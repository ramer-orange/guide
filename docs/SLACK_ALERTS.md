# Slack障害通知

障害に気づくための通知を、1つのSlack Incoming Webhookへまとめて送る構成の設定手順と運用メモです。通知経路は次の4つで、すべて同じWebhook URLを使います。

| 経路 | 送信元 | 設定先 | 有効になる条件 |
| --- | --- | --- | --- |
| Laravel | アプリの `error` 以上のログ（`slack` ログチャンネル） | Renderの環境変数 / ECSはCDKスタック | `LOG_STACK` に `slack` が含まれ、`LOG_SLACK_WEBHOOK_URL` が設定されている（ECSでは `-c enableSlackAlerts=true`） |
| AWS | CloudWatchアラーム → SNS → Slack | `cdk deploy` のcontextとパラメータ | `-c enableSlackAlerts=true` でデプロイ済み（自動デプロイで維持するにはリポジトリ変数 `SLACK_ALERTS_ENABLED` も `true`。AWS本番は課金停止中） |
| GitHub Actions | CI（`deploy.yml`）と依存監査（`dependency-security.yml`）の失敗 | リポジトリシークレット | シークレット `SLACK_WEBHOOK_URL` が登録されている |
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
| GitHub Actions（CI / `deploy.yml`） | **`main` への push** での `validate` / `runtime-image` / `infra-test` / `deploy` のいずれかの失敗（`deploy` は `AWS_DEPLOY_ENABLED=true` のときだけ実行されるため、現状は残り3つが対象） | `main` 以外のブランチへの push、`pull_request` の失敗、キャンセルされた実行（ジョブの結果が `cancelled` で `failure` ではないため）、スキップされたジョブ |
| GitHub Actions（依存監査 / `dependency-security.yml`） | 週次cron（毎週月曜 01:17 UTC）と `main` / `master` への push での `npm-audit` / `composer-audit` の失敗 | `pull_request` の失敗、`workflow_dispatch`（手動実行）の失敗 |
| Next.js | `onRequestError` が受け取るサーバー側エラー | クライアント側の `error.tsx` 到達 |

通知量を抑えるため、利用者の操作ミスで日常的に起きるもの（404、入力エラー）と、PR画面やGitHub上で確認できるものは送りません。

GitHub Actionsの通知を `main`（依存監査は `main` / `master` と週次cron）に限っているのは意図的な判断です。`pull_request` の失敗はPR画面で見えるため除外していますが、PRブランチへのpushは `push` イベントも起こすので、ブランチを絞らないと同じコミットの失敗が結局通知され、障害用チャンネルが日常の開発CI失敗で埋まります。feature ブランチのCI失敗は、通知ではなくGitHubのActions画面（およびPR画面）で確認してください。逆に週次の依存監査は誰も結果を見ていないため、新しいCVEで赤くなったことに気づく手段がこの通知だけになります。そのためcron実行は通知対象に含めています。

### Laravel経路でSlackへ載る内容

Slackへ送るのは、ログレベルとメッセージ本文だけです。例外の `context` と `extra` は送りません（`SlackWebhookHandler` の `includeContextAndExtra` を `false` にしている）。

ただし `context` を送らないだけでは不十分なので、メッセージ本文も `App\Logging\SlackMessageSanitizer` で次のように加工しています。

- 本文中の `(Connection: ..., Host: ..., Database: ..., SQL: ...)` を落として `(SQL詳細はログ参照)` に置き換える（末尾に限らず、後ろに文字が足されていても落とす）。`Illuminate\Database\QueryException` は例外メッセージ自体にこの部分を組み立てるため、何もしないと**バインド済みの値を含むSQL全文**と接続先（ホスト・ポート・DB名）がSlackへ流れます。たとえば `select * from password_reset_tokens where token = ?` がタイムアウトすると、実際のトークン値がチャンネルに出ます。
- 残った本文を300文字で切る（超えた分は `…（以下省略）`）。

この加工は `{placeholder}` の展開（`PsrLogMessageProcessor`）の**後**に走るので、`Log::error('クエリ失敗: {reason}', ['reason' => $e->getMessage()])` のように開発者が本文へ埋め込んだ値も対象になります。

**加工の対象はSlackへ送るレコードだけです。** サニタイザはLoggerではなくSlackハンドラに積んでいるため（`SlackAlertLoggerFactory`）、`LOG_STACK=stderr,slack` でまとめられても stderr 側のレコードは一切変わりません。下の「調査はログ本体で」という手順はこれが前提です。Laravelの `LogManager` は stack チャンネルへ全チャンネルの**プロセッサ**をまとめるので、Logger側に積むと stderr のログまで切り詰められます。

したがってSlackの通知は「どのレベルのエラーが、どんな文面で起きたか」までで、SQL・バインド値・接続先・スタックトレースは含みません。調査にはstderr（Renderのログ、CloudWatch Logs）の本体を使ってください。

ただし、守れるのは上の2つの定型パターンだけです。次のものは今もSlackへ届きます。運用上の前提として把握しておいてください。

