# AWS本番環境（ECS Fargate）構築・運用手順

> 以前の EC2 + docker compose 構成の手順は廃止しました。現在の構成は `infra/` の CDK（`GuideGovernanceStack`、環境ごとの `GuideDataStack` と `GuideStack`）が正です。
> AWS 課金停止中のため、GitHub Actions からの自動デプロイはリポジトリ変数 `AWS_DEPLOY_ENABLED` を `true` にするまで動きません。

## 構成

環境は `production` と `staging` の 2 つで、どちらも同じコードから作ります。違いは `infra/lib/environments.ts` にまとめてあります。

| スタック | 主なリソース |
|---------|-------------|
| `GuideGovernanceStack` | GuardDuty、AWS Config、CloudTrail、GitHub Actions の OIDC Provider（アカウントに 1 つだけ置くもの） |
| `GuideDataStack` / `GuideStagingDataStack` | 消えると戻せないもの。VPC（Public / Private isolated × 2AZ）、RDS PostgreSQL 17、S3（添付ファイル・ALB アクセスログ）、ECR（タグ上書き不可）、Secrets Manager（APP_KEY・Google OAuth・DB のアプリ用ユーザー）、AWS Backup |
| `GuideStack` / `GuideStagingStack` | 作り直せるもの。ALB（HTTPS、HTTP→HTTPS リダイレクト）、AWS WAF（遮断した判定のみログ出力）、ECS Fargate、Parameter Store、CloudWatch アラーム、Synthetics Canary（外形監視）、GitHub Actions 用の Role |

| | production | staging |
|---|---|---|
| RDS | db.t4g.small・Multi-AZ・PITR 7 日・削除保護あり | db.t4g.micro・Single-AZ・PITR 1 日・削除保護なし |
| Web タスク | 2〜3 台 | 1 台 |
| AWS Backup | 日次 35 日 / 月次 1 年 | なし |
| 外形監視 | 5 分間隔 | なし |
| スタック削除時 | DB はスナップショットを残し、S3・ECR・ログは残る | すべて消える（立てて壊す使い方を想定） |

- リソース名はすべて `guide-<環境名>-…` です。以下の例は production の名前で書いています。
- Web タスクは 1 コンテナ内で nginx・PHP-FPM・Next.js を動かします。
- ALB とコンテナのヘルスチェックは `/healthz` です。Next.js が nginx 経由で Laravel の `/up` を確認し、両方が動いているときだけ 200 を返します。
- Interface VPC Endpoint は既定で作りません。必要なら `-c enableInterfaceEndpoints=true` を付けます（月額約 100 USD）。

### スタックを 2 つに分けている理由

データ側のスタックは合成時の context を一切読みません。`-c appImageTag` や `-c enableSlackAlerts` の付け忘れ・付け間違いは、アプリ側のスタックの差分にしかなりません。
GitHub Actions は `cdk deploy --exclusively` でアプリ側だけを更新します。**データ側のスタックの変更は自動では反映されないので、手動で `cdk deploy GuideDataStack` してください。** 未反映のままアプリ側がその変更に依存していると、自動デプロイは失敗して止まります。

### DB の接続ユーザー

| ユーザー | 使うタスク | できること | パスワード |
|---|---|---|---|
| `guide_admin`（マスター） | Migration タスクだけ | DDL を含むすべて | CDK が生成した Secret（自動ローテーションはしない） |
| `guide_app` | Web タスク | テーブルの SELECT / INSERT / UPDATE / DELETE と連番の利用だけ | Secret `guide/<環境名>/database-app`（CDK が生成） |

Migration タスクは毎回、`php artisan db:provision-app-user`（`guide_app` を作成し、権限とパスワードを Secret の値に合わせ直す）を流してから `php artisan migrate` を実行します。
Web タスクのタスク定義と Execution Role は、マスターユーザーの Secret を参照しません。

