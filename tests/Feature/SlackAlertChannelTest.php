<?php

use App\Logging\OncePerProcessHandler;
use App\Logging\SlackAlertLoggerFactory;
use App\Logging\SlackAlertWebhookHandler;
use App\Logging\SlackMessageSanitizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\DeduplicationHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\NullHandler;
use Monolog\Handler\TestHandler;
use Monolog\Handler\WhatFailureGroupHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Monolog\Processor\PsrLogMessageProcessor;

/**
 * WhatFailureGroupHandler / DeduplicationHandler は内側のハンドラを公開しないため、
 * リフレクションで取り出す（それぞれ GroupHandler::$handlers、BufferHandler::$handler）。
 */
function wrappedHandlersOf(WhatFailureGroupHandler $group): array
{
    return (new ReflectionProperty($group, 'handlers'))->getValue($group);
}

function dedupHandlerOf(Logger $logger): DeduplicationHandler
{
    return wrappedHandlersOf($logger->getHandlers()[0])[0];
}

function oncePerProcessHandlerOf(Logger $logger): OncePerProcessHandler
{
    $dedup = dedupHandlerOf($logger);
    $inner = (new ReflectionProperty($dedup, 'handler'))->getValue($dedup);

    return wrappedHandlersOf($inner)[0];
}

function slackHandlerOf(Logger $logger): SlackAlertWebhookHandler
{
    $once = oncePerProcessHandlerOf($logger);

    return (new ReflectionProperty($once, 'handler'))->getValue($once);
}

/**
 * Logger のプロセッサを定義順に適用して、Slack へ送るペイロードを組み立てる。
 * Monolog\Logger::addRecord がハンドラへ渡す前に行う変換と同じ順序を再現する。
 */
function renderSlackPayload(Logger $logger, LogRecord $record): string
{
    foreach ($logger->getProcessors() as $processor) {
        $record = $processor($record);
    }

    return json_encode(
        slackHandlerOf($logger)->getSlackRecord()->getSlackData($record),
        JSON_UNESCAPED_UNICODE,
    );
}

function slackLogger(array $overrides = []): Logger
{
    return (new SlackAlertLoggerFactory)(array_merge([
        'url' => 'https://hooks.slack.com/services/T/B/x',
        'level' => 'error',
    ], $overrides));
}

/**
 * Slack への送信ハンドラを TestHandler に差し替えたチャンネル。
 * 「何回送られたか」を数えるために使う。
 */
function slackLoggerSendingTo(TestHandler $handler, string $store): Logger
{
    $factory = new class($handler) extends SlackAlertLoggerFactory
    {
        public function __construct(private TestHandler $testHandler) {}

        protected function makeSlackHandler(array $config, string $url, mixed $level): HandlerInterface
        {
            return $this->testHandler;
        }
    };

    return $factory([
        'url' => 'https://hooks.slack.com/services/T/B/x',
        'level' => 'error',
        'dedup_store' => $store,
    ]);
}

/**
 * BufferHandler::$bufferSize も非公開なのでリフレクションで読む。
 */
function bufferSizeOf(DeduplicationHandler $dedup): int
{
    return (new ReflectionProperty($dedup, 'bufferSize'))->getValue($dedup);
}

it('webhook URL が無いときは何もしないハンドラだけを持つ', function () {
    $logger = (new SlackAlertLoggerFactory)(['url' => null, 'level' => 'error']);

    expect($logger)->toBeInstanceOf(Logger::class);
    expect($logger->getHandlers())->toHaveCount(1);
    expect($logger->getHandlers()[0])->toBeInstanceOf(NullHandler::class);
});

it('webhook URL が空文字でも何もしないハンドラだけを持つ', function () {
    $logger = (new SlackAlertLoggerFactory)(['url' => '', 'level' => 'error']);

    expect($logger->getHandlers()[0])->toBeInstanceOf(NullHandler::class);
});

it('URL 未設定の slack チャンネルへ error を書いても例外にならない', function () {
    config(['logging.channels.slack.url' => null]);

    Log::channel('slack')->error('boom');

    expect(true)->toBeTrue();
});

it('Slack への送信が失敗してもログ呼び出し元に例外が出ない', function () {
    // 127.0.0.1:1 は接続拒否される。
    config(['logging.channels.slack.url' => 'http://127.0.0.1:1/services/T/B/x']);

    $channel = Log::channel('slack');
    // 重複排除ストアは実ファイルなので、再実行時に送信経路が抑制されないよう毎回違う本文にする。
    $channel->error('接続できないケース '.uniqid());
    // 実際の送信は重複排除ハンドラの flush() で行われるため、ここで明示的に流す。
    $channel->getLogger()->close();

    // flush() は handleBatch() が例外なく戻ったときだけ clear() まで到達する。
    // 外側の WhatFailureGroupHandler は close() の例外も飲んでしまうため、
    // 「バッファが空になった」ことが内側で例外を捕まえた唯一の証拠になる。
    expect(bufferSizeOf(dedupHandlerOf($channel->getLogger())))->toBe(0);
});

