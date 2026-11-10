# 障害エラーのSlack通知 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** アプリ・インフラ・CI の障害を、1本の Slack Incoming Webhook に集約して通知する。

**Architecture:** 通知経路を4つ用意し、すべて同じ Webhook URL を指す。(1) Laravel は Monolog のカスタムチャンネルで `error` 以上をSlackへ。(2) AWS の CloudWatch アラームは既存 SNS Topic に Lambda を購読させてSlackへ。(3) GitHub Actions は失敗時ジョブから curl でSlackへ。(4) Next.js はサーバー側エラーを `instrumentation.ts` の `onRequestError` からSlackへ。どの経路も Webhook URL 未設定なら完全に無効化され、Slack 側の障害でアプリのリクエストが失敗しない。

**Tech Stack:** Laravel 12 / Monolog 3（既存）、Pest 3、AWS CDK v2（aws-cdk-lib 2.274.0）、Lambda nodejs22.x（依存パッケージなし・グローバル `fetch`）、Next.js 16 / vitest 4、GitHub Actions

**Spec:** この計画書内の「設計判断（Spec 相当）」セクション。ブレスト（要件ヒアリング）をユーザーの指示でスキップしたため、以下の設計判断はすべて私の判断であり、ユーザーが承認したものではない。レビュー時に覆してよい。

---

## 設計判断（Spec 相当）

### 意図

本番で障害が起きたことに人間が気づくまでの時間を短くする。現状、CloudWatch アラームの通知先はメール1本だけで、アプリ例外・CI失敗・デプロイ失敗はどこにも通知されない。

### 通知する「障害系エラー」の定義

| 層 | 通知する | 通知しない |
|---|---|---|
| Laravel | `error` 以上のログ（未捕捉例外、`report()`、明示的な `Log::error` 以上） | `warning` 以下、404、バリデーションエラー（422） |
| AWS | 既存 CloudWatch アラーム10種の ALARM 遷移と OK 復帰（`guide-stack.ts` の既存ループが全アラームに OK アクションを付けている） | WAF のブロック個別イベント、CloudTrail |
| GitHub Actions | `push` イベントでの `validate` / `runtime-image` / `infra-test` / `deploy` のいずれかの失敗 | `pull_request` の失敗（PR画面で見えるため） |
| Next.js | `onRequestError` が受け取るサーバー側エラー | クライアント側の `error.tsx` 到達（ブラウザからの直接送信はしない） |

### Goals

- Webhook URL を1本だけ運用し、4経路すべてで同じチャンネルに届く
- URL 未設定の環境（ローカル開発・CI の検証ジョブ・テスト）では完全に無効で、挙動が一切変わらない
- Slack が落ちていても、タイムアウトしていても、アプリのリクエストは成功し続ける
- 既存の CDK テストとデプロイ手順を壊さない

### Non-goals

- Sentry / Datadog などの外部 APM 導入（Webhook 1本で足りる規模のため）
- クライアント側 JS エラーの収集
- Slack からの操作（アラーム停止・再デプロイなどのインタラクティブ機能）
- メール通知の廃止（Slack と併存させる）
- オンコール・エスカレーション（PagerDuty 等）

### Anti-goals（これが起きたら失敗）

- Slack 通知の追加が原因でユーザーのリクエストが 500 になる
- エラー1件につき大量のメッセージが流れてチャンネルが使えなくなる
- 例外の context 経由で個人情報・トークン・DB 接続情報が Slack に流れる
- Webhook URL が Git・CloudFormation テンプレート・タスク定義の平文に残る

### 制約と、その中で選んだ手段

- **新しい依存パッケージを追加しない。** `composer audit` / `npm audit` を CI で回しているため、監査対象を増やしたくない。→ Monolog 同梱の `SlackWebhookHandler`、Lambda は依存なしの `.mjs` + グローバル `fetch`、Next.js は `fetch` をそのまま使う。
- **AWS は課金停止中（`AWS_DEPLOY_ENABLED` が false）で、本番公開も一時停止中。** 実機確認ができない。→ CDK 側は context フラグ `enableSlackAlerts` で既定無効にし、合成結果をテストで検証する。Lambda のハンドラは単体テストで検証する。
- **Webhook URL の置き場所。** ECS は Secrets Manager 経由（`ecs.Secret.fromSecretsManager`、ランタイム SDK 不要）、Lambda は同じ `NoEcho` パラメータを環境変数として受け取る（nodejs22.x に AWS SDK が同梱される保証がないため、Secrets Manager を読まない）。Lambda 環境変数は保管時に暗号化されるが、`lambda:GetFunctionConfiguration` 権限があれば閲覧できる。これは「ランタイム依存をゼロにする」ことを優先した意図的な妥協。
- **多重通知の抑制。** Monolog の `DeduplicationHandler`（同一レベル・同一メッセージを300秒間で1回）で押さえる。ECS タスクごとに独立したストアになるので、タスク数ぶんは重複しうる。
- **Slack 障害の隔離。** `WhatFailureGroupHandler` で Slack ハンドラの例外を飲む。
- **情報漏れの防止。** `SlackWebhookHandler` の `includeContextAndExtra` を `false` にし、メッセージ本文とレベルだけを送る。詳細は CloudWatch Logs / Render のログで見る。

