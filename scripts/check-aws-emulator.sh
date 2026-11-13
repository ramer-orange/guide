#!/usr/bin/env bash
# 本番用の Docker イメージを PostgreSQL 17 と AWS Emulator の S3 につなぎ、
# マイグレーション、/healthz、添付 PDF のアップロードから削除までを確かめる。
# エミュレーターで信頼できるのは S3 までなので、RDS・ECS・CloudFormation は使わない
# （理由は docs/AWS_EMULATOR.md の検証結果を参照）。
set -euo pipefail

repo=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
web_port=${GUIDE_EMULATOR_WEB_PORT:-18088}
if [[ ! $web_port =~ ^[1-9][0-9]{0,4}$ ]] || (( web_port > 65535 )); then
    echo 'GUIDE_EMULATOR_WEB_PORT must be a TCP port (1–65535).' >&2
    exit 2
fi
for command in aws curl docker git jq openssl; do
    command -v "$command" >/dev/null || { echo "Required command: $command" >&2; exit 2; }
done
case $(docker info --format '{{.Architecture}}') in
    aarch64|arm64) emulator_arch=aarch64 ;;
    x86_64|amd64) emulator_arch=x86_64 ;;
    *) echo 'AWS Emulator requires an Arm64 or x86-64 Docker host.' >&2; exit 2 ;;
esac

