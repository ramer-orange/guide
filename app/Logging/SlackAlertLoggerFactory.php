<?php

namespace App\Logging;

use Monolog\Handler\DeduplicationHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\NullHandler;
use Monolog\Handler\ProcessableHandlerInterface;
use Monolog\Handler\WhatFailureGroupHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;

/**
 * Slack 障害通知用のログチャンネルを組み立てるカスタムドライバ。
 *
 * - Webhook URL が未設定でも例外を投げず、何もしないチャンネルとして振る舞う。
 * - Slack 側の障害でログ呼び出し元に例外を伝播させない。送信は 5 秒で打ち切り、再試行しない。
 * - context / extra を Slack ペイロードに含めず、メッセージ本文からも SQL と接続先を落とす。
 * - 同一メッセージの連続発生を、プロセス内では 1 回に畳み、プロセス間は 300 秒抑制する。
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
        // http(s):// で始まらない値も未設定として扱う。AWS では Webhook URL を入れる Secret を
        // 仮の値（"unset"）で先に作り、あとから値を入れるため、その間に送信を試みないようにする。
        if (! is_string($url) || preg_match('#^https?://#i', $url) !== 1) {
            return new Logger('slack', [new NullHandler]);
        }

        $level = $config['level'] ?? 'error';

        $slack = $this->makeSlackHandler($config, $url, $level);

        // サニタイズはこの Slack ハンドラの書き込み直前だけで行う。
        // Logger のプロセッサにすると stack チャンネルへ漏れる。
        // LogManager::createStackDriver は `LOG_STACK` に挙げた全チャンネルの
        // ハンドラとプロセッサを1つの Logger にまとめるため
        // （vendor/laravel/framework/src/Illuminate/Log/LogManager.php:282-294）、
        // 本番の `LOG_STACK=stderr,slack` では stderr のレコードまで
        // 切り詰め・SQL 削除の対象になってしまう。
        // ハンドラのプロセッサは AbstractProcessingHandler::handle() が write() の
        // 直前に適用するので、他のハンドラには一切影響しない。
        $slack->pushProcessor(new SlackMessageSanitizer);

        // 1 リクエスト内の重複を畳んだうえで、Slack 障害を呼び出し元から隔離する。
        // DeduplicationHandler は handle() 時点では送信せず、実際の送信は flush()
        // （BufferHandler::handle() が登録する register_shutdown_function 経由の close()）で起きる。
        // 外側の WhatFailureGroupHandler はこの経路を通らないため、内側にも挟んで
        // 送信例外を flush() の中で止める。
        $deduplicated = new DeduplicationHandler(
            new WhatFailureGroupHandler([
                new OncePerProcessHandler($slack, self::DEDUPLICATION_SECONDS),
            ]),
            $this->deduplicationStore($config),
            'error',
            self::DEDUPLICATION_SECONDS,
        );

        // DeduplicationHandler は bufferLimit = 0 / level = Debug で固定されるため、
        // 明示的にレベルを上げないと debug まで全件バッファに溜め込んでしまう。
        $deduplicated->setLevel($level);

        // Logger のプロセッサは PsrLogMessageProcessor だけ。これは組み込みの slack
        // ドライバと同じ挙動（config の replace_placeholders 相当）で、stack チャンネルへ
        // まとめられても他のハンドラの挙動を変えない。
        // Logger のプロセッサは Logger::addRecord がハンドラへ渡す前に走り、
        // ハンドラのプロセッサはその後 write() の直前に走るため、
        // 「プレースホルダ展開 → サニタイズ」の順序は Monolog の構造で保証される。
        return new Logger(
            'slack',
            [new WhatFailureGroupHandler([$deduplicated])],
            [new PsrLogMessageProcessor],
        );
    }

    /**
     * 実際に Slack へ送るハンドラ。テストから差し替えられるように分けている。
     *
     * @param  array<string, mixed>  $config
     */
    protected function makeSlackHandler(array $config, string $url, mixed $level): HandlerInterface&ProcessableHandlerInterface
    {
        return new SlackAlertWebhookHandler(
            $url,
            null,
            $config['username'] ?? 'Laravel Log',
            true,
            $config['emoji'] ?? ':boom:',
            false,
            false,
            $level,
        );
    }

    /**
     * プロセス間の重複抑制を記録するファイル。config で差し替えられる。
     *
     * @param  array<string, mixed>  $config
     */
    protected function deduplicationStore(array $config): string
    {
        $store = $config['dedup_store'] ?? null;

        if (is_string($store) && $store !== '') {
            return $store;
        }

        return storage_path('logs/slack-dedup.log');
    }
}