`guide_app` のパスワードを替えるときは、Secret `guide/<環境名>/database-app` の `password` を書き換えてから Migration タスクを実行し、続けて Web タスクを入れ替えます（`aws ecs update-service --force-new-deployment`）。PHP はリクエストごとに DB へ接続し直すため、Migration タスクがパスワードを書き換えてから Web タスクの入れ替えが終わるまでの数分間、古いタスクは DB に接続できずエラーを返します。利用の少ない時間に、停止を告知したうえで行ってください（Secret を書き換えない通常のデプロイでは、同じパスワードを設定し直すだけなので影響はありません）。

## 費用と監視の設定方針

量に比例して課金される設定は、現在の構成の規模（1クラスタ・1サービス・1コンテナ）に合わせて絞っています。
一方、障害の検知とバックアップの保持は、他人のデータを預かるサービスとしての値にしてあります（`infra/lib/environments.ts`）。
いずれも前提が変わったら戻す判断になるため、下げた理由と戻す条件を残します。

| 設定 | 現在の値 | 下げた理由 | 戻す条件 |
|------|---------|-----------|---------|
| Container Insights | 無効 | 1コンテナ構成では分解する対象がない。アラームとオートスケールは `AWS/ECS` の標準メトリクスを使っており影響しない | コンテナを分割する、サービスを増やす |
| WAF ログ | 遮断のみ | ALB アクセスログが別に S3 へ出ており、全リクエストの記録はそちらに残る | 本番公開の直後（誤検知で正常な利用を止めていないか確認する期間）。ルールを `COUNT` でチューニングするときはフィルタの `Conditions` に `COUNT` を足す |
| Synthetics Canary | production は 5 分間隔、staging は無し | 2 回連続の失敗で通知するので、検知まで約 10 分。実行回数がそのまま課金になるため、これより短くはしていない。アプリやコンテナの異常は `no-healthy-targets` などが 1 分周期で別に見ている | 間隔を変えるときは `environments.ts` の `canaryIntervalMinutes` を変える。アラームの `period` は同じ値に自動で揃う |
| ECR 保持数 | 20 本 | 保管費は月 1 USD 未満。ライフサイクルは稼働中かどうかを見ないため、本数を削りすぎると動いているタスクのイメージが消えうる | デプロイ頻度が上がり、20 本が数日ぶんを下回ったとき |
| AWS Backup | production は日次 35 日 / 月次 1 年、staging は無し | 直近 7 日は RDS 自動バックアップの PITR（ほぼ任意の時点へ復元可）が別にある。保持は容量 × 期間の課金 | 保持期間の要件（契約・法令）が決まったとき。根拠と復元手順は `docs/AWS_RESTORE_RUNBOOK.md` |

次の 3 つは据え置きで、AWS の課金を再開するときに判断します。

- **AWS Config**: `allSupported: true` で全リソース種別を継続記録しています（`infra/lib/governance-stack.ts`）。構成項目 1 件あたりの課金なので、ECS タスクの起動停止やデプロイの回数に比例して増えます。Config ルールは 1 本も定義していないため、現状は記録しているだけです。
- **CloudTrail**: `managementEvents: ALL` で読み取りイベントまで CloudWatch Logs へ流しています（`infra/lib/governance-stack.ts`）。監査価値は書き込み側にあるため、`WRITE_ONLY` にすればログ量を大きく減らせます。
- **RDS Multi-AZ**: 月額の中で最大の項目です。可用性を買っているので、SLA を負うかどうかで決まります。

## 事前準備

- Route 53 に公開ホストゾーンがあること
- Google OAuth のクライアント ID とシークレット（環境ごとにリダイレクト URI が違うので、環境ごとに用意する）
- アラーム通知を受け取るメールアドレス
- Slack へも通知する場合は Slack Incoming Webhook URL（`docs/SLACK_ALERTS.md`）

```bash
npm install -g aws-cdk
```

```bash
aws configure   # リージョンは ap-northeast-1
```

```bash
cd infra && npm ci
```

```bash
npx cdk bootstrap aws://<AWS_ACCOUNT_ID>/ap-northeast-1
```

### cdk bootstrap と GitHub Actions の権限