# 一時ディレクトリには DB パスワードとエミュレーターの認証情報を書くので、他ユーザーから読めないようにする。
umask 077
scratch=$(mktemp -d)
network=''
emulator=''
database=''
web=''
cleanup() {
    local status=$?
    if (( status != 0 )) && [[ -n $web ]]; then
        docker logs --tail 30 "$web" >&2 || true
    fi
    for container in "$web" "$database" "$emulator"; do
        if [[ -n $container ]]; then
            docker stop "$container" >/dev/null || true
        fi
    done
    if [[ -n $network ]]; then
        docker network rm "$network" >/dev/null || true
    fi
    rm -rf -- "$scratch"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

wait_for() {
    local label=$1
    shift
    for (( attempt=1; attempt<=30; attempt++ )); do
        if "$@" >/dev/null 2>&1; then return; fi
        sleep 1
    done
    echo "Timed out waiting for $label" >&2
    return 1
}

origin="http://127.0.0.1:$web_port"
commit=$(git -C "$repo" rev-parse --short HEAD)
guide_image="guide:aws-emulator-$commit-$web_port"
emulator_image="guide-aws-emulator:1.0.341-$emulator_arch"
# 手元の未コミットの変更や .env が混ざらないよう、HEAD のコミットだけを取り出してビルドする。
# umask 077 のままだとファイルが 600 になり、イメージ内の www-data から読めないので 755/644 に戻す。
mkdir -m 755 "$scratch/source"
git -C "$repo" -c tar.umask=0022 archive HEAD | tar -xp -C "$scratch/source"
docker build --build-arg "NEXT_PUBLIC_SITE_URL=$origin" -t "$guide_image" "$scratch/source"
docker build --build-arg "EMULATOR_ARCH=$emulator_arch" -t "$emulator_image" - < "$repo/docker/aws-emulator/Dockerfile"

# Web の公開ポート 80 をエミュレーター側のコンテナで開けているのは、後で Web コンテナを
# エミュレーターと同じネットワーク名前空間（--network container:）で動かすため。
network=$(docker network create "guide-aws-emulator-$$")
emulator=$(docker run -d --rm --network "$network" --memory 2g \
    -p '127.0.0.1::9297' -p "127.0.0.1:$web_port:80" "$emulator_image")
emulator_address=$(docker port "$emulator" 9297/tcp)
emulator_state=/home/emulator/.aws/emulator/9297
wait_for 'AWS Emulator credentials' docker exec "$emulator" test -s "$emulator_state/emulator_credentials"
docker exec "$emulator" cat "$emulator_state/emulator_credentials" > "$scratch/credentials"
docker exec "$emulator" cat "$emulator_state/ca.pem" > "$scratch/ca.pem"
wait_for 'AWS Emulator HTTPS' curl -fsS --max-time 2 --cacert "$scratch/ca.pem" "https://$emulator_address/__admin/health"

# 手元の ~/.aws の設定や本物の認証情報を一切読ませず、必ずエミュレーターだけに向ける。
aws_local() {
    env -i PATH="$PATH" AWS_CONFIG_FILE=/dev/null AWS_SHARED_CREDENTIALS_FILE="$scratch/credentials" \
        AWS_CA_BUNDLE="$scratch/ca.pem" AWS_EC2_METADATA_DISABLED=true AWS_MAX_ATTEMPTS=1 \
        aws --no-cli-pager --endpoint-url "https://$emulator_address" --region ap-northeast-1 "$@"
}
[[ $(aws_local sts get-caller-identity --query Account --output text) == 000000000000 ]]
bucket=guide-emulator-attachments
aws_local s3api create-bucket --bucket "$bucket" --create-bucket-configuration LocationConstraint=ap-northeast-1 >/dev/null
aws_local s3api put-public-access-block --bucket "$bucket" \
    --public-access-block-configuration BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true
aws_local s3api put-bucket-ownership-controls --bucket "$bucket" --ownership-controls 'Rules=[{ObjectOwnership=BucketOwnerEnforced}]'

# 本番の RDS の代わりに素の PostgreSQL を使う。エミュレーターの RDS は Laravel が接続時に送る
# SET search_path を拒否するため使えない。
admin_password=$(openssl rand -hex 24)
app_password=$(openssl rand -hex 24)
database=$(docker run -d --rm --network "$network" --network-alias guide-db \
    -e POSTGRES_USER=guide_admin -e "POSTGRES_PASSWORD=$admin_password" -e POSTGRES_DB=guide postgres:17-alpine)
wait_for PostgreSQL docker exec "$database" pg_isready -h 127.0.0.1 -U guide_admin -d guide

cat > "$scratch/app.env" <<EOF
APP_ENV=local
APP_NAME=Guide
APP_URL=$origin
APP_KEY=base64:$(openssl rand -base64 32)
DB_CONNECTION=pgsql
DB_HOST=guide-db
DB_PORT=5432
DB_DATABASE=guide
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=false
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=s3
FILESYSTEM_UPLOADS_DISK=s3
AWS_DEFAULT_REGION=ap-northeast-1
AWS_BUCKET=$bucket
AWS_ENDPOINT=https://127.0.0.1:9297
AWS_USE_PATH_STYLE_ENDPOINT=true
AWS_EC2_METADATA_DISABLED=true
AWS_MAX_ATTEMPTS=1
RUN_MIGRATIONS_ON_START=false
EOF
awk -F ' *= *' '/^aws_access_key_id/{print "AWS_ACCESS_KEY_ID=" $2} /^aws_secret_access_key/{print "AWS_SECRET_ACCESS_KEY=" $2}' \
    "$scratch/credentials" >> "$scratch/app.env"
# エミュレーターの HTTPS は独自 CA の証明書なので、PHP（AWS SDK が使う curl / openssl）に CA を教える。
# イメージは変えずに conf.d へ ini を差し込むだけにして、本番と同じイメージで検証する。
printf 'curl.cainfo=/emulator-ca.pem\nopenssl.cafile=/emulator-ca.pem\n' > "$scratch/php-ca.ini"
chmod 644 "$scratch/ca.pem" "$scratch/php-ca.ini"

# 本番と同じく Migration だけマスターユーザー（guide_admin）で接続し、guide_app を用意する。
# コマンドは ECS の Migration タスク（infra/lib/guide-stack.ts）と同じ。migrate --isolated はロックを
# キャッシュ（CACHE_STORE=database のテーブル）に取るため、テーブルがまだない空の DB では失敗する。
# migrations テーブルがない初回だけ、ロック無しで先に流す。
docker run --rm --network "$network" --env-file "$scratch/app.env" \
    -e DB_USERNAME=guide_admin -e "DB_PASSWORD=$admin_password" \
    -e DB_APP_USERNAME=guide_app -e "DB_APP_PASSWORD=$app_password" "$guide_image" sh -c \
    'php artisan db:provision-app-user && { php artisan migrate:status >/dev/null 2>&1 || php artisan migrate --force; } && php artisan migrate --force --isolated'
# Web コンテナはエミュレーターと同じネットワーク名前空間に入れ、S3 へは AWS_ENDPOINT の
# https://127.0.0.1:9297 で届くようにする。ホストの CLI と同じループバック宛てにそろえ、
# エミュレーターの証明書を名前の違いなしに検証させるため。check-attachment.php もこの値で
# 隔離環境かどうかを判定している。guide-db にはエミュレーターが属する $network 経由で届く。
web=$(docker run -d --rm --network "container:$emulator" --env-file "$scratch/app.env" \
    -e DB_USERNAME=guide_app -e "DB_PASSWORD=$app_password" \
    -v "$scratch/ca.pem:/emulator-ca.pem:ro" -v "$scratch/php-ca.ini:/usr/local/etc/php/conf.d/zz-emulator-ca.ini:ro" "$guide_image")
wait_for 'Guide /healthz' curl -fsS --max-time 4 "$origin/healthz"
curl -fsS "$origin/healthz" | jq -e '.status == "ok" and .backend == 200' >/dev/null
curl -fsS "$origin/" > /dev/null

# 認証付きの API 呼び出しはコンテナ内の PHP から行う（セッション Cookie を同じ APP_KEY で作れるため）。
docker exec -i "$web" php < "$repo/scripts/aws-emulator/check-attachment.php"
# アプリ側の確認とは別に、エミュレーター側から見てもバケットが空に戻ったことを確かめる。
aws_local s3api list-objects-v2 --bucket "$bucket" --no-paginate | jq -e '.KeyCount == 0' >/dev/null
echo "Guide $commit passed /, /healthz and the AWS Emulator S3 attachment check."