it('error 未満は Slack に送らない', function () {
    $logger = (new SlackAlertLoggerFactory)([
        'url' => 'https://hooks.slack.com/services/T/B/x',
        'level' => 'error',
    ]);
    $slack = slackHandlerOf($logger);

    expect($slack->getLevel())->toBe(Level::Error);
    expect($slack->isHandling(new LogRecord(
        new DateTimeImmutable, 'testing', Level::Warning, '警告は送らない',
    )))->toBeFalse();
});

it('context と extra を Slack ペイロードに含めない', function () {
    $logger = (new SlackAlertLoggerFactory)([
        'url' => 'https://hooks.slack.com/services/T/B/x',
        'level' => 'error',
    ]);
    $record = slackHandlerOf($logger)->getSlackRecord()->getSlackData(
        new LogRecord(
            new DateTimeImmutable,
            'testing',
            Level::Error,
            'DB接続に失敗',
            ['token' => 'secret-token-value', 'email' => 'user@example.com'],
        ),
    );

    expect(json_encode($record))->not->toContain('secret-token-value');
    expect(json_encode($record))->not->toContain('user@example.com');
});

it('error 未満は重複排除ハンドラのバッファにも溜めない', function () {
    $logger = (new SlackAlertLoggerFactory)([
        'url' => 'https://hooks.slack.com/services/T/B/x',
        'level' => 'error',
    ]);
    $dedup = dedupHandlerOf($logger);

    // DeduplicationHandler の既定は level = Debug / bufferLimit = 0（無制限）なので、
    // レベルを上げていないと debug ログまで全件メモリに溜まり続ける。
    expect($dedup->getLevel())->toBe(Level::Error);
    expect($dedup->isHandling(new LogRecord(
        new DateTimeImmutable, 'testing', Level::Warning, '警告は溜めない',
    )))->toBeFalse();
});

it('メッセージ中のプレースホルダを展開し、参照していない context は送らない', function () {
    $logger = (new SlackAlertLoggerFactory)([
        'url' => 'https://hooks.slack.com/services/T/B/x',
        'level' => 'error',
    ]);

    // 展開してから本文を削る順序（PSR → サニタイザ）でなければならない。
    expect($logger->getProcessors())->toHaveCount(2);
    expect($logger->getProcessors()[0])->toBeInstanceOf(PsrLogMessageProcessor::class);
    expect($logger->getProcessors()[1])->toBeInstanceOf(SlackMessageSanitizer::class);

    // Logger::addRecord はハンドラへ渡す前にプロセッサを適用する。送信せずに同じ変換を再現する。
    $processed = ($logger->getProcessors()[0])(new LogRecord(
        new DateTimeImmutable,
        'testing',
        Level::Error,
        'ユーザー {id} の処理に失敗',
        ['id' => 42, 'token' => 'secret-token-value'],
    ));

    $payload = json_encode(
        slackHandlerOf($logger)->getSlackRecord()->getSlackData($processed),
        JSON_UNESCAPED_UNICODE,
    );

    expect($payload)->toContain('ユーザー 42 の処理に失敗');
    expect($payload)->not->toContain('secret-token-value');
});

it('ハンドラの入れ子が WhatFailureGroup → Deduplication → WhatFailureGroup → OncePerProcess である', function () {
    $logger = slackLogger();

    expect($logger->getHandlers()[0])->toBeInstanceOf(WhatFailureGroupHandler::class);
    expect(dedupHandlerOf($logger))->toBeInstanceOf(DeduplicationHandler::class);
    expect(oncePerProcessHandlerOf($logger))->toBeInstanceOf(OncePerProcessHandler::class);
    expect(slackHandlerOf($logger))->toBeInstanceOf(SlackAlertWebhookHandler::class);
});

it('1プロセス内の同一メッセージは1回だけ送る', function () {
    OncePerProcessHandler::forgetSentRecords();
    $store = tempnam(sys_get_temp_dir(), 'slack-dedup-');
    $sent = new TestHandler;
    $logger = slackLoggerSendingTo($sent, $store);

    try {
        $logger->error('DBに接続できません');
        $logger->error('DBに接続できません');
        $logger->error('DBに接続できません');
        // DeduplicationHandler は flush() まで送信しない。
        $logger->close();

        // DeduplicationHandler::flush() は送ると決めたらバッファ全体を handleBatch() へ渡すので、
        // OncePerProcessHandler が無ければここは 3 件になる。
        expect($sent->getRecords())->toHaveCount(1);
        expect($sent->getRecords()[0]->message)->toBe('DBに接続できません');
    } finally {
        @unlink($store);
    }
});