### 実装者に委ねる点

- Slack メッセージの文面・絵文字・Block Kit の細かいレイアウト（テストが要求する項目が入っていればよい）
- README / docs の日本語の言い回し

---

## Global Constraints

- PHP は `~8.2.35 || ~8.3.36 || ~8.4.27 || ~8.5.12`（`composer.json`）。CI は 8.3.36。
- Node は `^22.23.4 || ^24.22.1 || ^26.12.1`。ローカル・CI・Docker はいずれも 22.23.4。
- `composer.json` / `frontend/package.json` / `infra/package.json` に**新規パッケージを追加しない**。
- Webhook URL をコード・`render.yaml`・`.env.example`・CloudFormation テンプレートの平文に書かない。`.env.example` には空の `LOG_SLACK_WEBHOOK_URL=` だけを置く。
- 新規コードのコメントとドキュメントは日本語（既存ファイルに合わせる）。
- CDK の新規リソースは context `enableSlackAlerts=true` のときだけ作る。既定（false）で `npx tsc --noEmit` と `npm test` が既存のまま通ること。
- Laravel の Slack チャンネル名は既存の `slack` を維持する（`config/logging.php` のキーを変えない）。
- Slack 送信のタイムアウトは全経路 5 秒。

## Review Focus

1. `LOG_SLACK_WEBHOOK_URL` が未設定のまま `LOG_STACK` に `slack` が入った環境で、`Log::error()` が例外を投げないか（→ Task 1 Step 1）
2. Slack が接続拒否・タイムアウト・5xx を返したとき、`Log::error()` の呼び出し元に例外が伝播しないか（→ Task 1 Step 6）
3. 例外の `context` に入れた機密値（トークン・メールアドレス）が Slack ペイロードに載らないか（→ Task 1 Step 9）
4. 同一エラーが短時間に大量発生したとき、Slack へ送る回数が抑制されるか（→ Task 1 Step 9）
5. CloudWatch アラームの通知が JSON でない素のテキストで届いたとき、Lambda が落ちずに本文をそのまま転送するか（→ Task 3 Step 3）

---

## File Structure

```
app/Logging/SlackAlertLoggerFactory.php     # 新規: Monolog カスタムチャンネルの組み立て
config/logging.php                          # 変更: slack チャンネルを custom ドライバへ
tests/Feature/SlackAlertChannelTest.php     # 新規: チャンネルの構成と例外隔離

render.yaml                                 # 変更: LOG_STACK / LOG_SLACK_* / SLACK_WEBHOOK_URL
.env.example                                # 変更: Slack 関連の空キー
docs/SLACK_ALERTS.md                        # 新規: 運用手順（Webhook 発行・4経路の設定・停止方法）
README.md                                   # 変更: 目次と「障害通知」節へのリンク

infra/lambda/slack-alarm-notifier/index.mjs # 新規: SNS → Slack 変換（依存なし）
infra/lib/guide-stack.ts                    # 変更: Slack 通知経路と ECS 環境変数
infra/test/slack-alarm-notifier.test.ts     # 新規: ハンドラ単体テスト
infra/test/guide-stack.test.ts              # 変更: 合成結果の検証

.github/workflows/deploy.yml                # 変更: 失敗通知ジョブ

frontend/src/lib/alerts/slack.ts            # 新規: 送信関数
frontend/src/lib/alerts/slack.test.ts       # 新規
frontend/src/instrumentation.ts             # 新規: onRequestError フック
frontend/.env.example                       # 変更: SLACK_WEBHOOK_URL
```

Task 1 → Task 2 は依存する（Task 2 は Task 1 のチャンネルを本番設定に組み込む）。Task 3・Task 4・Task 5 は互いに独立で、Task 1 にも依存しない。

---

### Task 1: Laravel の Slack ログチャンネル

既存の `slack` チャンネル（`driver => 'slack'`）は URL が null だと例外になるため、カスタムドライバに差し替える。

