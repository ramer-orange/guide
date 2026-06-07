# AWS構築完了記録

## 構築したAWSリソース

| リソース | 内容 |
|---------|------|
| EC2 | t4g.small（ARM/Graviton）/ Ubuntu 24.04 LTS / EBS gp3 20GB |
| Elastic IP | `176.34.20.141`（EC2に自動アタッチ） |
| Security Group | 22番（自分のIPのみ）/ 80番・443番（全許可） |
| IAM Role | EC2からS3へのReadWrite権限（アクセスキー不要） |
| EC2 Key Pair | `guide-ec2-key`（秘密鍵はSSM Parameter Storeに保存） |
| S3 | `guide-production-files`（添付ファイル用、非公開） |
| S3 | `guide-production-backups`（DBバックアップ用、7日で自動削除） |

## CDK構築手順（初回のみ）

```bash
npm install -g aws-cdk
aws configure
# Access Key ID・Secret Access Key・リージョン(ap-northeast-1)・出力形式(json)を入力

cd infra
npx cdk bootstrap aws://949925037534/ap-northeast-1
npx cdk deploy
```

所要時間：約3分30秒。

デプロイ後にSSH秘密鍵を取得:

```bash
aws ssm get-parameter \
  --name "/ec2/keypair/key-095bda56bce46d758" \
  --with-decryption \
  --query Parameter.Value \
  --output text > ~/.ssh/guide-ec2.pem
chmod 600 ~/.ssh/guide-ec2.pem
```

SSH接続確認:

```bash
ssh -i ~/.ssh/guide-ec2.pem ubuntu@176.34.20.141
```

## 初回アプリデプロイ手順

### 1. EC2にSSH接続

```bash
ssh -i ~/.ssh/guide-ec2.pem ubuntu@176.34.20.141
```

### 2. リポジトリをclone

```bash
git clone https://github.com/ramer-orange/guide.git ~/guide
cd ~/guide
```

### 3. .envを作成

```bash
cat > ~/guide/.env << 'EOF'
APP_NAME=PLAGINE
APP_ENV=production
APP_KEY=base64:...  # php artisan key:generate --show で生成
APP_DEBUG=false
APP_URL=http://176.34.20.141  # ドメイン取得後はhttps://ドメイン名に変更
APP_TIMEZONE=Asia/Tokyo

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=guide-db
DB_USERNAME=kaikai
DB_PASSWORD=...

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=false  # HTTPS化後はtrueに変更
CACHE_STORE=database
QUEUE_CONNECTION=database

FILESYSTEM_DISK=local
FILESYSTEM_UPLOADS_DISK=s3
FILESYSTEM_TEMPORARY_URL_TTL=10
AWS_DEFAULT_REGION=ap-northeast-1
AWS_BUCKET=guide-production-files
AWS_USE_PATH_STYLE_ENDPOINT=false

LOG_CHANNEL=stderr
MAIL_MAILER=log
EOF
```

AWS認証情報（`AWS_ACCESS_KEY_ID` 等）は不要。EC2のIAM Roleが自動的にS3へのアクセス権限を提供する。

### 4. コンテナ起動

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

### 5. 依存パッケージのインストール・ビルド

```bash
docker compose -f docker-compose.prod.yml exec app composer install --no-dev --optimize-autoloader
docker compose -f docker-compose.prod.yml exec app bash -c 'npm ci && npm run build'
```

### 6. DBマイグレーション・キャッシュ生成

```bash
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
docker compose -f docker-compose.prod.yml exec app php artisan route:cache
docker compose -f docker-compose.prod.yml exec app php artisan view:cache
```

## 通常デプロイ手順（2回目以降）

```bash
ssh -i ~/.ssh/guide-ec2.pem ubuntu@176.34.20.141
cd ~/guide
git pull origin main
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app composer install --no-dev --optimize-autoloader
docker compose -f docker-compose.prod.yml exec app bash -c 'npm ci && npm run build'
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
docker compose -f docker-compose.prod.yml exec app php artisan route:cache
docker compose -f docker-compose.prod.yml exec app php artisan view:cache
```

## トラブルシューティング記録

### Dockerfileのビルドエラー（COPY: file not found）

**原因**: `docker/8.3/Dockerfile` の COPY コマンドが `start-container` 等をビルドコンテキストルートから探していたが、実際のファイルは `docker/8.3/` にある。`docker-compose.prod.yml` のビルドコンテキストは `.`（プロジェクトルート）なので不一致が発生。

**修正**: `docker/8.3/Dockerfile` の COPY パスを以下に変更した。

```dockerfile
# 修正前
COPY start-container /usr/local/bin/start-container
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY php.ini /etc/php/8.3/cli/conf.d/99-sail.ini

# 修正後
COPY docker/8.3/start-container /usr/local/bin/start-container
COPY docker/8.3/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/8.3/php.ini /etc/php/8.3/cli/conf.d/99-sail.ini
```

### 500エラー（Vite manifest not found）

**原因**: `public/build/manifest.json` が存在しない。`npm run build` を実行していなかった。

**対処**: `npm ci && npm run build` を実行してViteのビルド成果物を生成する。

## アクセス情報

| 項目 | 値 |
|------|---|
| サイトURL | http://176.34.20.141 |
| SSH接続 | `ssh -i ~/.ssh/guide-ec2.pem ubuntu@176.34.20.141` |
| SSH秘密鍵 | `~/.ssh/guide-ec2.pem` |
| SSMパラメータ名 | `/ec2/keypair/key-095bda56bce46d758` |

## 次のステップ

- [ ] ドメインのAレコードを `176.34.20.141` に向ける
- [ ] CertbotでHTTPS化する
- [ ] `.env` の `APP_URL` を `https://ドメイン名` に変更する
- [ ] `.env` の `SESSION_SECURE_COOKIE` を `true` に変更する
- [ ] PostgreSQLバックアップcronを設定する
