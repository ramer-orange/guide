import { beforeEach, describe, expect, test, vi } from 'vitest';
import { handler } from '../lambda/slack-alarm-notifier/index.mjs';

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

  test('OK 復帰は復旧として送る', async () => {
    await handler(snsEvent({ ...alarmMessage, NewStateValue: 'OK' }));

    const body = JSON.parse((globalThis.fetch as any).mock.calls[0][1].body);
    expect(JSON.stringify(body)).toContain('OK');
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

  test('Slack が 500 を返しても例外を投げない', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => new Response('err', { status: 500 })));

    await expect(handler(snsEvent(alarmMessage))).resolves.toBeUndefined();
  });
});
