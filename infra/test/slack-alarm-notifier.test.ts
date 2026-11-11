import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';
import { createHandler } from '../lambda/slack-alarm-notifier/index.mjs';

const alarmMessage = {
  AlarmName: 'guide-production-no-healthy-targets',
  AlarmDescription: null,
  NewStateValue: 'ALARM',
  NewStateReason: 'Threshold Crossed',
  StateChangeTime: '2026-11-10T00:00:00.000+0000',
  Region: 'Asia Pacific (Tokyo)',
  AWSAccountId: '123456789012',
};

function snsEvent(message: unknown) {
  return {
    Records: [{ Sns: { Message: typeof message === 'string' ? message : JSON.stringify(message) } }],
  };
}

const secretArn = 'arn:aws:secretsmanager:ap-northeast-1:123456789012:secret:slack-AbCdEf';
const webhookSecret = JSON.stringify({ url: 'https://hooks.slack.com/services/T/B/x' });

describe('slack-alarm-notifier', () => {
  // Secrets Manager の代わりに、テストごとに差し替えられる読み込み関数を渡す。
  let loadSecret: ReturnType<typeof vi.fn<(arn: string) => Promise<string | undefined>>>;
  let handler: (event: unknown) => Promise<void>;

  beforeEach(() => {
    process.env.SLACK_WEBHOOK_SECRET_ARN = secretArn;
    loadSecret = vi.fn(async () => webhookSecret);
    handler = createHandler(loadSecret);
    vi.stubGlobal('fetch', vi.fn(async () => new Response('ok', { status: 200 })));
    // 送信失敗が console.error に記録されることを確認するため、出力を差し替える。
    vi.spyOn(console, 'error').mockImplementation(() => {});
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  test('アラーム名と状態と理由を Slack へ送る', async () => {
    await handler(snsEvent(alarmMessage));

    const [url, init] = (globalThis.fetch as any).mock.calls[0];
    expect(url).toBe('https://hooks.slack.com/services/T/B/x');
    const body = JSON.parse(init.body);
    expect(JSON.stringify(body)).toContain('guide-production-no-healthy-targets');
    expect(JSON.stringify(body)).toContain('ALARM');
    expect(JSON.stringify(body)).toContain('Threshold Crossed');
  });

  test('OK 復帰は緑色で復旧として送る', async () => {
    await handler(snsEvent({ ...alarmMessage, NewStateValue: 'OK' }));

    const body = JSON.parse((globalThis.fetch as any).mock.calls[0][1].body);
    const attachment = body.attachments[0];
    expect(attachment.color).toBe('#2d7d46');
    expect(attachment.text).toContain('復旧');
    expect(JSON.stringify(body)).toContain('OK');
  });

  test('ALARM は赤色で発生として送り、復旧とは書かない', async () => {
    await handler(snsEvent(alarmMessage));

    const body = JSON.parse((globalThis.fetch as any).mock.calls[0][1].body);
    const attachment = body.attachments[0];
    expect(attachment.color).toBe('#d13212');
    expect(attachment.text).not.toContain('復旧');
    expect(attachment.text).toContain('発生');
  });

  test('JSON でない本文はそのまま転送して落ちない', async () => {
    await handler(snsEvent('アラームではない素のテキスト'));

    const body = JSON.parse((globalThis.fetch as any).mock.calls[0][1].body);
    expect(JSON.stringify(body)).toContain('アラームではない素のテキスト');
  });

  test('Secret の ARN が未設定なら送信しない', async () => {
    delete process.env.SLACK_WEBHOOK_SECRET_ARN;

    await handler(snsEvent(alarmMessage));

    expect(loadSecret).not.toHaveBeenCalled();
    expect(globalThis.fetch).not.toHaveBeenCalled();
  });

  test('Webhook URL は環境変数からではなく Secret から読む', async () => {
    process.env.SLACK_WEBHOOK_URL = 'https://hooks.slack.com/services/ENV/ENV/env';
    try {
      await handler(snsEvent(alarmMessage));
    } finally {
      delete process.env.SLACK_WEBHOOK_URL;
    }

    expect(loadSecret).toHaveBeenCalledWith(secretArn);
    expect((globalThis.fetch as any).mock.calls[0][0]).toBe('https://hooks.slack.com/services/T/B/x');
  });

  // デプロイ直後の Secret には仮の値が入っている。URL を入れるまでは何も送らない。
  test.each([
    ['仮の値', JSON.stringify({ url: 'unset', placeholder: 'x' })],
    ['url キーが無い', JSON.stringify({ other: 'https://hooks.slack.com/services/T/B/x' })],
    ['JSON でない', 'https://hooks.slack.com/services/T/B/x'],
    ['空', undefined],
  ])('Secret に URL が入っていなければ送信しない（%s）', async (_label, secretString) => {
    loadSecret.mockResolvedValue(secretString);

    await expect(handler(snsEvent(alarmMessage))).resolves.toBeUndefined();

    expect(globalThis.fetch).not.toHaveBeenCalled();
  });

  test('Secret を読めなくても例外を投げず、次の通知で読み直す', async () => {
    loadSecret.mockRejectedValueOnce(new Error('AccessDenied'));

    await expect(handler(snsEvent(alarmMessage))).resolves.toBeUndefined();
    expect(globalThis.fetch).not.toHaveBeenCalled();

    await handler(snsEvent(alarmMessage));
    expect(loadSecret).toHaveBeenCalledTimes(2);
    expect(globalThis.fetch).toHaveBeenCalledTimes(1);
  });

  test('Secret は通知のたびには読まず、5 分を過ぎたら読み直す', async () => {
    vi.useFakeTimers();
    try {
      await handler(snsEvent(alarmMessage));
      await handler(snsEvent(alarmMessage));
      expect(loadSecret).toHaveBeenCalledTimes(1);

      vi.advanceTimersByTime(5 * 60 * 1000);
      await handler(snsEvent(alarmMessage));
      expect(loadSecret).toHaveBeenCalledTimes(2);
    } finally {
      vi.useRealTimers();
    }
  });

  test('Records が配列でないイベントでも落ちず、送信しない', async () => {
    await expect(
      handler({ Records: { 0: { Sns: { Message: '{}' } } } }),
    ).resolves.toBeUndefined();
    await expect(handler({})).resolves.toBeUndefined();
    await expect(handler(undefined)).resolves.toBeUndefined();

    expect(globalThis.fetch).not.toHaveBeenCalled();
  });

  test('Slack が 500 を返しても例外を投げず、状態コードを記録する', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => new Response('err', { status: 500 })));

    await expect(handler(snsEvent(alarmMessage))).resolves.toBeUndefined();

    expect(console.error).toHaveBeenCalledWith(expect.stringContaining('500'));
  });

  test('fetch が例外を投げても握りつぶして記録する', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => {
        throw new Error('boom');
      }),
    );

    await expect(handler(snsEvent(alarmMessage))).resolves.toBeUndefined();

    expect(console.error).toHaveBeenCalledWith(
      'Slack webhook request failed',
      expect.any(Error),
    );
  });

  // 経過時間そのものは偽クロックなしには測れないため、「中断シグナルが実際の
  // リクエストに付いているか」を見る。定数を読むだけでは、signal を渡し忘れても気づけない。
  test('送信リクエストに中断シグナルを付ける', async () => {
    await handler(snsEvent(alarmMessage));

    const [, init] = (globalThis.fetch as any).mock.calls[0];
    expect(init.signal).toBeInstanceOf(AbortSignal);
    expect(init.signal.aborted).toBe(false);
  });
});