- 開発者が `Log::error()` の本文に直接書いた個人情報やトークン（300文字以内なら）。
- `QueryException` の内側の `PDOException` 本文そのもの。たとえば `FATAL: password authentication failed for user "guide"` や、接続エラーに含まれるDSN。これらは `(Connection: ...)` の括弧の外にあるため、この加工では落ちません。一般的な秘匿値マスキングの設計が必要で、本ブランチの範囲外として残してあります。

CloudWatchアラームは10種すべてに復旧時の通知（OKアクション）が付いているため（`infra/lib/guide-stack.ts` の `addOkAction` ループ）、Slackへ送るLambdaにも障害発生と復旧の両方が届きます。これはSNSのメール通知と同じ条件で、既存の宛先や閾値は変えていません。

## 4. 重複抑制と既知の挙動

Laravel経路の抑制は2段構えで、どちらも「同じログレベル」と「同じメッセージ（1行目）」の一致で判定します。

1. **同一プロセス内（`App\Logging\OncePerProcessHandler`）** — 1リクエスト（またはワーカープロセス）の中で同じエラーを何回ログしても、Slackへ送るのは1回だけです。Monologの `DeduplicationHandler` は送るか送らないかを1つの真偽値で決め、送ると決めたらバッファ全体を内側のハンドラへ渡すため、これを挟まないとループの中で起きたN件がN回Slackへ送られます。記憶しているのは送信済みの印だけで、200件に達したら捨てて作り直します。
2. **プロセスをまたぐ抑制（`DeduplicationHandler`、300秒）** — 1度Slackへ送ったメッセージは、次の300秒のあいだ、別のリクエストから同じものが来ても送りません。記録は各コンテナの `storage/logs/slack-dedup.log` に書かれるため、抑制はコンテナ単位です。ECSタスクやRenderのインスタンスが複数あれば、同じエラーがインスタンスごとに1回ずつ届きます。保存先は環境変数 `LOG_SLACK_DEDUP_STORE` で変えられます。

抑制の判定レベルは `error` に固定されているため（`app/Logging/SlackAlertLoggerFactory.php`）、`LOG_SLACK_LEVEL` を `warning` などへ下げた場合、`warning` の通知は（プロセス内の1回化は効きますが）300秒の抑制には乗らず、プロセスごとに届きます。

判定はメッセージの完全一致なので、本文にリクエストIDやユーザーIDのような毎回変わる値が入っていると、同じ障害でも別メッセージとして扱われて抑制が効きません。DBエラーのようにSQLのバインド値が本文に入るものも同じで、§3のサニタイズは抑制の判定より**後**（Slackハンドラの書き込み直前）に走るため、抑制の当たりやすさは改善しません。抑制は加工前の本文で判定されます。

既知の運用上の性質として、どちらの抑制も送信を試みる前に「送った」と記録します。そのためSlackへ届かなかった通知も、次の300秒は「すでに通知済み」として抑制されます。Slack側の障害やWebhook URLの失効中に起きたエラーは、Slackには現れないことがあります。同じエラーはstderr（Renderのログ、CloudWatch Logs）にはそのまま残るので、Slackに通知が来ないときや障害調査のときはログ本体を確認してください。

Next.js経路（`frontend/src/lib/alerts/slack.ts`）にも同じ300秒の抑制があります。`onRequestError` はリクエストごとに呼ばれるため、壊れたルート1つとクローラーだけでチャンネルが埋まります。抑制の鍵は「タイトル + 詳細（エラー名とメッセージ）」で、パスは鍵に含めません。同じエラーが複数のパスで起きている場合、通知に出るのは最初の1件だけです。記憶はプロセス内のMapなので、インスタンス単位・再起動でリセットされ、上限は200件です。

Slackへの送信失敗はアプリの処理へ伝播しません。通知の送信が失敗しても、リクエストやジョブは通常どおり続きます。Laravel・Next.jsのどちらも、送信のタイムアウトは5秒（Laravelは接続2秒）で、失敗しても再試行しません。

## 5. 通知を止める方法

| 経路 | 止め方 |
| --- | --- |
| Laravel（Render） | 環境変数 `LOG_STACK` から `slack` を外す（`stderr` だけにする）。`LOG_SLACK_WEBHOOK_URL` を空にしても止まる。 |
| AWS（CloudWatch・ECSのLaravel/Next.js） | `-c enableSlackAlerts=false` を付けて `cdk deploy` し直す。Webhookのシークレットがタスク定義へ付かなくなる。あわせてリポジトリ変数 `SLACK_ALERTS_ENABLED` も `false` にする（または削除する）。`true` のままだと、次の自動デプロイが `SlackWebhookUrl` パラメータの値を持たないまま作り直そうとして失敗する。 |
| GitHub Actions | リポジトリシークレット `SLACK_WEBHOOK_URL` を削除する。 |
| Next.js（Render） | 環境変数 `SLACK_WEBHOOK_URL` を空にする、または削除する。 |

Renderの環境変数は再デプロイ（保存時に自動で走る）後に効きます。設定キャッシュを作り直すまで旧値で動くため、保存直後ではなくデプロイ完了を確認してください。即座に止めたい場合は、Slack App側でWebhookを削除するのが確実です。

Webhook URLが漏れた場合は、Slack Appの **Incoming Webhooks** 画面で該当Webhookを削除し、新しいURLを発行して各設定先を差し替えてください。URLを無効化すれば、古いURLへの投稿はすべて失敗します。
