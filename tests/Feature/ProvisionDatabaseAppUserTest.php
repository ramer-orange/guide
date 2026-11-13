<?php

use App\Support\PostgresScramVerifier;
use Illuminate\Support\Facades\DB;

/**
 * 権限の検証は PostgreSQL に対してしか意味を持たないので、sqlite（既定の phpunit.xml）では飛ばす。
 * CI は PostgreSQL に接続して全テストを流すため、そこで実行される（.github/workflows/deploy.yml）。
 */
function skipUnlessPostgres(): void
{
    if (DB::connection()->getDriverName() !== 'pgsql') {
        test()->markTestSkipped('PostgreSQL に接続しているときだけ実行する');
    }
}

function hasPrivilege(string $function, string $role, string $object, string $privilege): bool
{
    return (bool) DB::selectOne("SELECT {$function}(?, ?, ?) AS allowed", [$role, $object, $privilege])->allowed;
}

it('検証子は PostgreSQL が保存する SCRAM-SHA-256 の形式になる', function () {
    $verifier = PostgresScramVerifier::make('pencil', base64_decode('W22ZaJ0SNY7soEsUEjb6gQ=='));

    // RFC 7677 の例と同じパスワード・塩・反復回数。値は実際の PostgreSQL 16 に
    // この検証子を設定し、パスワード "pencil" でログインできることを確かめたもの。
    expect($verifier)->toBe(
        'SCRAM-SHA-256$4096:W22ZaJ0SNY7soEsUEjb6gQ==$WG5d8oPm3OtcPnkdi4Uo7BkeZkBFzpcXkuLmtbsT4qY=:wfPLwcE6nTWhTAmQ7tl2KeoiWGPlZqQxSrmfPwDl2dU=',
    );
});

it('塩を毎回変えるので、同じパスワードでも検証子は一致しない', function () {
    expect(PostgresScramVerifier::make('same'))->not->toBe(PostgresScramVerifier::make('same'));
});

it('PostgreSQL 以外では何もせず失敗する', function () {
    if (DB::connection()->getDriverName() === 'pgsql') {
        test()->markTestSkipped('PostgreSQL 以外のドライバでだけ実行する');
    }
    config(['database.app_user' => ['username' => 'guide_app_test', 'password' => 'secret']]);

    $this->artisan('db:provision-app-user')->assertFailed();
});

it('ユーザー名やパスワードが不正なら何も作らない', function (mixed $username, mixed $password) {
    skipUnlessPostgres();
    config(['database.app_user' => ['username' => $username, 'password' => $password]]);

    $this->artisan('db:provision-app-user')->assertFailed();

    expect(DB::select("SELECT 1 FROM pg_roles WHERE rolname LIKE 'guide_app_test%'"))->toBeEmpty();
})->with([
    'ユーザー名が無い' => [null, 'secret'],
    'SQL を壊す文字' => ['guide_app_test"; DROP TABLE users; --', 'secret'],
    '大文字' => ['Guide_App_Test', 'secret'],
    'パスワードが無い' => ['guide_app_test', null],
    'パスワードが空' => ['guide_app_test', ''],
]);

it('接続中のユーザー自身は指定できない', function () {
    skipUnlessPostgres();
    config(['database.app_user' => [
        'username' => DB::connection()->getConfig('username'),
        'password' => 'secret',
    ]]);

    $this->artisan('db:provision-app-user')->assertFailed();
});

it('テーブルの読み書きだけができるユーザーを作る', function () {
    skipUnlessPostgres();
    config(['database.app_user' => ['username' => 'guide_app_test', 'password' => 'secret']]);

    $this->artisan('db:provision-app-user')->assertSuccessful();

    $role = DB::selectOne("SELECT rolcanlogin, rolsuper, rolcreatedb, rolcreaterole FROM pg_roles WHERE rolname = 'guide_app_test'");
    expect($role->rolcanlogin)->toBeTrue();
    expect($role->rolsuper)->toBeFalse();
    expect($role->rolcreatedb)->toBeFalse();
    expect($role->rolcreaterole)->toBeFalse();

    foreach (['SELECT', 'INSERT', 'UPDATE', 'DELETE'] as $privilege) {
        expect(hasPrivilege('has_table_privilege', 'guide_app_test', 'users', $privilege))->toBeTrue($privilege);
    }
    // テーブルを空にする・定義を変える・新しく作る、はできない。
    expect(hasPrivilege('has_table_privilege', 'guide_app_test', 'users', 'TRUNCATE'))->toBeFalse();
    expect(hasPrivilege('has_schema_privilege', 'guide_app_test', 'public', 'CREATE'))->toBeFalse();
    expect(hasPrivilege('has_schema_privilege', 'guide_app_test', 'public', 'USAGE'))->toBeTrue();
    // 連番（id の採番）は使える。
    expect(hasPrivilege('has_sequence_privilege', 'guide_app_test', 'users_id_seq', 'USAGE'))->toBeTrue();
});

it('あとから migrate で増えたテーブルにも同じ権限が付く', function () {
    skipUnlessPostgres();
    config(['database.app_user' => ['username' => 'guide_app_test', 'password' => 'secret']]);
    $this->artisan('db:provision-app-user')->assertSuccessful();

    DB::statement('CREATE TABLE provisioned_later (id bigserial PRIMARY KEY)');

    expect(hasPrivilege('has_table_privilege', 'guide_app_test', 'provisioned_later', 'INSERT'))->toBeTrue();
    expect(hasPrivilege('has_sequence_privilege', 'guide_app_test', 'provisioned_later_id_seq', 'USAGE'))->toBeTrue();
    expect(hasPrivilege('has_table_privilege', 'guide_app_test', 'provisioned_later', 'TRUNCATE'))->toBeFalse();
});

it('2 回実行しても成功し、パスワードを新しい値に合わせ直す', function () {
    skipUnlessPostgres();
    config(['database.app_user' => ['username' => 'guide_app_test', 'password' => 'first']]);
    $this->artisan('db:provision-app-user')->assertSuccessful();

    config(['database.app_user.password' => 'second']);
    $this->artisan('db:provision-app-user')->assertSuccessful();

    expect(DB::select("SELECT 1 FROM pg_roles WHERE rolname = 'guide_app_test'"))->toHaveCount(1);
});
