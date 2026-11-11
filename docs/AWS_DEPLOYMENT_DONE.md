# AWS本番環境（ECS Fargate）構築・運用手順

> 以前の EC2 + docker compose 構成の手順は廃止しました。現在の構成は `infra/` の CDK（`GuideStack` と `GuideGovernanceStack`）が正です。
> AWS 課金停止中のため、GitHub Actions からの自動デプロイはリポジトリ変数 `AWS_DEPLOY_ENABLED` を `true` にするまで動きません。

## 構成

| スタック | 主なリソース |
|---------|-------------|
| `GuideStack` | VPC（Public / Private isolated × 2AZ）、ALB（HTTPS、HTTP→HTTPS リダイレクト）、AWS WAF（ログ出力あり）、ECS Fargate（2〜3タスク）、RDS PostgreSQL 17（db.t4g.small・Multi-AZ）、ECR（タグ上書き不可）、S3（添付ファイル・監査ログ）、Secrets Manager、Parameter Store、CloudWatch アラーム、Synthetics Canary（外形監視）、AWS Backup、CloudTrail、GitHub Actions 用 OIDC Role |
| `GuideGovernanceStack` | GuardDuty、AWS Config（アカウント・リージョンに 1 つだけ作れるもの） |

- Web タスクは 1 コンテナ内で nginx・PHP-FPM・Next.js を動かします。
- ALB とコンテナのヘルスチェックは `/healthz` です。Next.js が nginx 経由で Laravel の `/up` を確認し、両方が動いているときだけ 200 を返します。
- `php artisan migrate` は専用の Migration タスクだけが実行します（Web タスクは `RUN_MIGRATIONS_ON_START=false`）。
- Interface VPC Endpoint は既定で作りません。必要なら `-c enableInterfaceEndpoints=true` を付けます（月額約 100 USD）。

## 事前準備

- Route 53 に公開ホストゾーンがあること
- Google OAuth のクライアント ID とシークレット
- アラーム通知を受け取るメールアドレス

```bash
npm install -g aws-cdk
aws configure   # リージョンは ap-northeast-1
cd infra
npm ci
npx cdk bootstrap aws://<AWS_ACCOUNT_ID>/ap-northeast-1
```

## 初回構築（手動・1回だけ）

### 1. アカウント共通の監査スタック

GuardDuty や AWS Config が既に有効なアカウントでは、該当するほうを `-c enableGuardDuty=false` / `-c enableConfig=false` で外します。

```bash
npx cdk deploy GuideGovernanceStack \
  --parameters GuideGovernanceStack:AlertEmail=<通知先メール>
```

### 2. アプリの基盤（タスク 0 台）

ECR が空なので、最初はタスク数 0 で作ります。

```bash
npx cdk deploy GuideStack \
  -c desiredCount=0 \
  --parameters GuideStack:DomainName=<guide.example.com> \
  --parameters GuideStack:HostedZoneId=<Z0123...> \
  --parameters GuideStack:HostedZoneName=<example.com> \
  --parameters GuideStack:GoogleClientId=<client id> \
  --parameters GuideStack:GoogleClientSecret=<client secret> \
  --parameters GuideStack:AlertEmail=<通知先メール>
```

デプロイ後、SNS から届く確認メールのリンクを開いて購読を有効にしてください（有効にしないと通知が届きません）。
タスク 0 台の間は「正常なタスクがない」「外形監視失敗」のアラームが鳴りますが、手順 4 の完了で復旧します。

### 3. 最初のイメージを push

```bash
REPO=$(aws cloudformation describe-stacks --stack-name GuideStack \
  --query "Stacks[0].Outputs[?OutputKey=='ApplicationRepositoryUri'].OutputValue" --output text)
URL=$(aws cloudformation describe-stacks --stack-name GuideStack \
  --query "Stacks[0].Outputs[?OutputKey=='ApplicationUrl'].OutputValue" --output text)
TAG=$(git rev-parse HEAD)

aws ecr get-login-password | docker login --username AWS --password-stdin "${REPO%/*}"
docker buildx build --platform linux/arm64 \
  --build-arg "NEXT_PUBLIC_SITE_URL=${URL}" \
  -t "${REPO}:${TAG}" --push .
```

### 4. Migration を実行してからタスクを起動