GitHub Actions の Role（`guide-<環境名>-github-actions`）が直接持つ権限は、ECR への push、Migration タスクの実行、bootstrap が作った 3 つの Role（deploy / file-publishing / lookup）の引き受けだけです。
ただし `cdk deploy` の実体は、bootstrap が作る CloudFormation 実行 Role（`cdk-hnb659fds-cfn-exec-role-…`）が行います。**この Role は bootstrap の既定では `AdministratorAccess` です。** つまり、デプロイできる人は CloudFormation を通じてアカウント内の何でも作れます。これはこのリポジトリのコードでは絞れません。絞るには次のどちらかが必要です。

- 権限を絞ったポリシーを自分で用意し、`npx cdk bootstrap … --cloudformation-execution-policies arn:aws:iam::<AWS_ACCOUNT_ID>:policy/<ポリシー名>` で bootstrap し直す
- production を別の AWS アカウントに分ける（staging 用の Role から production に触れなくなる。同じアカウントに置く限り、staging 用の Role も CloudFormation 経由で production のスタックを更新できる）

現状で効いている防御は GitHub 側です。Role を引き受けられるのは、GitHub の Environment（`production` / `staging`）を指定したジョブだけです。Environment の保護ルールを必ず設定してください（後述）。

## 初回構築（手動・環境ごとに 1 回）

production の手順を書きます。staging は、スタック名を `GuideStagingDataStack` / `GuideStagingStack`、名前の `production` を `staging` に読み替えてください。

> 以前の単一スタック構成（`GuideStack` に VPC・RDS・S3 が入っていた構成）が残っているアカウントには、そのまま重ねてデプロイしないでください。RDS や S3 が別スタックへ移るため、CloudFormation は既存のリソースを `GuideStack` から外そうとします。既存のデータがある場合は、先に `docs/AWS_RESTORE_RUNBOOK.md` の手順でスナップショットを取り、移行の段取りを決めてください。

### 1. アカウント共通のスタック

GuardDuty・AWS Config・CloudTrail の証跡・GitHub の OIDC Provider のうち、既にアカウントにあるものは `-c enableGuardDuty=false` / `-c enableConfig=false` / `-c enableCloudTrail=false` / `-c createGithubOidcProvider=false` で外します。

```bash
npx cdk deploy GuideGovernanceStack \
  --parameters GuideGovernanceStack:AlertEmail=<通知先メール>
```

### 2. データ側のスタック

VPC・RDS・S3・ECR・Secret を作ります。context もパラメータも要りません。

```bash
npx cdk deploy GuideDataStack
```

### 3. Google OAuth の値を Secret に入れる

Secret は仮の値で作られています。値はコマンドの引数ではなくファイルから渡します（シェルの履歴に残さないため）。

```bash
cat > /tmp/google-oauth.json   # {"client_id": "...", "client_secret": "..."} を貼り付けて Ctrl-D
```

```bash
aws secretsmanager put-secret-value \
  --secret-id guide/production/google-oauth \
  --secret-string file:///tmp/google-oauth.json
```

```bash
rm /tmp/google-oauth.json
```

値を入れる前でもアプリは起動しますが、Google ログインは失敗します。あとから値を替えたときは、Web タスクを入れ替えると反映されます（`aws ecs update-service --cluster guide-production --service guide-production-web --force-new-deployment`）。

### 4. 最初のイメージを push

`NEXT_PUBLIC_SITE_URL` はビルド時に埋め込まれるので、公開する URL を渡します。

```bash
REPO=$(aws cloudformation describe-stacks --stack-name GuideDataStack \
  --query "Stacks[0].Outputs[?OutputKey=='ApplicationRepositoryUri'].OutputValue" --output text)
```

```bash
TAG=$(git rev-parse HEAD)
```

```bash
aws ecr get-login-password | docker login --username AWS --password-stdin "${REPO%/*}"
```

```bash
docker buildx build --platform linux/arm64 \
  --build-arg "NEXT_PUBLIC_SITE_URL=https://<guide.example.com>" \
  -t "${REPO}:${TAG}" --push .
```

