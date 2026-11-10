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
    $channel->error('接続できないケース');
    // 実際の送信は重複排除ハンドラの close() で行われるため、ここで明示的に流す。
    $channel->getLogger()->close();

    expect(true)->toBeTrue();
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

it('同一メッセージの連続発生を重複排除ハンドラで抑える', function () {
    $logger = (new SlackAlertLoggerFactory)([
        'url' => 'https://hooks.slack.com/services/T/B/x',
        'level' => 'error',
    ]);
    $group = $logger->getHandlers()[0];

    expect($group)->toBeInstanceOf(WhatFailureGroupHandler::class);
    expect(dedupHandlerOf($logger))->toBeInstanceOf(DeduplicationHandler::class);
});
