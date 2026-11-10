import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';
import { SEND_TIMEOUT_MS, handler } from '../lambda/slack-alarm-notifier/index.mjs';

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

describe('slack-alarm-notifier', () => {
  beforeEach(() => {
    process.env.SLACK_WEBHOOK_URL = 'https://hooks.slack.com/services/T/B/x';
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

  test('Webhook URL 未設定なら送信しない', async () => {
    delete process.env.SLACK_WEBHOOK_URL;

    await handler(snsEvent(alarmMessage));

    expect(globalThis.fetch).not.toHaveBeenCalled();
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

  // タイムアウトの経過自体は偽クロックなしには検証できないため、
  // 機能の制約である 5 秒という値だけを固定する（実際の中断は AbortSignal.timeout に任せる）。
  test('送信のタイムアウトは 5 秒', () => {
    expect(SEND_TIMEOUT_MS).toBe(5000);
  });
});
