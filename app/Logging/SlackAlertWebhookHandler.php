<?php

namespace App\Logging;

use Monolog\Handler\SlackWebhookHandler;
use Monolog\LogRecord;
use Monolog\Utils;
use RuntimeException;

/**
 * タイムアウト付きで Slack Incoming Webhook へ送る SlackWebhookHandler。
 *
 * 親クラスの write() は CURLOPT_TIMEOUT / CURLOPT_CONNECTTIMEOUT を設定せず、
 * さらに Monolog\Handler\Curl\Util::execute() の既定で最大5回まで再試行する。
 * Slack 側が無応答のとき、curl の既定（接続 300 秒）× 5 回ぶんリクエスト処理が
 * ブロックされ、PHP-FPM のワーカーを占有してしまう。
 * この経路だけはユーザーのリクエスト内（register_shutdown_function）で動くため、
 * 送信は「5 秒で打ち切り、再試行しない」に固定する。
 */
class SlackAlertWebhookHandler extends SlackWebhookHandler
{
    /**
     * 送信全体を打ち切るまでの秒数。
     */
    public const SEND_TIMEOUT_SECONDS = 5;

    /**
     * 接続確立を打ち切るまでの秒数。
     */
    public const CONNECT_TIMEOUT_SECONDS = 2;

    /**
     * 送信に使う curl オプション。
     *
     * 設定済みの curl ハンドルは後から読み出せないため、オプションの組み立てを
     * ここに分けてテストから検証できるようにしている。
     *
     * @return array<int, mixed>
     */
    public function curlOptions(LogRecord $record): array
    {
        $postString = Utils::jsonEncode($this->getSlackRecord()->getSlackData($record));

        return [
            CURLOPT_URL => $this->getWebhookUrl(),
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-type: application/json'],
            CURLOPT_POSTFIELDS => $postString,
            // 親クラスが設定しない2つ。これが無いと curl の既定値（接続 300 秒）になる。
            CURLOPT_TIMEOUT => self::SEND_TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function write(LogRecord $record): void
    {
        // 再試行しない。1 回だけ送る（親は Curl\Util::execute() で最大5回試す）。
        $this->send($this->curlOptions($record));
    }

    /**
     * curl を 1 回だけ実行する。
     *
     * 失敗は例外にして、外側の WhatFailureGroupHandler に飲ませる
     * （ログ呼び出し元へは伝播しない）。
     *
     * @param  array<int, mixed>  $options
     */
    protected function send(array $options): void
    {
        $ch = curl_init();

        try {
            curl_setopt_array($ch, $options);

            if (curl_exec($ch) === false) {
                throw new RuntimeException(
                    sprintf('Curl error (code %d): %s', curl_errno($ch), curl_error($ch)),
                );
            }
        } finally {
            curl_close($ch);
        }
    }
}
