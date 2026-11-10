<?php

namespace App\Logging;

use Closure;
use Monolog\Handler\AbstractHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\LogRecord;
use Monolog\ResettableInterface;

/**
 * 同じ「レベル + メッセージ」を、このプロセスの中では一定時間に1回だけ内側のハンドラへ渡す。
 *
 * DeduplicationHandler::flush() は送信するかどうかを 1 つの真偽値で決め、
 * 送ると決めたらバッファ全体（重複を含む）を handleBatch() へ渡す。
 * そのため「1 リクエスト内で同じエラーを N 回ログした」場合は N 回 Slack へ送られ、
 * 抑制が効き始めるのは次のプロセスからになる。
 * ここでプロセス内の重複を畳み、DeduplicationHandler はプロセス間の抑制だけを担う。
 *
 * 記録には送信時刻を持たせ、抑制窓を過ぎたキーは「無かったもの」として扱う。
 * PHP-FPM のワーカーは（このリポジトリには pm.max_requests の設定が無いため）
 * コンテナの寿命いっぱい生き続けるので、時刻を持たないと実質「ワーカーにつき永久に1回」
 * になり、1時間後に再発した障害が同じワーカー上では黙殺される。
 *
 * 送信の成否は見ない（DeduplicationHandler のストアと同じ扱い）。
 * 送信に失敗した通知も、抑制窓のあいだは再送されない。
 */
class OncePerProcessHandler extends AbstractHandler
{
    /**
     * 記憶しておくキーの最大数。長時間動くワーカーで無制限に増えないようにする。
     */
    public const MAX_REMEMBERED_KEYS = 200;

    /**
     * 送信済みキー => 送信時刻（Unix 秒）。プロセスの寿命いっぱい保持する。
     *
     * @var array<string, int>
     */
    private static array $sent = [];

    /**
     * @param  int  $window  同じキーを抑制する秒数
     * @param  (Closure(): int)|null  $clock  現在時刻（Unix 秒）。テストから差し替える
     */
    public function __construct(
        private HandlerInterface $handler,
        private int $window = 300,
        private ?Closure $clock = null,
    ) {
        parent::__construct();
    }

    public function handle(LogRecord $record): bool
    {
        $now = $this->clock === null ? time() : ($this->clock)();
        $key = self::keyFor($record);
        $lastSentAt = self::$sent[$key] ?? null;

        if ($lastSentAt !== null && $now - $lastSentAt < $this->window) {
            // 送らない。false を返して後続のハンドラへバブルさせる。
            return false;
        }

        // 窓を過ぎた記録を落としてから上限を見る。
        foreach (self::$sent as $rememberedKey => $sentAt) {
            if ($now - $sentAt >= $this->window) {
                unset(self::$sent[$rememberedKey]);
            }
        }

        // それでも上限なら、古い順に捨てる（PHP の配列は挿入順を保つ）。
        while (count(self::$sent) >= self::MAX_REMEMBERED_KEYS) {
            array_shift(self::$sent);
        }

        self::$sent[$key] = $now;

        return $this->handler->handle($record);
    }

    public function close(): void
    {
        $this->handler->close();
    }

    /**
     * Logger::reset() から呼ばれる。Octane のようにプロセスを使い回す構成では、
     * リクエストの区切りで記憶を捨てる自然な地点になる。
     */
    public function reset(): void
    {
        self::$sent = [];

        if ($this->handler instanceof ResettableInterface) {
            $this->handler->reset();
        }
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