### 5. アプリ側のスタック（タスク 0 台）

DB にはまだ `guide_app` ユーザーもテーブルも無いので、最初はタスク数 0 で作ります。

```bash
npx cdk deploy GuideStack --exclusively \
  -c appImageTag=${TAG} \
  -c desiredCount=0 \
  --parameters GuideStack:DomainName=<guide.example.com> \
  --parameters GuideStack:HostedZoneId=<Z0123...> \
  --parameters GuideStack:HostedZoneName=<example.com> \
  --parameters GuideStack:AlertEmail=<通知先メール>
```

Slack へも通知する場合は、`-c enableSlackAlerts=true` と `--parameters GuideStack:SlackWebhookUrl=<Webhook URL>` を足します（省略すると Slack 関連のリソースは作られません。詳細は `docs/SLACK_ALERTS.md`）。秘密の値のうち、CloudFormation のパラメータで受け取るのはこの Webhook URL だけです。
`-c enableSlackAlerts=true` は以降の**手動の** `cdk deploy GuideStack` でも毎回付けてください。付け忘れると Slack 関連のリソースが削除され、Slack 通知が外れます。
GitHub Actions からの自動デプロイは、変数 `SLACK_ALERTS_ENABLED` の値を `-c enableSlackAlerts=<値>` としてそのまま渡します（未設定なら `false`）。Slack を有効にしたら、この変数も `true` に設定してください。
この変数に入れられるのは小文字の `true` と `false` だけです。`True` や `TRUE` のような別の綴りを入れると、CDK が `CDK context enableSlackAlerts must be true or false` で例外を投げ、**デプロイそのものが失敗**します（`infra/lib/guide-stack.ts` の `booleanContext`）。

デプロイ後、SNS から届く確認メールのリンクを開いて購読を有効にしてください（有効にしないと通知が届きません）。
タスク 0 台の間は「正常なタスクがない」「外形監視失敗」のアラームが鳴りますが、手順 6 の完了で復旧します。

### 6. Migration を実行してからタスクを起動

```bash
# 6-1. Migration を実行（<...> は GuideStack の Output の値）
aws ecs run-task --cluster guide-production --launch-type FARGATE \
  --task-definition guide-production-migration \
  --network-configuration "awsvpcConfiguration={subnets=[<PublicSubnetIds>],securityGroups=[<TaskSecurityGroupId>],assignPublicIp=ENABLED}"
# ロググループ /ecs/guide-production/migration で、
# 「PostgreSQL ユーザー guide_app を用意しました。」と migrate の正常終了を確認する
```

```bash
# 6-2. Web タスクを起動（staging は desiredCount=1）
npx cdk deploy GuideStack --exclusively -c appImageTag=${TAG} -c desiredCount=2
```

### 7. GitHub Actions からのデプロイを有効にする

1. GitHub のリポジトリ設定で Environment `production` と `staging` を作る
2. `production` の保護ルールを設定する
   - **Deployment branches and tags**: `main` だけを許可する
   - **Required reviewers**: 承認者を指定する（staging で確認してから本番へ出す、という手順をここで強制する）
3. 各 Environment のシークレット `AWS_ROLE_TO_ASSUME` に、その環境のスタックの Output `GitHubActionsRoleArn` を登録する
4. リポジトリ変数 `AWS_DEPLOY_ENABLED`（production）と `AWS_STAGING_DEPLOY_ENABLED`（staging）を `true` に設定する
5. Slack 通知を有効にしている場合は、変数 `SLACK_ALERTS_ENABLED` も `true` に設定する（Environment の変数にすれば環境ごとに変えられる）

AWS 側の Role は `repo:<owner>/<repo>:environment:<環境名>` のジョブだけを信頼します。Environment を指定していないジョブや、別のブランチ・タグからは引き受けられません。

## 通常デプロイ（自動）

`main` に push すると `.github/workflows/deploy.yml` が、検証のあと `deploy-staging` → `deploy-production` の順に `.github/workflows/aws-deploy.yml` を呼びます。staging が失敗すると production へは進みません。staging を無効にしている（変数が `true` でない）ときは、production だけが動きます。

