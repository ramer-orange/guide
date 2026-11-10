<?php

use App\Logging\SlackAlertLoggerFactory;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\DeduplicationHandler;
use Monolog\Handler\NullHandler;
use Monolog\Handler\SlackWebhookHandler;
use Monolog\Handler\WhatFailureGroupHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Monolog\Processor\PsrLogMessageProcessor;

/**
 * WhatFailureGroupHandler / DeduplicationHandler は内側のハンドラを公開しないため、
 * リフレクションで取り出す（それぞれ GroupHandler::$handlers、BufferHandler::$handler）。
 */
function wrappedHandlersOf(WhatFailureGroupHandler $group): array
{
    return (new ReflectionProperty($group, 'handlers'))->getValue($group);
}

function dedupHandlerOf(Logger $logger): DeduplicationHandler
{
    return wrappedHandlersOf($logger->getHandlers()[0])[0];
}

function slackHandlerOf(Logger $logger): SlackWebhookHandler
{
    $dedup = dedupHandlerOf($logger);
    $inner = (new ReflectionProperty($dedup, 'handler'))->getValue($dedup);

    return wrappedHandlersOf($inner)[0];
}

/**
 * BufferHandler::$bufferSize も非公開なのでリフレクションで読む。
 */
function bufferSizeOf(DeduplicationHandler $dedup): int
{
    return (new ReflectionProperty($dedup, 'bufferSize'))->getValue($dedup);
}

it('webhook URL が無いときは何もしないハンドラだけを持つ', function () {
    $logger = (new SlackAlertLoggerFactory)(['url' => null, 'level' => 'error']);

    expect($logger)->toBeInstanceOf(Logger::class);
    expect($logger->getHandlers())->toHaveCount(1);
    expect($logger->getHandlers()[0])->toBeInstanceOf(NullHandler::class);
});

it('webhook URL が空文字でも何もしないハンドラだけを持つ', function () {
    $logger = (new SlackAlertLoggerFactory)(['url' => '', 'level' => 'error']);

    expect($logger->getHandlers()[0])->toBeInstanceOf(NullHandler::class);
});

it('URL 未設定の slack チャンネルへ error を書いても例外にならない', function () {
    config(['logging.channels.slack.url' => null]);

    Log::channel('slack')->error('boom');

    expect(true)->toBeTrue();
});

it('Slack への送信が失敗してもログ呼び出し元に例外が出ない', function () {
    // 127.0.0.1:1 は接続拒否される。
    config(['logging.channels.slack.url' => 'http://127.0.0.1:1/services/T/B/x']);

    $channel = Log::channel('slack');
    // 重複排除ストアは実ファイルなので、再実行時に送信経路が抑制されないよう毎回違う本文にする。
    $channel->error('接続できないケース '.uniqid());
    // 実際の送信は重複排除ハンドラの flush() で行われるため、ここで明示的に流す。
    $channel->getLogger()->close();

    // flush() は handleBatch() が例外なく戻ったときだけ clear() まで到達する。
    // 外側の WhatFailureGroupHandler は close() の例外も飲んでしまうため、
    // 「バッファが空になった」ことが内側で例外を捕まえた唯一の証拠になる。
    expect(bufferSizeOf(dedupHandlerOf($channel->getLogger())))->toBe(0);
});

it('error 未満は Slack に送らない', function () {
    $logger = (new SlackAlertLoggerFactory)([
        'url' => 'https://hooks.slack.com/services/T/B/x',
        'level' => 'error',
    ]);
    $slack = slackHandlerOf($logger);

    expect($slack->getLevel())->toBe(Level::Error);
    expect($slack->isHandling(new LogRecord(
        new DateTimeImmutable, 'testing', Level::Warning, '警告は送らない',
    )))->toBeFalse();
});

it('context と extra を Slack ペイロードに含めない', function () {
    $logger = (new SlackAlertLoggerFactory)([
        'url' => 'https://hooks.slack.com/services/T/B/x',
        'level' => 'error',
    ]);
    $record = slackHandlerOf($logger)->getSlackRecord()->getSlackData(
        new LogRecord(
            new DateTimeImmutable,
            'testing',
            Level::Error,
            'DB接続に失敗',
            ['token' => 'secret-token-value', 'email' => 'user@example.com'],
        ),
    );

    expect(json_encode($record))->not->toContain('secret-token-value');
    expect(json_encode($record))->not->toContain('user@example.com');
});

it('error 未満は重複排除ハンドラのバッファにも溜めない', function () {
    $logger = (new SlackAlertLoggerFactory)([
        'url' => 'https://hooks.slack.com/services/T/B/x',
        'level' => 'error',
    ]);
    $dedup = dedupHandlerOf($logger);

    // DeduplicationHandler の既定は level = Debug / bufferLimit = 0（無制限）なので、
    // レベルを上げていないと debug ログまで全件メモリに溜まり続ける。
    expect($dedup->getLevel())->toBe(Level::Error);
    expect($dedup->isHandling(new LogRecord(
        new DateTimeImmutable, 'testing', Level::Warning, '警告は溜めない',
    )))->toBeFalse();
});

it('メッセージ中のプレースホルダを展開し、参照していない context は送らない', function () {
    $logger = (new SlackAlertLoggerFactory)([
        'url' => 'https://hooks.slack.com/services/T/B/x',
        'level' => 'error',
    ]);

    expect($logger->getProcessors())->toHaveCount(1);
    expect($logger->getProcessors()[0])->toBeInstanceOf(PsrLogMessageProcessor::class);

    // Logger::addRecord はハンドラへ渡す前にプロセッサを適用する。送信せずに同じ変換を再現する。
    $processed = ($logger->getProcessors()[0])(new LogRecord(
        new DateTimeImmutable,
        'testing',
        Level::Error,
        'ユーザー {id} の処理に失敗',
        ['id' => 42, 'token' => 'secret-token-value'],
    ));

    $payload = json_encode(
        slackHandlerOf($logger)->getSlackRecord()->getSlackData($processed),
        JSON_UNESCAPED_UNICODE,
    );

    expect($payload)->toContain('ユーザー 42 の処理に失敗');
    expect($payload)->not->toContain('secret-token-value');
});

it('同一メッセージの連続発生を重複排除ハンドラで抑える', function () {
    $logger = (new SlackAlertLoggerFactory)([
        'url' => 'https://hooks.slack.com/services/T/B/x',
        'level' => 'error',
    ]);
    $group = $logger->getHandlers()[0];

    expect($group)->toBeInstanceOf(WhatFailureGroupHandler::class);
    expect(dedupHandlerOf($logger))->toBeInstanceOf(DeduplicationHandler::class);
});
