<?php

use App\Logging\SlackAlertLoggerFactory;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\NullHandler;
use Monolog\Logger;

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

it('Slack への送信が失敗してもログ呼び出し元に例外が出ない', function () {
    // 127.0.0.1:1 は接続拒否される。
    config(['logging.channels.slack.url' => 'http://127.0.0.1:1/services/T/B/x']);

    $channel = Log::channel('slack');
    $channel->error('接続できないケース');
    // 実際の送信は重複排除ハンドラの close() で行われるため、ここで明示的に流す。
    $channel->getLogger()->close();

    expect(true)->toBeTrue();
});
