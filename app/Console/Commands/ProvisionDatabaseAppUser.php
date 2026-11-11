<?php

namespace App\Console\Commands;

use App\Support\PostgresScramVerifier;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Web アプリが接続に使う、権限を絞った PostgreSQL ユーザーを用意する。
 *
 * AWS では DB のマスターユーザーを Migration タスクだけが使い、Web タスクは
 * このコマンドが作るユーザーで接続する（infra/lib/guide-stack.ts）。
 * アプリに脆弱性があっても、テーブルの読み書きより先（テーブルの作成・削除、
 * ロールの操作）へ進めないようにするため。
 *
 * 何度実行しても同じ状態になる。デプロイのたびに migrate の前に流し、
 * パスワードと権限を毎回 Secret の値へ合わせ直す。
 */
class ProvisionDatabaseAppUser extends Command
{
    protected $signature = 'db:provision-app-user';

    protected $description = 'Web アプリ用の権限を絞った PostgreSQL ユーザーを作成・更新する';

    /**
     * アプリ用ユーザーに与えるテーブル権限。DDL と TRUNCATE は含めない。
     */
    private const TABLE_PRIVILEGES = 'SELECT, INSERT, UPDATE, DELETE';

    private const SEQUENCE_PRIVILEGES = 'USAGE, SELECT';

    public function handle(): int
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'pgsql') {
            $this->error('db:provision-app-user は PostgreSQL でだけ使えます。');

            return self::FAILURE;
        }

        $username = config('database.app_user.username');
        $password = config('database.app_user.password');

        // ロール名は SQL に識別子として埋め込むので、形を先に決め打ちで確かめる。
        if (! is_string($username) || preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $username) !== 1) {
            $this->error('DB_APP_USERNAME は英小文字・数字・アンダースコアで指定してください。');

            return self::FAILURE;
        }
        if (! is_string($password) || $password === '') {
            $this->error('DB_APP_PASSWORD が設定されていません。');

            return self::FAILURE;
        }
        // 接続中のユーザー自身を指定すると、マスターユーザーのパスワードを書き換えてしまう。
        if ($username === $connection->getConfig('username')) {
            $this->error('DB_APP_USERNAME には、このコマンドの接続ユーザーとは別のユーザーを指定してください。');

            return self::FAILURE;
        }

        $role = '"'.$username.'"';
        $database = '"'.str_replace('"', '""', $connection->getDatabaseName()).'"';
        // 検証子は英数字と + / = $ : だけでできているが、念のためドライバに引用させる。
        $verifier = $connection->getPdo()->quote(PostgresScramVerifier::make($password));

        // 途中で失敗したときに、権限が半端に付いたユーザーを残さない。
        $connection->transaction(function () use ($connection, $username, $role, $database, $verifier) {
            // CREATE ROLE / ALTER ROLE はユーティリティ文で、プレースホルダを使えない。
            if ($this->roleExists($connection, $username)) {
                $connection->statement("ALTER ROLE {$role} WITH LOGIN PASSWORD {$verifier}");
            } else {
                $connection->statement("CREATE ROLE {$role} WITH LOGIN PASSWORD {$verifier}");
            }

            $connection->statement("GRANT CONNECT ON DATABASE {$database} TO {$role}");
            $connection->statement("GRANT USAGE ON SCHEMA public TO {$role}");

            // 既にあるテーブルとシーケンス。
            $connection->statement('GRANT '.self::TABLE_PRIVILEGES." ON ALL TABLES IN SCHEMA public TO {$role}");
            $connection->statement('GRANT '.self::SEQUENCE_PRIVILEGES." ON ALL SEQUENCES IN SCHEMA public TO {$role}");

            // これから migrate が作るテーブルとシーケンス。
            // 既定権限は「このコマンドの接続ユーザーが今後作るもの」に効くので、
            // migrate も同じユーザー（マスターユーザー）で実行すること。
            $connection->statement('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT '.self::TABLE_PRIVILEGES." ON TABLES TO {$role}");
            $connection->statement('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT '.self::SEQUENCE_PRIVILEGES." ON SEQUENCES TO {$role}");
        });

        $this->info("PostgreSQL ユーザー {$username} を用意しました。");

        return self::SUCCESS;
    }

    private function roleExists(Connection $connection, string $username): bool
    {
        return $connection->selectOne('SELECT 1 AS found FROM pg_roles WHERE rolname = ?', [$username]) !== null;
    }
}
