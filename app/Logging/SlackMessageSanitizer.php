<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Slack へ送るメッセージ本文から、機密値を含みやすい部分を落とすプロセッサ。
 *
 * context / extra を送らない設定にしても、Laravel の
 * Illuminate\Database\QueryException は「メッセージ本文そのもの」に
 * SQL（プレースホルダへバインド済みの値つき）と接続先（Host / Port / Database）を
 * 埋め込む（QueryException::formatMessage()）。
 * たとえば `select * from password_reset_tokens where token = ?` が
 * タイムアウトすると、実際のトークン値が本文に載って Slack へ流れる。
 *
 * そこで (1) 末尾の `(Connection: ..., SQL: ...)` を捨てて参照先だけ示し、
 * (2) 残った本文も長さで切る。詳細は stderr / CloudWatch Logs 側で見る。
 */
class SlackMessageSanitizer implements ProcessorInterface
{
    /**
     * Slack へ送るメッセージ本文の最大文字数。
     */
    public const MAX_MESSAGE_LENGTH = 300;

    /**
     * 落とした SQL 詳細の代わりに置く文字列。
     */
    public const SQL_PLACEHOLDER = '(SQL詳細はログ参照)';

    /**
     * 切り詰めたことを示す末尾。
     */
    public const TRUNCATION_SUFFIX = '…（以下省略）';

    public function __invoke(LogRecord $record): LogRecord
    {
        $message = self::stripConnectionDetails($record->message);
        $message = self::truncate($message);

        if ($message === $record->message) {
            return $record;
        }

        return $record->with(message: $message);
    }

    /**
     * QueryException が付ける `(Connection: ..., SQL: ...)` を取り除く。
     *
     * 末尾に固定しない。`Log::error("DB失敗: {$e->getMessage()} 再試行します")` のように
     * 後ろに文字が足されると、末尾アンカーでは括弧内が本文の途中に残って漏れる。
     * 本文は複数行になりうるため /s を付け、`(Connection: ` から（貪欲に）最後の
     * 閉じ括弧までをまとめて落とす。SQL 自体が括弧を含むため非貪欲にはしない。
     * 括弧の後に続く文字（上の例の「再試行します」）は残る。
     */
    private static function stripConnectionDetails(string $message): string
    {
        $stripped = preg_replace(
            '/\s*\(Connection: .*\)/s',
            ' '.self::SQL_PLACEHOLDER,
            $message,
        );

        // preg_replace は失敗時に null を返す。通知のために本文を失うのは避ける。
        return is_string($stripped) ? $stripped : $message;
    }

    private static function truncate(string $message): string
    {
        if (mb_strlen($message) <= self::MAX_MESSAGE_LENGTH) {
            return $message;
        }

        return mb_substr($message, 0, self::MAX_MESSAGE_LENGTH).self::TRUNCATION_SUFFIX;
    }
}