**Files:**
- Create: `app/Logging/SlackAlertLoggerFactory.php`
- Modify: `config/logging.php:76-83`（`slack` チャンネル定義）
- Test: `tests/Feature/SlackAlertChannelTest.php`

**Interfaces:**
- Consumes: なし
- Produces: `App\Logging\SlackAlertLoggerFactory::__invoke(array $config): \Monolog\Logger`。`config/logging.php` の `slack` チャンネルが `'driver' => 'custom', 'via' => SlackAlertLoggerFactory::class` としてこれを使う。Task 2 は環境変数 `LOG_SLACK_WEBHOOK_URL` / `LOG_SLACK_LEVEL` / `LOG_SLACK_USERNAME` / `LOG_SLACK_EMOJI` を設定する。

- [ ] **Step 1: URL 未設定時のテストを書く**

```php
<?php

use App\Logging\SlackAlertLoggerFactory;
use Monolog\Handler\NullHandler;
use Monolog\Handler\WhatFailureGroupHandler;
use Monolog\Logger;
use Illuminate\Support\Facades\Log;

it('webhook URL が無いときは何もしないハンドラだけを持つ', function () {
    $logger = (new SlackAlertLoggerFactory())(['url' => null, 'level' => 'error']);

    expect($logger)->toBeInstanceOf(Logger::class);
    expect($logger->getHandlers())->toHaveCount(1);
    expect($logger->getHandlers()[0])->toBeInstanceOf(NullHandler::class);
});

it('webhook URL が空文字でも何もしないハンドラだけを持つ', function () {
    $logger = (new SlackAlertLoggerFactory())(['url' => '', 'level' => 'error']);

    expect($logger->getHandlers()[0])->toBeInstanceOf(NullHandler::class);
});

it('URL 未設定の slack チャンネルへ error を書いても例外にならない', function () {
    config(['logging.channels.slack.url' => null]);

    Log::channel('slack')->error('boom');

    expect(true)->toBeTrue();
});
```

- [ ] **Step 2: テストが失敗することを確認する**

Run: `php artisan test tests/Feature/SlackAlertChannelTest.php`
Expected: FAIL（`App\Logging\SlackAlertLoggerFactory` が存在しない）

- [ ] **Step 3: `SlackAlertLoggerFactory::__invoke(array $config): \Monolog\Logger` を `app/Logging/SlackAlertLoggerFactory.php` に実装する**

`$config['url']` が空なら `new Logger('slack', [new NullHandler()])` を返す。空でない場合は `SlackWebhookHandler` を作り、`DeduplicationHandler`（`$time = 300`、ストアは `storage_path('logs/slack-dedup.log')`）で包み、さらに `WhatFailureGroupHandler` で包んだものを唯一のハンドラにする。`SlackWebhookHandler` の引数は URL、`$channel = null`、`$username = $config['username'] ?? 'Laravel Log'`、`$useAttachment = true`、`$iconEmoji = $config['emoji'] ?? ':boom:'`、`$useShortAttachment = false`、`$includeContextAndExtra = false`、`$level = $config['level'] ?? 'error'`。`SlackWebhookHandler` が持つ `Slack\SlackRecord` 経由の送信タイムアウトは設定できないため、ここでは触らない（タイムアウトは Task 3・5 の自前送信にのみ適用する）。

- [ ] **Step 4: 設定を差し替える**

`config/logging.php` の `slack` チャンネルを次の形にする。`driver` を `custom`、`via` を `App\Logging\SlackAlertLoggerFactory::class` にし、`level` の既定値を `critical` から `error` に変え、`LOG_LEVEL` ではなく専用の `LOG_SLACK_LEVEL` を読む。`url` / `username` / `emoji` の env キーは既存のまま。

- [ ] **Step 5: テストが通ることを確認する**

Run: `php artisan test tests/Feature/SlackAlertChannelTest.php`
Expected: PASS（3件）

- [ ] **Step 6: Slack 障害を隔離するテストを書く**

```php
it('Slack への送信が失敗してもログ呼び出し元に例外が出ない', function () {
    // 127.0.0.1:1 は接続拒否される。
    config(['logging.channels.slack.url' => 'http://127.0.0.1:1/services/T/B/x']);

    Log::channel('slack')->error('接続できないケース');

    expect(true)->toBeTrue();
});
```

- [ ] **Step 7: テストを実行して通ることを確認する**

Run: `php artisan test tests/Feature/SlackAlertChannelTest.php`
Expected: PASS（4件）。`WhatFailureGroupHandler` が無いと FAIL するので、Step 3 の実装が効いていることの確認になる。

- [ ] **Step 8: コミットする**

