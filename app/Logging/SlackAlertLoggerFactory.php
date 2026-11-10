<?php

namespace App\Logging;

use Monolog\Handler\DeduplicationHandler;
use Monolog\Handler\NullHandler;
use Monolog\Handler\SlackWebhookHandler;
use Monolog\Handler\WhatFailureGroupHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;

/**
 * Slack 障害通知用のログチャンネルを組み立てるカスタムドライバ。
 *
 * - Webhook URL が未設定でも例外を投げず、何もしないチャンネルとして振る舞う。
 * - Slack 側の障害でログ呼び出し元に例外を伝播させない。
 * - context / extra を Slack ペイロードに含めないため、機密値が漏れない。
 * - 同一メッセージの連続発生は一定時間だけ抑制する。
 */
class SlackAlertLoggerFactory
{
    /**
     * 重複抑制を維持する期間（秒）。
     */
    private const DEDUPLICATION_SECONDS = 300;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __invoke(array $config): Logger
    {
        $url = $config['url'] ?? null;

        // URL 未設定（本番で Slack を使わない構成）では何もしないハンドラだけを持つ。
        if (! is_string($url) || $url === '') {
            return new Logger('slack', [new NullHandler]);
        }

        $slack = new SlackWebhookHandler(
            $url,
            null,
            $config['username'] ?? 'Laravel Log',
            true,
            $config['emoji'] ?? ':boom:',
            false,
            false,
            $config['level'] ?? 'error',
        );

        $level = $config['level'] ?? 'error';

        // 同一メッセージの連続通知を抑えたうえで、Slack 障害を呼び出し元から隔離する。
        // DeduplicationHandler は handle() 時点では送信せず、実際の送信は flush()
        // （BufferHandler::handle() が登録する register_shutdown_function 経由の close()）で起きる。
        // 外側の WhatFailureGroupHandler はこの経路を通らないため、内側にも挟んで
        // 送信例外を flush() の中で止める。
        $deduplicated = new DeduplicationHandler(
            new WhatFailureGroupHandler([$slack]),
            storage_path('logs/slack-dedup.log'),
            'error',
            self::DEDUPLICATION_SECONDS,
        );

        // DeduplicationHandler は bufferLimit = 0 / level = Debug で固定されるため、
        // 明示的にレベルを上げないと debug まで全件バッファに溜め込んでしまう。
        $deduplicated->setLevel($level);

        // 組み込みの slack ドライバと同じく、メッセージ中のプレースホルダを展開する
        // （config の replace_placeholders に対応）。context 自体は送信しないため、
        // 展開されるのは開発者がメッセージ本文で明示的に参照した値だけ。
        return new Logger('slack', [new WhatFailureGroupHandler([$deduplicated])], [new PsrLogMessageProcessor]);
    }
}
