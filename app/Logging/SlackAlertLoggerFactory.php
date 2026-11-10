<?php

namespace App\Logging;

use Monolog\Handler\DeduplicationHandler;
use Monolog\Handler\NullHandler;
use Monolog\Handler\SlackWebhookHandler;
use Monolog\Handler\WhatFailureGroupHandler;
use Monolog\Logger;

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
            return new Logger('slack', [new NullHandler()]);
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

        // 同一メッセージの連続通知を抑えたうえで、Slack 障害を呼び出し元から隔離する。
        // DeduplicationHandler は自身の close()（デストラクタ経由も含む）で送信するため、
        // 外側の WhatFailureGroupHandler だけでは送信失敗を捕まえられない。
        // そのため内側にも WhatFailureGroupHandler を挟んで送信例外をここで止める。
        $deduplicated = new DeduplicationHandler(
            new WhatFailureGroupHandler([$slack]),
            storage_path('logs/slack-dedup.log'),
            'error',
            self::DEDUPLICATION_SECONDS,
        );

        return new Logger('slack', [new WhatFailureGroupHandler([$deduplicated])]);
    }
}
