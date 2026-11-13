# CLAUDE.md

PLAGINE（旅のしおり）。Laravel 12 の API と Next.js 16 のフロントエンドを1つのリポジトリに置き、Render と AWS ECS Fargate の両方へデプロイできる構成。

## 構成

| パス | 中身 |
| --- | --- |
| `app/`, `routes/`, `config/`, `database/` | Laravel 12 の API |
| `tests/` | Pest 3。`tests/Feature` には `RefreshDatabase` が自動で付く（`tests/Pest.php`） |
| `frontend/` | Next.js 16（App Router、`output: "standalone"`）。テストは vitest |
| `infra/` | AWS CDK v2。環境ごとの値は `infra/lib/environments.ts`、消えると戻せないもの（VPC・RDS・S3・ECR・Secret）は `guide-data-stack.ts`、作り直せるもの（ECS・ALB・監視）は `guide-stack.ts` |
| `docs/` | 設計・運用ドキュメント。`docs/superpowers/plans/` は実装計画 |
| `scripts/`, `docker/`, `conf/`, `nginx/` | 実行環境とランタイム検証 |

## コマンド

```bash
composer install && npm ci && npm --prefix frontend ci && npm --prefix infra ci
```

```bash
./vendor/bin/pest
```

```bash
./vendor/bin/pint --test
```

```bash
npm --prefix frontend run test && npm --prefix frontend run typecheck && npm --prefix frontend run lint
```

```bash
npm --prefix infra run test && npm --prefix infra run build
```

```bash
npm run cdk:synth
```

- PHP のテストは `phpunit.xml` で sqlite の `:memory:` を使う。DB の用意は不要だが `.env` の `APP_KEY` は必要（`.env` は Git 管理外なので、新しい worktree では既存の checkout からコピーする）。
- ローカルで全体を動かすときは `docker compose up`。`composer dev` は PHP 側だけを立てる。
- E2E は `npm run e2e:next`（Playwright）。

## 規約

- **コミットメッセージは日本語**。`feat:` `fix:` `test:` `ci:` `docs:` などの接頭辞を付ける。
- PHP は Pint（Laravel プリセット）、TypeScript は ESLint + Prettier。インデントは PHP が4スペース、TypeScript も4スペース。
- コメントは日本語で、「なぜこう書いたか」を書く。既存コードはその密度で書かれている。
- PHP のバージョンは `composer.json` で範囲を固定し、`scripts/check-php-runtime.php` が install 時に検証する。Node も同様（`frontend/scripts/check-node-runtime.mjs`）。勝手に上げない。

## 環境ごとの事情

- **Render**: 無料プラン。PHP の脆弱性対応のため現在は公開を一時停止している。設定は `render.yaml`。
- **AWS**: 請求停止中のため、`.github/workflows/deploy.yml` のデプロイはリポジトリ変数 `AWS_DEPLOY_ENABLED`（production）と `AWS_STAGING_DEPLOY_ENABLED`（staging）で止めてある。構築と運用の手順は `docs/AWS_DEPLOYMENT_DONE.md`、復元の手順は `docs/AWS_RESTORE_RUNBOOK.md`。
- **AWS のスタック**: production は `GuideDataStack` + `GuideStack`、staging は `GuideStagingDataStack` + `GuideStagingStack`。CI は `cdk deploy --exclusively` でアプリ側だけを更新する。データ側（`guide-data-stack.ts`）を変えたら手動でデプロイする。データ側には合成時の context を読ませない。
- **DB の接続ユーザー**: Web タスクは権限を絞った `guide_app`、Migration タスクだけがマスターユーザーで接続する。`guide_app` は Migration タスクが `php artisan db:provision-app-user` で用意する。DDL が要る処理を Web タスク側（リクエスト処理や起動スクリプト）に書かない。
- **秘密の値**: Google OAuth は CloudFormation のパラメータで受け取らない。Secrets Manager に仮の値で Secret を作り、デプロイ後に `put-secret-value` で入れる。Slack の Webhook URL だけは NoEcho パラメータ（`SlackWebhookUrl`）で受け取る。
- **CDK の context フラグ**: `enableSlackAlerts` のような合成時フラグは、付けずに `cdk deploy` すると対応するリソースが削除される。CI 側（`deploy.yml`）が同じ値を渡していることを必ず確認する。

## 障害通知

`error` 以上のログ、CloudWatch アラーム、CI の失敗、Next.js のサーバーエラーを Slack へ送る。有効化の手順と各経路の条件は `docs/SLACK_ALERTS.md` にまとめてある。

- Laravel 側は `config/logging.php` の `slack` チャンネル（`App\Logging\SlackAlertLoggerFactory`）。`LOG_SLACK_WEBHOOK_URL` が未設定なら何もしない。
- `slack` チャンネルは `LOG_CHANNEL=stack` + `LOG_STACK=stderr,slack` でしか効かない。`LOG_CHANNEL=stderr` だと `LOG_STACK` は評価されない。
- Slack へ送るメッセージは `SlackMessageSanitizer` で SQL と接続情報を落としている。これは Slack ハンドラ側のプロセッサであって Logger のプロセッサではない。Logger に付けると stderr / CloudWatch Logs の本文まで切り詰められる。
