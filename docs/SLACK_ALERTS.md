# Slack障害通知

障害に気づくための通知を、1つのSlack Incoming Webhookへまとめて送る構成の設定手順と運用メモです。通知経路は次の4つで、すべて同じWebhook URLを使います。

| 経路 | 送信元 | 設定先 | 有効になる条件 |
| --- | --- | --- | --- |
| Laravel | アプリの `error` 以上のログ（`slack` ログチャンネル） | Renderの環境変数 / ECSはCDKスタック | `LOG_STACK` に `slack` が含まれ、`LOG_SLACK_WEBHOOK_URL` が設定されている（ECSでは `-c enableSlackAlerts=true`） |
| AWS | CloudWatchアラーム → SNS → Slack | `cdk deploy` のcontextとパラメータ | `-c enableSlackAlerts=true` でデプロイ済み（自動デプロイで維持するにはリポジトリ変数 `SLACK_ALERTS_ENABLED` も `true`。AWS本番は課金停止中） |
| GitHub Actions | CIワークフローの失敗 | リポジトリシークレット | シークレット `SLACK_WEBHOOK_URL` が登録されている |
| Next.js | サーバー側の `onRequestError` | Renderの環境変数 / ECSはCDKスタック | 環境変数 `SLACK_WEBHOOK_URL` が設定されている（ECSでは `-c enableSlackAlerts=true`） |

AWS本番環境は課金を停止しているため、GitHub Actionsのデプロイジョブはリポジトリ変数 `AWS_DEPLOY_ENABLED` が `true` のときだけ動きます。AWS経路は設定を用意してあるだけで、この変数を `true` にして再デプロイするまで稼働しません（[AWSデプロイ記録](AWS_DEPLOYMENT_DONE.md)）。

## 1. Slack AppとIncoming Webhook URLの発行

1. <https://api.slack.com/apps> を開き、**Create New App** → **From scratch** を選ぶ。
2. App名（例: `PLAGINE Alerts`）と通知を受けるワークスペースを指定して作成する。
3. 左メニューの **Incoming Webhooks** を開き、**Activate Incoming Webhooks** をオンにする。
4. **Add New Webhook to Workspace** を押し、通知先チャンネル（例: `#plagine-alerts`）を選んで許可する。
5. 表示された `https://hooks.slack.com/services/...` 形式のURLを控える。このURLはパスワードと同じ扱いの秘密情報で、URLを知っていれば誰でもチャンネルへ投稿できる。

Webhook URLはGitやソースコード、Next.jsのbuild成果物へ含めないでください。保管先は各サービスの秘密情報の仕組み（Renderの環境変数、GitHubのシークレット、CloudFormationのパラメータ）だけにします。チャンネルを変えたいときは新しいWebhookを発行してURLを差し替えます。

## 2. 経路ごとの設定先

### Laravel（Render）

`render.yaml` では次のように定義してあります。

- `LOG_CHANNEL=stack`、`LOG_STACK=stderr,slack` — 従来どおりstderr（Renderのログ）へ出しつつ、Slackへも送る。
- `LOG_SLACK_LEVEL=error` — `error` 以上だけ送る。
- `LOG_SLACK_WEBHOOK_URL` — `sync: false` なので値は入っていない。Renderのダッシュボードで対象サービスの **Environment** を開き、この変数へWebhook URLを手入力する。
- `SLACK_WEBHOOK_URL` — 同じく `sync: false`。Next.js側が読む変数で、同じURLを入れる。

Slackへの表示名とアイコンは `LOG_SLACK_USERNAME`（既定 `Laravel Log`）と `LOG_SLACK_EMOJI`（既定 `:boom:`）で変えられます。どちらも `render.yaml` には書いておらず、変えたいときだけ設定します。

これらの値はコンテナ起動時の `php artisan config:cache`（`scripts/render-next/00-start.sh`）で設定キャッシュに取り込まれるため、変更が効くのは再デプロイまたは再起動の後です。Renderは環境変数を保存すると自動で再デプロイするので、ダッシュボードでの変更はそのまま反映されます。

`LOG_SLACK_WEBHOOK_URL` が空のあいだ、`slack` チャンネルは何もしないハンドラとして動きます。そのため `LOG_STACK` に `slack` を入れた状態でURL未設定のままデプロイしても、ログ出力が失敗することはありません。

ローカル開発では `.env.example` のとおり `LOG_STACK=single`、`LOG_SLACK_WEBHOOK_URL=`（空）のままにして、Slackへ送らない状態を保ちます。

### Laravel / Next.js（AWS ECS）

ECSでは、`LOG_STACK` と `LOG_SLACK_LEVEL` はコンテナの環境変数として、`LOG_SLACK_WEBHOOK_URL` と `SLACK_WEBHOOK_URL` はSecrets Manager由来のシークレットとして、いずれもCDKスタック（`infra/lib/guide-stack.ts`）から渡されます。Renderのようにダッシュボードで個別に足す運用ではなく、4つすべてがスタックの管理下にあります。

LaravelとNext.jsの通知をECSで入れるスイッチは `-c enableSlackAlerts=true` と `GuideStack:SlackWebhookUrl` パラメータです。切るときは `-c enableSlackAlerts=false` でデプロイし直します。このときWebhookのシークレットがタスク定義へ付かないため、両経路とも通知を送らなくなります（CloudWatchアラーム → SNS → Slackの経路も同じcontextで切り替わります）。

```sh
cd infra
npx cdk deploy GuideStack \
  -c enableSlackAlerts=true \
  --parameters GuideStack:SlackWebhookUrl=https://hooks.slack.com/services/...
```

Webhook URLはシェル履歴にも残るため、入力後に履歴を消すか、履歴へ残らない方法で渡してください。

