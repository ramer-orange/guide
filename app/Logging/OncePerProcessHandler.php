<?php

namespace App\Logging;

use Monolog\Handler\AbstractHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\LogRecord;

/**
 * 同じ「レベル + メッセージ」を、このプロセスの中では 1 回だけ内側のハンドラへ渡す。
 *
 * DeduplicationHandler::flush() は送信するかどうかを 1 つの真偽値で決め、
 * 送ると決めたらバッファ全体（重複を含む）を handleBatch() へ渡す。
 * そのため「1 リクエスト内で同じエラーを N 回ログした」場合は N 回 Slack へ送られ、
 * 抑制が効き始めるのは次のプロセスからになる。
 * ここでプロセス内の重複を畳み、DeduplicationHandler はプロセス間の抑制だけを担う。
 *
 * 送信の成否は見ない（DeduplicationHandler のストアと同じ扱い）。
 * 送信に失敗した通知も、このプロセスの残りの間は再送されない。
 */
class OncePerProcessHandler extends AbstractHandler
{
    /**
     * 記憶しておくキーの最大数。長時間動くワーカーで無制限に増えないようにする。
     */
    public const MAX_REMEMBERED_KEYS = 200;

    /**
     * 送信済みキーの集合。プロセスの寿命いっぱい保持する。
     *
     * @var array<string, true>
     */
    private static array $sent = [];

    public function __construct(private HandlerInterface $handler)
    {
        parent::__construct();
    }

    public function handle(LogRecord $record): bool
    {
        $key = self::keyFor($record);

        if (isset(self::$sent[$key])) {
            // 送らない。false を返して後続のハンドラへバブルさせる。
            return false;
        }

        // 上限に達したら作り直す。保持しているのは送信済みの印だけなので、
        // 捨てた結果は「もう一度だけ送る」になり、通知が増えすぎることはない。
        if (count(self::$sent) >= self::MAX_REMEMBERED_KEYS) {
            self::$sent = [];
        }

        self::$sent[$key] = true;

        return $this->handler->handle($record);
    }

    public function close(): void
    {
        $this->handler->close();
    }

    /**
     * 送信済みの記憶を捨てる。テストから「別プロセス相当」の状態を作るために使う。
     */
    public static function forgetSentRecords(): void
    {
        self::$sent = [];
    }

    /**
     * DeduplicationHandler のストア行と同じ粒度（レベル + メッセージ 1 行目）で鍵を作る。
     */
    private static function keyFor(LogRecord $record): string
    {
        $firstLine = preg_replace('{[\r\n].*}s', '', $record->message) ?? $record->message;

        return hash('sha256', $record->level->getName().':'.$firstLine);
    }
}