```bash
git add app/Logging/SlackAlertLoggerFactory.php config/logging.php tests/Feature/SlackAlertChannelTest.php
git commit -m "feat: Slack通知用のログチャンネルを追加"
```

- [ ] **Step 9: 残りのテストを書く（レベル・機密値・重複抑制）**

```php
use Monolog\Handler\DeduplicationHandler;
use Monolog\Handler\SlackWebhookHandler;
use Monolog\Level;

it('error 未満は Slack に送らない', function () {
    $logger = (new SlackAlertLoggerFactory())([
        'url' => 'https://hooks.slack.com/services/T/B/x',
        'level' => 'error',
    ]);
    $slack = slackHandlerOf($logger);

    expect($slack->getLevel())->toBe(Level::Error);
    expect($slack->isHandling(new Monolog\LogRecord(
        new DateTimeImmutable(), 'testing', Level::Warning, '警告は送らない',
    )))->toBeFalse();
});

it('context と extra を Slack ペイロードに含めない', function () {
    $logger = (new SlackAlertLoggerFactory())([
        'url' => 'https://hooks.slack.com/services/T/B/x',
        'level' => 'error',
    ]);
    $record = slackHandlerOf($logger)->getSlackRecord()->getSlackData(
        new Monolog\LogRecord(
            new DateTimeImmutable(),
            'testing',
            Level::Error,
            'DB接続に失敗',
            ['token' => 'secret-token-value', 'email' => 'user@example.com'],
        ),
    );

    expect(json_encode($record))->not->toContain('secret-token-value');
    expect(json_encode($record))->not->toContain('user@example.com');
});

it('同一メッセージの連続発生を重複排除ハンドラで抑える', function () {
    $logger = (new SlackAlertLoggerFactory())([
        'url' => 'https://hooks.slack.com/services/T/B/x',
        'level' => 'error',
    ]);
    $group = $logger->getHandlers()[0];

    expect($group)->toBeInstanceOf(WhatFailureGroupHandler::class);
    expect(dedupHandlerOf($logger))->toBeInstanceOf(DeduplicationHandler::class);
});
```

`slackHandlerOf()` / `dedupHandlerOf()` は同ファイル内のヘルパとして定義する。`WhatFailureGroupHandler` と `DeduplicationHandler` は内側のハンドラを公開しないため、`ReflectionProperty`（それぞれ `handlers`、`handler`）で取り出す。

- [ ] **Step 10: テストが失敗することを確認する**

Run: `php artisan test tests/Feature/SlackAlertChannelTest.php`
Expected: ヘルパ未定義で FAIL

- [ ] **Step 11: ヘルパを実装してテストを通す**

Run: `php artisan test tests/Feature/SlackAlertChannelTest.php`
Expected: PASS（7件）

- [ ] **Step 12: Pint と全テストを実行する**

Run: `./vendor/bin/pint --test && php artisan test`
Expected: どちらも PASS

- [ ] **Step 13: コミットする**

```bash
git add tests/Feature/SlackAlertChannelTest.php app/Logging/SlackAlertLoggerFactory.php
git commit -m "test: Slackログチャンネルのレベル・秘匿・重複抑制を検証"
```

---

### Task 2: 本番設定への組み込みと運用ドキュメント

**Files:**
- Modify: `render.yaml`（`envVars`）
- Modify: `.env.example`
- Modify: `frontend/.env.example`
- Create: `docs/SLACK_ALERTS.md`
- Modify: `README.md`（目次と新しい節）

**Interfaces:**
- Consumes: Task 1 の `slack` ログチャンネル（`LOG_STACK` に `slack` を含めることで有効化）
- Produces: 環境変数名の確定 — Laravel は `LOG_STACK` / `LOG_SLACK_WEBHOOK_URL` / `LOG_SLACK_LEVEL`、Next.js は `SLACK_WEBHOOK_URL`。Task 3 の ECS 環境変数と Task 5 がこの名前を使う。

- [ ] **Step 1: `render.yaml` を更新する**

`guide` サービスの `envVars` の `LOG_CHANNEL: stderr` を `LOG_CHANNEL: stack` に変え、`LOG_STACK` に `stderr,slack`、`LOG_SLACK_LEVEL` に `error` を追加する。`LOG_SLACK_WEBHOOK_URL` と `SLACK_WEBHOOK_URL` は `sync: false`（Render のダッシュボードで手入力）で追加する。

- [ ] **Step 2: `.env.example` と `frontend/.env.example` を更新する**

`.env.example` に `LOG_STACK=single`、`LOG_SLACK_WEBHOOK_URL=`、`LOG_SLACK_LEVEL=error` を追記する（ローカルでは Slack を無効にしたいので `LOG_STACK` は `single` のまま）。`frontend/.env.example` に `SLACK_WEBHOOK_URL=` を追記する。

