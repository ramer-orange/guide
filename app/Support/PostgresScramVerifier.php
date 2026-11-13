<?php

namespace App\Support;

/**
 * PostgreSQL が pg_authid に保存する SCRAM-SHA-256 の検証子を、クライアント側で作る。
 *
 * `ALTER ROLE … PASSWORD '<平文>'` と書くと、平文のパスワードが SQL 文として
 * サーバーへ渡り、文のログやエラーログに残りうる。検証子を作ってから渡せば、
 * 平文はこのプロセスの外へ出ない（psql の \password と同じ方式）。
 * PostgreSQL は `SCRAM-SHA-256$` で始まる値を、ハッシュ済みとしてそのまま保存する。
 */
class PostgresScramVerifier
{
    /**
     * PostgreSQL の既定値（scram_iterations）と同じ反復回数。
     */
    private const ITERATIONS = 4096;

    private const SALT_BYTES = 16;

    /**
     * @param  string|null  $salt  テストで結果を固定するための塩。通常は渡さない。
     */
    public static function make(string $password, ?string $salt = null): string
    {
        $salt ??= random_bytes(self::SALT_BYTES);

        // RFC 5802 の SaltedPassword / ClientKey / StoredKey / ServerKey。
        $saltedPassword = hash_pbkdf2('sha256', $password, $salt, self::ITERATIONS, 32, true);
        $clientKey = hash_hmac('sha256', 'Client Key', $saltedPassword, true);
        $storedKey = hash('sha256', $clientKey, true);
        $serverKey = hash_hmac('sha256', 'Server Key', $saltedPassword, true);

        return sprintf(
            'SCRAM-SHA-256$%d:%s$%s:%s',
            self::ITERATIONS,
            base64_encode($salt),
            base64_encode($storedKey),
            base64_encode($serverKey),
        );
    }
}