`enableSlackAlerts` は合成時に効くCDK contextなので、付けずに `cdk deploy` するとSlack関連のリソースが削除されます。`main` への push で走る自動デプロイはリポジトリ変数 `SLACK_ALERTS_ENABLED` の値を `-c enableSlackAlerts=<値>` として渡すため（未設定なら `false`）、手動で有効にしたあとはこの変数も `true` にしてください。この変数に入れられるのは小文字の `true` と `false` だけです。`True` や `TRUE` のような別の綴りだと、CDKが `CDK context enableSlackAlerts must be true or false` で例外を投げるため、Slack通知が静かに止まるのではなくデプロイ自体が失敗します（`infra/lib/guide-stack.ts` の `booleanContext`）。`SlackWebhookUrl` パラメータは `--parameters` を渡さなければCloudFormationが前回値を引き継ぐので、ワークフローからは渡していません。

このECS側の配線はCDKの変更として本ブランチで入ります。あわせて、AWSへのデプロイ自体はリポジトリ変数 `AWS_DEPLOY_ENABLED` が `true` になるまで動かないため（`.github/workflows/deploy.yml`）、AWS経路は今日の時点では設定が用意されているだけで稼働していません。

### GitHub Actions

リポジトリの **Settings** → **Secrets and variables** → **Actions** → **Secrets** で、`SLACK_WEBHOOK_URL` という名前のリポジトリシークレットへWebhook URLを登録します。シークレットが未登録のあいだはSlackへ通知されません。

## 3. 通知されるもの・されないもの

| 経路 | 通知する | 通知しない |
| --- | --- | --- |
| Laravel | `error` 以上のログ（未捕捉例外、`report()`、明示的な `Log::error` 以上） | `warning` 以下、404、バリデーションエラー（422） |
| AWS | 既存CloudWatchアラーム10種のALARM遷移とOK復帰（[アラーム一覧](AWS_DEPLOYMENT_DONE.md#監視)） | WAFのブロック個別イベント、CloudTrail |
| GitHub Actions | `push` イベントでの `validate` / `runtime-image` / `infra-test` / `deploy` のいずれかの失敗（`deploy` は `main` かつ `AWS_DEPLOY_ENABLED=true` のときだけ実行されるため、現状は残り3つが対象） | `pull_request` の失敗（PR画面で見えるため）、キャンセルされた実行（ジョブの結果が `cancelled` で `failure` ではないため）、スキップされたジョブ |
| Next.js | `onRequestError` が受け取るサーバー側エラー | クライアント側の `error.tsx` 到達 |

通知量を抑えるため、利用者の操作ミスで日常的に起きるもの（404、入力エラー）と、PR画面やGitHub上で確認できるものは送りません。

CloudWatchアラームは10種すべてに復旧時の通知（OKアクション）が付いているため（`infra/lib/guide-stack.ts` の `addOkAction` ループ）、Slackへ送るLambdaにも障害発生と復旧の両方が届きます。これはSNSのメール通知と同じ条件で、既存の宛先や閾値は変えていません。

## 4. 重複抑制と既知の挙動

Laravel経路では、`error` 以上について、同じログレベルと同じメッセージの連続発生を300秒だけ抑制します。抑制の判定レベルは `error` に固定されているため（`app/Logging/SlackAlertLoggerFactory.php`）、`LOG_SLACK_LEVEL` を `warning` などへ下げた場合、`warning` の通知は抑制されずに毎回届きます。抑制の記録は各コンテナの `storage/logs/slack-dedup.log` に書かれるため、抑制はコンテナ単位です。ECSタスクやRenderのインスタンスが複数あれば、同じエラーがインスタンスごとに1回ずつ届きます。

既知の運用上の性質として、重複抑制を行う `DeduplicationHandler` は送信を試みる前に記録を書きます。そのためSlackへ届かなかった通知も、次の300秒は「すでに通知済み」として抑制されます。Slack側の障害やWebhook URLの失効中に起きたエラーは、Slackには現れないことがあります。同じエラーはstderr（Renderのログ、CloudWatch Logs）にはそのまま残るので、Slackに通知が来ないときや障害調査のときはログ本体を確認してください。

Slackへの送信失敗はアプリの処理へ伝播しません。通知の送信が失敗しても、リクエストやジョブは通常どおり続きます。

## 5. 通知を止める方法

| 経路 | 止め方 |
| --- | --- |
| Laravel（Render） | 環境変数 `LOG_STACK` から `slack` を外す（`stderr` だけにする）。`LOG_SLACK_WEBHOOK_URL` を空にしても止まる。 |
| AWS（CloudWatch・ECSのLaravel/Next.js） | `-c enableSlackAlerts=false` を付けて `cdk deploy` し直す。Webhookのシークレットがタスク定義へ付かなくなる。あわせてリポジトリ変数 `SLACK_ALERTS_ENABLED` も `false` にする（または削除する）。`true` のままだと、次の自動デプロイが `SlackWebhookUrl` パラメータの値を持たないまま作り直そうとして失敗する。 |
| GitHub Actions | リポジトリシークレット `SLACK_WEBHOOK_URL` を削除する。 |
| Next.js（Render） | 環境変数 `SLACK_WEBHOOK_URL` を空にする、または削除する。 |

Renderの環境変数は再デプロイ（保存時に自動で走る）後に効きます。設定キャッシュを作り直すまで旧値で動くため、保存直後ではなくデプロイ完了を確認してください。即座に止めたい場合は、Slack App側でWebhookを削除するのが確実です。

Webhook URLが漏れた場合は、Slack Appの **Incoming Webhooks** 画面で該当Webhookを削除し、新しいURLを発行して各設定先を差し替えてください。URLを無効化すれば、古いURLへの投稿はすべて失敗します。