- [ ] **Step 3: ローカルで Slack が無効なままであることを確認する**

Run: `php artisan test && php artisan config:clear`
Expected: PASS。`.env` を変更していないので挙動は変わらない。

- [ ] **Step 4: `docs/SLACK_ALERTS.md` を書く**

次を日本語で書く。(1) Slack App の作成と Incoming Webhook URL の発行手順、(2) 4経路それぞれの設定先 — Render 環境変数、GitHub の `SLACK_WEBHOOK_URL` シークレット、`cdk deploy` の `-c enableSlackAlerts=true` と `--parameters GuideStack:SlackWebhookUrl=...`、(3) 通知されるもの・されないものの一覧（設計判断の表をそのまま引く）、(4) 通知を止める方法（Render は `LOG_STACK` から `slack` を外す、AWS は `-c enableSlackAlerts=false` で再デプロイ、GitHub はシークレットを削除）、(5) 重複抑制は300秒・ECS タスク単位であること。

- [ ] **Step 5: `README.md` に節を追加する**

`## ランタイムのセキュリティ更新`（202行目付近）の後に `## 障害通知` を追加し、`docs/SLACK_ALERTS.md` へリンクする。`## 目次`（18行目）にも項目を足す。

- [ ] **Step 6: コミットする**

```bash
git add render.yaml .env.example frontend/.env.example docs/SLACK_ALERTS.md README.md
git commit -m "docs: Slack障害通知の設定手順を追加"
```

---

### Task 3: CloudWatch アラームを Slack へ（CDK + Lambda）

**Files:**
- Create: `infra/lambda/slack-alarm-notifier/index.mjs`
- Create: `infra/test/slack-alarm-notifier.test.ts`
- Modify: `infra/lib/guide-stack.ts`（import 追加、`alertTopic` 周辺の 802-812行目、可用性アラーム3種、ECS 環境変数 460-480行目付近、`CfnOutput` 1196行目付近）
- Modify: `infra/test/guide-stack.test.ts`

**Interfaces:**
- Consumes: Task 2 が決めた環境変数名 `LOG_SLACK_WEBHOOK_URL` / `LOG_SLACK_LEVEL`
- Produces: `index.mjs` の `export async function handler(event): Promise<void>`。CDK context `enableSlackAlerts`（boolean、既定 false）と CloudFormation パラメータ `SlackWebhookUrl`（NoEcho）。`CfnOutput` `SlackNotifierFunctionName`。

- [ ] **Step 1: Lambda ハンドラのテストを書く**

```ts
import { beforeEach, describe, expect, test, vi } from 'vitest';
import { handler } from '../lambda/slack-alarm-notifier/index.mjs';

const alarmMessage = {
  AlarmName: 'guide-production-no-healthy-targets',
  AlarmDescription: null,
  NewStateValue: 'ALARM',
  NewStateReason: 'Threshold Crossed',
  StateChangeTime: '2026-11-10T00:00:00.000+0000',
  Region: 'Asia Pacific (Tokyo)',
  AWSAccountId: '123456789012',
};

function snsEvent(message: unknown) {
  return {
    Records: [{ Sns: { Message: typeof message === 'string' ? message : JSON.stringify(message) } }],
  };
}

describe('slack-alarm-notifier', () => {
  beforeEach(() => {
    process.env.SLACK_WEBHOOK_URL = 'https://hooks.slack.com/services/T/B/x';
    vi.stubGlobal('fetch', vi.fn(async () => new Response('ok', { status: 200 })));
  });

  test('アラーム名と状態と理由を Slack へ送る', async () => {
    await handler(snsEvent(alarmMessage));

    const [url, init] = (globalThis.fetch as any).mock.calls[0];
    expect(url).toBe('https://hooks.slack.com/services/T/B/x');
    const body = JSON.parse(init.body);
    expect(JSON.stringify(body)).toContain('guide-production-no-healthy-targets');
    expect(JSON.stringify(body)).toContain('ALARM');
    expect(JSON.stringify(body)).toContain('Threshold Crossed');
  });

  test('OK 復帰は復旧として送る', async () => {
    await handler(snsEvent({ ...alarmMessage, NewStateValue: 'OK' }));

    const body = JSON.parse((globalThis.fetch as any).mock.calls[0][1].body);
    expect(JSON.stringify(body)).toContain('OK');
  });

  test('JSON でない本文はそのまま転送して落ちない', async () => {
    await handler(snsEvent('アラームではない素のテキスト'));

    const body = JSON.parse((globalThis.fetch as any).mock.calls[0][1].body);
    expect(JSON.stringify(body)).toContain('アラームではない素のテキスト');
  });

  test('Webhook URL 未設定なら送信しない', async () => {
    delete process.env.SLACK_WEBHOOK_URL;

    await handler(snsEvent(alarmMessage));

    expect(globalThis.fetch).not.toHaveBeenCalled();
  });

  test('Slack が 500 を返しても例外を投げない', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => new Response('err', { status: 500 })));

    await expect(handler(snsEvent(alarmMessage))).resolves.toBeUndefined();
  });
});
```