it('別プロセスでも同一メッセージは300秒のあいだ送らない', function () {
    $store = tempnam(sys_get_temp_dir(), 'slack-dedup-');

    try {
        OncePerProcessHandler::forgetSentRecords();
        $first = new TestHandler;
        $firstLogger = slackLoggerSendingTo($first, $store);
        $firstLogger->error('ディスクが一杯です');
        $firstLogger->close();

        expect($first->getRecords())->toHaveCount(1);

        // プロセス内の記憶を捨てる。ここから先の抑制はストアファイルだけが根拠になる。
        OncePerProcessHandler::forgetSentRecords();
        $second = new TestHandler;
        $secondLogger = slackLoggerSendingTo($second, $store);
        $secondLogger->error('ディスクが一杯です');
        $secondLogger->close();

        expect($second->getRecords())->toHaveCount(0);

        // 別のメッセージは抑制されない（抑制が本文で判定されていることの確認）。
        OncePerProcessHandler::forgetSentRecords();
        $third = new TestHandler;
        $thirdLogger = slackLoggerSendingTo($third, $store);
        $thirdLogger->error('別の障害');
        $thirdLogger->close();

        expect($third->getRecords())->toHaveCount(1);
    } finally {
        @unlink($store);
    }
});

it('送信のタイムアウトと接続タイムアウトを curl に設定する', function () {
    $options = slackHandlerOf(slackLogger())->curlOptions(new LogRecord(
        new DateTimeImmutable, 'testing', Level::Error, 'タイムアウト確認',
    ));

    // 親の SlackWebhookHandler::write() はこの2つを設定しないため、
    // curl の既定（接続 300 秒・全体は無制限）でリクエストがぶら下がる。
    expect($options[CURLOPT_TIMEOUT])->toBe(5);
    expect($options[CURLOPT_CONNECTTIMEOUT])->toBe(2);
    expect($options[CURLOPT_URL])->toBe('https://hooks.slack.com/services/T/B/x');
    expect($options[CURLOPT_POSTFIELDS])->toContain('タイムアウト確認');
});

it('送信は失敗しても1回しか試さない', function () {
    // Monolog\Handler\Curl\Util::execute() の既定は 5 回。write() が再試行しないことを数える。
    $handler = new class('https://hooks.slack.com/services/T/B/x') extends SlackAlertWebhookHandler
    {
        public int $attempts = 0;

        public function writeRecord(LogRecord $record): void
        {
            $this->write($record);
        }

        protected function send(array $options): void
        {
            $this->attempts++;

            throw new RuntimeException('送信失敗');
        }
    };

    $write = fn () => $handler->writeRecord(new LogRecord(
        new DateTimeImmutable, 'testing', Level::Error, '再試行しない',
    ));

    expect($write)->toThrow(RuntimeException::class);
    expect($handler->attempts)->toBe(1);
});

it('QueryException のバインド値と接続先を Slack へ送らない', function () {
    $exception = new QueryException(
        'pgsql',
        'select * from password_reset_tokens where token = ?',
        ['tok_live_SECRETVALUE'],
        new PDOException('SQLSTATE[08006] connection timed out'),
        [
            'driver' => 'pgsql',
            'host' => 'db.internal.example',
            'port' => 5432,
            'database' => 'guide_production',
        ],
    );

    // Laravel の例外ハンドラは例外メッセージをそのままログ本文にする。
    expect($exception->getMessage())
        ->toContain('tok_live_SECRETVALUE')
        ->toContain('db.internal.example')
        ->toContain('guide_production');

    $payload = renderSlackPayload(slackLogger(), new LogRecord(
        new DateTimeImmutable,
        'testing',
        Level::Error,
        $exception->getMessage(),
        ['exception' => $exception],
    ));

    expect($payload)->not->toContain('tok_live_SECRETVALUE');
    expect($payload)->not->toContain('password_reset_tokens');
    expect($payload)->not->toContain('db.internal.example');
    expect($payload)->not->toContain('Port: 5432');
    expect($payload)->not->toContain('guide_production');
    expect($payload)->not->toContain('Connection:');

    // 何が起きたかは残す。
    expect($payload)->toContain('connection timed out');
    expect($payload)->toContain('SQL詳細はログ参照');
});

it('長いメッセージは300文字で切る', function () {
    $payload = renderSlackPayload(slackLogger(), new LogRecord(
        new DateTimeImmutable, 'testing', Level::Error, str_repeat('あ', 500),
    ));

    expect($payload)->toContain(str_repeat('あ', 300));
    expect($payload)->not->toContain(str_repeat('あ', 301));
    expect($payload)->toContain('以下省略');
});

it('プレースホルダ展開後の本文もサニタイズの対象にする', function () {
    // 展開前は本文に SQL が無いため、サニタイザが PsrLogMessageProcessor より
    // 先に動く順序だと、この値はそのまま Slack へ流れる。
    $payload = renderSlackPayload(slackLogger(), new LogRecord(
        new DateTimeImmutable,
        'testing',
        Level::Error,
        'クエリ失敗: {reason}',
        ['reason' => 'timeout (Connection: pgsql, Host: db.internal.example, Database: guide_production, SQL: select * from users where email = leak@example.com)'],
    ));

    expect($payload)->toContain('クエリ失敗: timeout');
    expect($payload)->not->toContain('leak@example.com');
    expect($payload)->not->toContain('db.internal.example');
});
