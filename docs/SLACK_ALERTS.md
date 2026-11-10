# Slack障害通知

障害に気づくための通知を、1つのSlack Incoming Webhookへまとめて送る構成の設定手順と運用メモです。通知経路は次の4つで、すべて同じWebhook URLを使います。

| 経路 | 送信元 | 設定先 | 有効になる条件 |
| --- | --- | --- | --- |
| Laravel | アプリの `error` 以上のログ（`slack` ログチャンネル） | Renderの環境変数 / ECSのタスク定義 | `LOG_STACK` に `slack` が含まれ、`LOG_SLACK_WEBHOOK_URL` が設定されている |
| AWS | CloudWatchアラーム → SNS → Slack | `cdk deploy` のcontextとパラメータ | `-c enableSlackAlerts=true` でデプロイ済み（AWS本番は課金停止中） |
| GitHub Actions | CIワークフローの失敗 | リポジトリシークレット | シークレット `SLACK_WEBHOOK_URL` が登録されている |
| Next.js | サーバー側の `onRequestError` | Renderの環境変数 / ECSのタスク定義 | 環境変数 `SLACK_WEBHOOK_URL` が設定されている |

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

`LOG_SLACK_WEBHOOK_URL` が空のあいだ、`slack` チャンネルは何もしないハンドラとして動きます。そのため `LOG_STACK` に `slack` を入れた状態でURL未設定のままデプロイしても、ログ出力が失敗することはありません。

ローカル開発では `.env.example` のとおり `LOG_STACK=single`、`LOG_SLACK_WEBHOOK_URL=`（空）のままにして、Slackへ送らない状態を保ちます。

### Laravel / Next.js（AWS ECS）

AWS側では同じ変数名をタスク定義に渡します。CDKスタックのデプロイ時に、Slack通知を有効化するcontextとWebhook URLのパラメータを指定します。

```sh
cd infra
npx cdk deploy GuideStack \
  -c enableSlackAlerts=true \
  --parameters GuideStack:SlackWebhookUrl=https://hooks.slack.com/services/...
```

Webhook URLはシェル履歴にも残るため、入力後に履歴を消すか、履歴へ残らない方法で渡してください。

### GitHub Actions

リポジトリの **Settings** → **Secrets and variables** → **Actions** → **Secrets** で、`SLACK_WEBHOOK_URL` という名前のリポジトリシークレットへWebhook URLを登録します。シークレットが未登録のあいだはSlackへ通知されません。

## 3. 通知されるもの・されないもの

| 経路 | 通知する | 通知しない |
| --- | --- | --- |
| Laravel | `error` 以上のログ（未捕捉例外、`report()`、明示的な `Log::error` 以上） | `warning` 以下、404、バリデーションエラー（422） |
| AWS | 既存CloudWatchアラームのALARM遷移、および可用性系3種のOK復帰 | WAFのブロック個別イベント、CloudTrail |
| GitHub Actions | `push` イベントでの `validate` / `runtime-image` / `infra-test` / `deploy` のいずれかの失敗 | `pull_request` の失敗（PR画面で見えるため） |
| Next.js | `onRequestError` が受け取るサーバー側エラー | クライアント側の `error.tsx` 到達 |

通知量を抑えるため、利用者の操作ミスで日常的に起きるもの（404、入力エラー）と、PR画面やGitHub上で確認できるものは送りません。CloudWatchアラームはSNSトピック経由で既にメール通知もしており、そちらの宛先と条件は変えていません。

## 4. 重複抑制と既知の挙動

Laravel経路では、同じログレベルと同じメッセージの連続発生を300秒だけ抑制します。抑制の記録は各コンテナの `storage/logs/slack-dedup.log` に書かれるため、抑制はコンテナ単位です。ECSタスクやRenderのインスタンスが複数あれば、同じエラーがインスタンスごとに1回ずつ届きます。

既知の運用上の性質として、重複抑制を行う `DeduplicationHandler` は送信を試みる前に記録を書きます。そのためSlackへ届かなかった通知も、次の300秒は「すでに通知済み」として抑制されます。Slack側の障害やWebhook URLの失効中に起きたエラーは、Slackには現れないことがあります。同じエラーはstderr（Renderのログ、CloudWatch Logs）にはそのまま残るので、Slackに通知が来ないときや障害調査のときはログ本体を確認してください。

Slackへの送信失敗はアプリの処理へ伝播しません。通知の送信が失敗しても、リクエストやジョブは通常どおり続きます。

## 5. 通知を止める方法

| 経路 | 止め方 |
| --- | --- |
| Laravel（Render） | 環境変数 `LOG_STACK` から `slack` を外す（`stderr` だけにする）。`LOG_SLACK_WEBHOOK_URL` を空にしても止まる。 |
| AWS | `-c enableSlackAlerts=false` を付けて `cdk deploy` し直す。 |
| GitHub Actions | リポジトリシークレット `SLACK_WEBHOOK_URL` を削除する。 |
| Next.js | 環境変数 `SLACK_WEBHOOK_URL` を空にする、または削除する。 |

Webhook URLが漏れた場合は、Slack Appの **Incoming Webhooks** 画面で該当Webhookを削除し、新しいURLを発行して各設定先を差し替えてください。URLを無効化すれば、古いURLへの投稿はすべて失敗します。