- [ ] **Step 2: テストが失敗することを確認する**

Run: `npm --prefix infra test -- slack-alarm-notifier`
Expected: FAIL（`index.mjs` が存在しない）

- [ ] **Step 3: `handler(event)` を `infra/lambda/slack-alarm-notifier/index.mjs` に実装する**

依存パッケージを使わず、グローバル `fetch` と `AbortSignal.timeout(5000)` で送る。`event.Records` の各 `Sns.Message` を `JSON.parse` し、失敗したら本文をそのまま使う。`NewStateValue` が `ALARM` なら赤、`OK` なら緑、それ以外は灰色の `attachments` 1件にする。`fetch` の失敗と非 2xx は `console.error` に記録して握りつぶす（Lambda を再試行させない）。

- [ ] **Step 4: テストが通ることを確認する**

Run: `npm --prefix infra test -- slack-alarm-notifier`
Expected: PASS（5件）

- [ ] **Step 5: CDK 側のテストを `infra/test/guide-stack.test.ts` に追記する**

```ts
test('既定では Slack 通知のリソースを作らない', () => {
  const template = makeTemplate();
  expect(
    Object.keys(template.findResources('AWS::SNS::Subscription', {
      Properties: { Protocol: 'lambda' },
    })),
  ).toHaveLength(0);
  expect(Object.keys(template.findParameters('SlackWebhookUrl'))).toHaveLength(0);
});

test('enableSlackAlerts で SNS を購読する Lambda と NoEcho パラメータを作る', () => {
  const template = makeTemplate({ appImageTag: 'abc123', enableSlackAlerts: true });

  template.hasParameter('SlackWebhookUrl', { NoEcho: true });
  template.hasResourceProperties('AWS::Lambda::Function', {
    Runtime: 'nodejs22.x',
    Timeout: 10,
    Environment: { Variables: { SLACK_WEBHOOK_URL: { Ref: 'SlackWebhookUrl' } } },
  });
  expect(
    Object.keys(template.findResources('AWS::SNS::Subscription', {
      Properties: { Protocol: 'lambda' },
    })),
  ).toHaveLength(1);
});

test('すべてのアラームが復旧時も通知する', () => {
  const template = makeTemplate();

  // 既存の this.node.findAll() ループが全 Alarm に OK アクションを付けている。
  // Slack 通知を足したあともこの性質が保たれることの回帰ガード。
  const alarms = template.findResources('AWS::CloudWatch::Alarm');
  expect(Object.keys(alarms)).toHaveLength(10);
  for (const [id, alarm] of Object.entries(alarms)) {
    expect(alarm.Properties.OKActions, id).toBeDefined();
    expect(alarm.Properties.AlarmActions, id).toBeDefined();
  }
});

test('ECS タスクに Slack ログ設定を渡す', () => {
  const template = makeTemplate();

  template.hasResourceProperties('AWS::ECS::TaskDefinition', {
    ContainerDefinitions: Match.arrayWith([
      Match.objectLike({
        Environment: Match.arrayWith([
          { Name: 'LOG_STACK', Value: 'stderr,slack' },
          { Name: 'LOG_SLACK_LEVEL', Value: 'error' },
        ]),
      }),
    ]),
  });
});
```

- [ ] **Step 6: テストが失敗することを確認する**

Run: `npm --prefix infra test -- guide-stack`
Expected: 新規4件が FAIL

- [ ] **Step 7: `infra/lib/guide-stack.ts` を変更する**