各環境で行うこと:

1. コミット SHA をタグにしてイメージをビルドし、その環境の ECR へ push（その環境の URL を `NEXT_PUBLIC_SITE_URL` に渡す）
2. 新しいイメージで Migration タスクを 1 回実行し、失敗したらここで止める
3. `cdk deploy <スタック名> --exclusively -c appImageTag=<SHA> -c enableSlackAlerts=<SLACK_ALERTS_ENABLED の値>` で Web タスクをローリング更新（失敗時は自動ロールバック）
4. `https://<ドメイン>/healthz` の疎通確認

注意点:

- 手動で `cdk deploy GuideStack` するときも、必ず `-c appImageTag=<稼働中のSHA>` を付けてください。付けないとエラーで止まります（`latest` も拒否されます）。
- `desiredCount` は台数を変えたいときだけ渡します。渡さなければ現在の台数と Auto Scaling の設定が維持されます。
- Migration は新旧どちらのコードでも動く形（列の追加 → データ移行 → 次のリリースで旧列削除）で書いてください。Migration 完了から Web タスク入れ替え完了までの間は旧コードが新スキーマで動きます。
- Migration タスクの定義（コマンドや環境変数）を変えた場合、その変更が効くのは次のデプロイからです。ワークフローは「いま登録されているタスク定義のイメージだけを差し替えて」Migration を実行し、そのあとで `cdk deploy` がタスク定義を更新するためです。

### staging を止める・壊す

使わない期間の費用を止めるには、`AWS_STAGING_DEPLOY_ENABLED` を外してからスタックを消します。staging はデータも残りません。

```bash
npx cdk destroy GuideStagingStack
```

```bash
npx cdk destroy GuideStagingDataStack
```

Secrets Manager の Secret は削除予約（既定 30 日）に入り、その間は同じ名前で作り直せません。すぐに立て直す場合は、`aws secretsmanager delete-secret --secret-id guide/staging/<名前> --force-delete-without-recovery` で先に消してください。

## 監視

通知先は CloudFormation Parameter `AlertEmail` の SNS 購読です。障害時と復旧時の両方に通知します。
`-c enableSlackAlerts=true` でデプロイすると、同じ SNS トピックを購読する Lambda（`guide-production-slack-alarm-notifier`）が Slack へも転送します。設定の詳細は `docs/SLACK_ALERTS.md` にあります。

| アラーム | 内容 |
|---------|------|
| `guide-production-no-healthy-targets` | 正常なタスクが 0（データ欠損も障害扱い） |
| `guide-production-elb-5xx` | ALB 自身が 5xx を返している |
| `guide-production-target-5xx` | アプリが 5xx を返している |
| `guide-production-health-check-failed` | 外形監視（5 分ごとに `/healthz`）が 2 回連続で失敗（staging には無い） |
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
- API 操作: CloudTrail（`/aws/cloudtrail/guide-account` と `GuideGovernanceStack` の S3）

## バックアップ

- RDS 自動バックアップ: 7 日（ポイントインタイムリカバリ可）
- AWS Backup: 日次（35 日保管）・月次（1 年保管）、Vault `guide-production`
- 添付ファイル用 S3: バージョニング有効（旧バージョンは 90 日で削除）

目標とする復旧時間・復旧時点と、復元の手順は `docs/AWS_RESTORE_RUNBOOK.md` にあります。

## 未対応・今後の検討

- CloudFormation 実行 Role の権限を絞る、または production を別アカウントへ分ける（「cdk bootstrap と GitHub Actions の権限」を参照）
- DB パスワードの自動ローテーション（`guide_app` もマスターユーザーも、現状は手動。`guide_app` は上の手順で替えられる）
- 復元訓練の実施（`docs/AWS_RESTORE_RUNBOOK.md` の手順は、まだ実際の AWS 環境で通していない）
- バックアップの別リージョンへのコピー
- 負荷が増えた場合、セッションとキャッシュの ElastiCache（Valkey）への移行