```bash
# 4-1. タスク定義を手順 3 のイメージに更新（まだ 0 台のまま）
npx cdk deploy GuideStack -c appImageTag=${TAG} -c desiredCount=0

# 4-2. Migration を実行（<...> は GuideStack の Output の値）
aws ecs run-task --cluster guide-production --launch-type FARGATE \
  --task-definition guide-production-migration \
  --network-configuration "awsvpcConfiguration={subnets=[<PublicSubnetIds>],securityGroups=[<TaskSecurityGroupId>],assignPublicIp=ENABLED}"
# ロググループ /ecs/guide-production/migration で正常終了を確認する

# 4-3. Web タスクを 2 台で起動
npx cdk deploy GuideStack -c appImageTag=${TAG} -c desiredCount=2
```

### 5. GitHub Actions からのデプロイを有効にする

1. Output `GitHubActionsRoleArn` を GitHub の Secret `AWS_ROLE_TO_ASSUME` に登録
2. リポジトリ変数 `AWS_DEPLOY_ENABLED` を `true` に設定

## 通常デプロイ（自動）

`main` に push すると `.github/workflows/deploy.yml` の `deploy` ジョブが次の順に実行します。

1. 検証（PHP / Next.js のテスト、CDK のテスト）
2. コミット SHA をタグにしてイメージをビルドし ECR へ push（本番 URL を `NEXT_PUBLIC_SITE_URL` に渡す）
3. 新しいイメージで Migration タスクを 1 回実行し、失敗したらここで止める
4. `cdk deploy GuideStack -c appImageTag=<SHA>` で Web タスクをローリング更新（失敗時は自動ロールバック）
5. `https://<ドメイン>/healthz` の疎通確認

注意点:

- 手動で `cdk deploy GuideStack` するときも、必ず `-c appImageTag=<稼働中のSHA>` を付けてください。付けないとエラーで止まります（`latest` も拒否されます）。
- `desiredCount` は台数を変えたいときだけ渡します。渡さなければ現在の台数と Auto Scaling の設定が維持されます。
- Migration は新旧どちらのコードでも動く形（列の追加 → データ移行 → 次のリリースで旧列削除）で書いてください。Migration 完了から Web タスク入れ替え完了までの間は旧コードが新スキーマで動きます。

## 監視

通知先は CloudFormation Parameter `AlertEmail` の SNS 購読です。障害時と復旧時の両方に通知します。

| アラーム | 内容 |
|---------|------|
| `guide-production-no-healthy-targets` | 正常なタスクが 0（データ欠損も障害扱い） |
| `guide-production-elb-5xx` | ALB 自身が 5xx を返している |
| `guide-production-target-5xx` | アプリが 5xx を返している |
| `guide-production-health-check-failed` | 外形監視（5 分ごとに `/healthz`）が失敗 |
| `guide-production-high-task-cpu` | タスクの CPU が高い |
| `guide-production-high-database-cpu` | RDS の CPU が高い |
| `guide-production-low-database-cpu-credit` | RDS の CPU クレジット残量が少ない |
| `guide-production-low-database-memory` | RDS の空きメモリが少ない |
| `guide-production-low-database-storage` | RDS の空き容量が 2GB 未満 |
| `guide-production-high-database-connections` | RDS の接続数が多い |

ログ:

- アプリ: `/ecs/guide-production/application`
- Migration: `/ecs/guide-production/migration`
- WAF: `aws-waf-logs-guide-production`（Cookie・Authorization は記録しない）
- API 操作: CloudTrail（`/aws/cloudtrail/guide-production` と監査用 S3）

## バックアップ

- RDS 自動バックアップ: 7 日（ポイントインタイムリカバリ可）
- AWS Backup: 日次（35 日保管）・月次（1 年保管）、Vault `guide-production`
- 添付ファイル用 S3: バージョニング有効（旧バージョンは 90 日で削除）

## 未対応・今後の検討

- DB パスワードの自動ローテーション: ECS タスクは起動時にパスワードを読み込むため、単純に有効にすると稼働中のタスクが DB に接続できなくなります。ローテーション後にタスクを入れ替える仕組みとあわせて導入してください。
- バックアップの別リージョンへのコピー
- 負荷が増えた場合、セッションとキャッシュの ElastiCache（Valkey）への移行