4点を加える。(a) `import * as lambda from 'aws-cdk-lib/aws-lambda';` を追加し、`enableSlackAlerts` を `this.node.tryGetContext('enableSlackAlerts') === true || this.node.tryGetContext('enableSlackAlerts') === 'true'` で読む。真のとき `SlackWebhookUrl`（`noEcho: true`、`allowedPattern: '^https://hooks\\.slack\\.com/services/.+$'`）を作り、`lambda.Function`（`runtime: lambda.Runtime.NODEJS_22_X`、`handler: 'index.handler'`、`code: lambda.Code.fromAsset(path.join(__dirname, '../lambda/slack-alarm-notifier'))`、`timeout: cdk.Duration.seconds(10)`、`memorySize: 128`、ログは非推奨の `logRetention` ではなく明示的な `logs.LogGroup` を作って `logGroup` に渡す、環境変数 `SLACK_WEBHOOK_URL`）を作り、`alertTopic.addSubscription(new subscriptions.LambdaSubscription(fn))` を呼ぶ。(b) 復旧通知のための変更は**不要**。`infra/lib/guide-stack.ts:973-978` に `this.node.findAll()` で全 `cloudwatch.Alarm` に `addOkAction(alarmAction)` を付けるループが既にあるため、`addOkAction` の行を新たに書かないこと。Slack の Lambda は同じ SNS Topic を購読するので、これだけで全アラームの復旧通知が Slack にも届く。(c) ECS のコンテナ環境変数に `LOG_STACK: 'stderr,slack'` と `LOG_SLACK_LEVEL: 'error'` を足し、`enableSlackAlerts` が真のときだけ `LOG_SLACK_WEBHOOK_URL` を `ecs.Secret.fromSecretsManager(slackWebhookSecret)` で渡す（`slackWebhookSecret` は `SlackWebhookUrl` パラメータから `cdk.SecretValue.cfnParameter` で作る）。(d) `enableSlackAlerts` が真のとき `CfnOutput` `SlackNotifierFunctionName` を出力する。

- [ ] **Step 8: テスト・型チェックを実行する**

Run: `cd infra && npx tsc --noEmit && npm test`
Expected: すべて PASS（既存テストも含む）

- [ ] **Step 9: 合成できることを確認する**

Run: `npm run cdk:synth -- -c enableSlackAlerts=true`
Expected: エラーなし

- [ ] **Step 10: `docs/AWS_DEPLOYMENT_DONE.md` に手順を追記する**

「事前準備」に Slack Webhook URL を加え、`cdk deploy` の例に `-c enableSlackAlerts=true` と `--parameters GuideStack:SlackWebhookUrl=<URL>` を足す。

- [ ] **Step 11: コミットする**

```bash
git add infra/lambda infra/lib/guide-stack.ts infra/test docs/AWS_DEPLOYMENT_DONE.md
git commit -m "feat: CloudWatchアラームをSlackへ通知する"
```

---

### Task 4: GitHub Actions の失敗通知

**Files:**
- Modify: `.github/workflows/deploy.yml`（`jobs` の末尾に追加）

**Interfaces:**
- Consumes: リポジトリシークレット `SLACK_WEBHOOK_URL`
- Produces: なし

- [ ] **Step 1: `notify-failure` ジョブを追加する**

`needs: [validate, runtime-image, infra-test, deploy]`、`if: ${{ always() && github.event_name == 'push' && contains(needs.*.result, 'failure') }}`、`runs-on: ubuntu-latest`。ステップは1つで、`SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK_URL }}` を環境変数に取り、空なら `echo` して `exit 0`。空でなければ `jq -n` でペイロードを組み、`curl --fail --silent --show-error --max-time 5` で POST する。本文に含めるのは、ワークフロー名、どのジョブが失敗したか（`${{ toJSON(needs) }}` から読める形）、`github.ref_name`、`github.actor`、`${{ github.server_url }}/${{ github.repository }}/actions/runs/${{ github.run_id }}`。`deploy` はスキップされることが多いので、`always()` と `contains(needs.*.result, 'failure')` の組み合わせでスキップを失敗扱いしないこと。

- [ ] **Step 2: ワークフローの構文を検証する**

Run: `docker run --rm -v "$PWD":/repo -w /repo rhysd/actionlint:latest -color`
Expected: `.github/workflows/deploy.yml` にエラーなし。Docker が使えない場合は `python3 -c "import yaml;yaml.safe_load(open('.github/workflows/deploy.yml'))"` で YAML として読めることだけ確認する。

- [ ] **Step 3: 通知が既存ジョブの成否に影響しないことを確認する**

`notify-failure` を `needs` に持つジョブが存在しないこと、`deploy` の `if` 条件を変更していないことを `git diff .github/workflows/deploy.yml` で確認する。

- [ ] **Step 4: コミットする**

```bash
git add .github/workflows/deploy.yml
git commit -m "ci: CIとデプロイの失敗をSlackへ通知する"
```

---

### Task 5: Next.js サーバー側エラーの通知

**Files:**
- Create: `frontend/src/lib/alerts/slack.ts`
- Create: `frontend/src/lib/alerts/slack.test.ts`
- Create: `frontend/src/instrumentation.ts`

**Interfaces:**
- Consumes: Task 2 が決めた環境変数 `SLACK_WEBHOOK_URL`
- Produces: `notifySlack(alert: { title: string; detail: string; context?: Record<string, string> }): Promise<void>`、および Next.js が呼ぶ `onRequestError`

- [ ] **Step 1: 送信関数のテストを書く**

```ts
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';
import { notifySlack } from './slack';

describe('notifySlack', () => {
  beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn(async () => new Response('ok', { status: 200 })));
  });
  afterEach(() => {
    delete process.env.SLACK_WEBHOOK_URL;
    vi.unstubAllGlobals();
  });

  test('Webhook URL が無いときは送信しない', async () => {
    await notifySlack({ title: 'エラー', detail: 'boom' });

    expect(globalThis.fetch).not.toHaveBeenCalled();
  });

  test('タイトルと詳細を送る', async () => {
    process.env.SLACK_WEBHOOK_URL = 'https://hooks.slack.com/services/T/B/x';

    await notifySlack({ title: 'Next.js サーバーエラー', detail: 'boom' });

    const [url, init] = (globalThis.fetch as any).mock.calls[0];
    expect(url).toBe('https://hooks.slack.com/services/T/B/x');
    expect(init.body).toContain('Next.js サーバーエラー');
    expect(init.body).toContain('boom');
  });

  test('送信が失敗しても例外を投げない', async () => {
    process.env.SLACK_WEBHOOK_URL = 'https://hooks.slack.com/services/T/B/x';
    vi.stubGlobal('fetch', vi.fn(async () => { throw new Error('network down'); }));

    await expect(notifySlack({ title: 'エラー', detail: 'boom' })).resolves.toBeUndefined();
  });

  test('非 2xx でも例外を投げない', async () => {
    process.env.SLACK_WEBHOOK_URL = 'https://hooks.slack.com/services/T/B/x';
    vi.stubGlobal('fetch', vi.fn(async () => new Response('err', { status: 429 })));

    await expect(notifySlack({ title: 'エラー', detail: 'boom' })).resolves.toBeUndefined();
  });
});
```

- [ ] **Step 2: テストが失敗することを確認する**

Run: `npm --prefix frontend test -- slack`
Expected: FAIL（`./slack` が存在しない）

- [ ] **Step 3: `notifySlack` を `frontend/src/lib/alerts/slack.ts` に実装する**

`process.env.SLACK_WEBHOOK_URL` が空なら即 `return`。`fetch` に `method: 'POST'`、`headers: { 'content-type': 'application/json' }`、`signal: AbortSignal.timeout(5000)` を渡す。`try`/`catch` で包み、失敗と非 2xx は `console.error` に出すだけにする。`context` はキーと値を本文に並べるだけで、エラーオブジェクトをそのまま `JSON.stringify` しない（スタックトレース全文を送らない。先頭 2000 文字で切る）。

- [ ] **Step 4: テストが通ることを確認する**

Run: `npm --prefix frontend test -- slack`
Expected: PASS（4件）

- [ ] **Step 5: `frontend/src/instrumentation.ts` を書く**

`export async function onRequestError(error: unknown, request: { path?: string; method?: string }, context: { routerKind?: string; routeType?: string })` を定義し、`notifySlack` を `await` せずに呼ぶ（レスポンスを遅らせない）。`title` は `Next.js サーバーエラー`、`detail` は `error instanceof Error ? error.message : String(error)`、`context` に `path` / `method` / `routeType` を入れる。

- [ ] **Step 6: 型チェック・lint・テスト・ビルドを実行する**

Run: `npm --prefix frontend run typecheck && npm --prefix frontend run lint && npm --prefix frontend test && npm --prefix frontend run build`
Expected: すべて PASS。ビルドログに `instrumentation` が認識された旨が出ること。

- [ ] **Step 7: コミットする**

```bash
git add frontend/src/lib/alerts frontend/src/instrumentation.ts
git commit -m "feat: Next.jsのサーバーエラーをSlackへ通知する"
```

---

## 完了条件

- `php artisan test` / `npm --prefix frontend test` / `npm --prefix infra test` がすべて PASS
- `./vendor/bin/pint --test`、`npm --prefix frontend run lint`、`npm --prefix frontend run typecheck`、`cd infra && npx tsc --noEmit` が PASS
- `npm --prefix frontend run build` と `npm run cdk:synth -- -c enableSlackAlerts=true` が成功
- `composer audit` と `npm audit --prefix frontend --omit=dev` の結果が実装前と変わらない（依存を追加していない）
- Webhook URL が `git grep -i "hooks.slack.com/services"` でテストのダミー値以外に出てこない
