import { describe, expect, test } from 'vitest';
import * as cdk from 'aws-cdk-lib';
import { Annotations, Match, Template } from 'aws-cdk-lib/assertions';
import { GovernanceStack } from '../lib/governance-stack';
import { GuideStack } from '../lib/guide-stack';

const env = { account: '123456789012', region: 'ap-northeast-1' };
// AWS への問い合わせを避けるため、AZ の検索結果を固定する。
const lookupContext = {
  'availability-zones:account=123456789012:region=ap-northeast-1': [
    'ap-northeast-1a',
    'ap-northeast-1c',
  ],
};

function makeGuideStack(context: Record<string, unknown> = { appImageTag: 'abc123' }) {
  const app = new cdk.App({ context: { ...lookupContext, ...context } });
  return new GuideStack(app, 'TestStack', { env });
}

function makeTemplate(context?: Record<string, unknown>): Template {
  return Template.fromStack(makeGuideStack(context));
}

describe('GuideStack', () => {
  test('appImageTag が無いとデプロイできない', () => {
    const stack = makeGuideStack({});
    Annotations.fromStack(stack).hasError(
      '*',
      Match.stringLikeRegexp('appImageTag is required'),
    );
  });

  test('latest タグは使えない', () => {
    const stack = makeGuideStack({ appImageTag: 'latest' });
    Annotations.fromStack(stack).hasError(
      '*',
      Match.stringLikeRegexp('immutable tag'),
    );
  });

  test('初回構築（desiredCount=0）はタグ無しで作れる', () => {
    const stack = makeGuideStack({ desiredCount: 0 });
    Annotations.fromStack(stack).hasNoError('*', Match.anyValue());
    const template = Template.fromStack(stack);
    template.hasResourceProperties('AWS::ECS::Service', { DesiredCount: 0 });
    template.hasResourceProperties('AWS::ApplicationAutoScaling::ScalableTarget', {
      MinCapacity: 0,
    });
  });

  test('通常デプロイではタスク数を変更せず、最小 2 タスクを維持する', () => {
    const template = makeTemplate();
    const services = template.findResources('AWS::ECS::Service');
    for (const service of Object.values(services)) {
      expect(service.Properties.DesiredCount).toBeUndefined();
    }
    template.hasResourceProperties('AWS::ApplicationAutoScaling::ScalableTarget', {
      MinCapacity: 2,
      MaxCapacity: 6,
    });
  });

  test('Web タスクは起動時に migrate しない', () => {
    const template = makeTemplate();
    template.hasResourceProperties('AWS::ECS::TaskDefinition', {
      Family: 'guide-production-application',
      ContainerDefinitions: Match.arrayWith([
        Match.objectLike({
          Environment: Match.arrayWith([
            { Name: 'RUN_MIGRATIONS_ON_START', Value: 'false' },
          ]),
          HealthCheck: Match.objectLike({
            Command: ['CMD-SHELL', 'curl --fail --silent http://127.0.0.1/healthz || exit 1'],
          }),
        }),
      ]),
    });
  });

  test('ALB は /healthz で確認し、HTTP を HTTPS へリダイレクトする', () => {
    const template = makeTemplate();
    template.hasResourceProperties('AWS::ElasticLoadBalancingV2::TargetGroup', {
      HealthCheckPath: '/healthz',
    });
    template.hasResourceProperties('AWS::ElasticLoadBalancingV2::Listener', {
      Port: 80,
      DefaultActions: [
        Match.objectLike({
          Type: 'redirect',
          RedirectConfig: Match.objectLike({ Protocol: 'HTTPS', Port: '443', StatusCode: 'HTTP_301' }),
        }),
      ],
    });
  });

  test('ECR のタグは上書きできない', () => {
    makeTemplate().hasResourceProperties('AWS::ECR::Repository', {
      ImageTagMutability: 'IMMUTABLE',
    });
  });

  test('RDS は t4g.small・Multi-AZ・Performance Insights 有効', () => {
    makeTemplate().hasResourceProperties('AWS::RDS::DBInstance', {
      DBInstanceClass: 'db.t4g.small',
      MultiAZ: true,
      EnablePerformanceInsights: true,
      PubliclyAccessible: false,
    });
  });

  test('Interface Endpoint は既定で作らず、指定時だけ作る', () => {
    const interfaceEndpoints = (template: Template) =>
      Object.values(template.findResources('AWS::EC2::VPCEndpoint')).filter(
        (endpoint) => endpoint.Properties.VpcEndpointType === 'Interface',
      ).length;
    expect(interfaceEndpoints(makeTemplate())).toBe(0);
    expect(
      interfaceEndpoints(
        makeTemplate({ appImageTag: 'abc123', enableInterfaceEndpoints: true }),
      ),
    ).toBe(5);
  });

  test('WAF に SQLi ルールとログ出力がある', () => {
    const template = makeTemplate();
    template.hasResourceProperties('AWS::WAFv2::WebACL', {
      Rules: Match.arrayWith([
        Match.objectLike({ Name: 'AWSManagedRulesSQLiRuleSet' }),
      ]),
    });
    template.hasResourceProperties('AWS::WAFv2::LoggingConfiguration', {
      RedactedFields: Match.arrayWith([{ SingleHeader: { Name: 'cookie' } }]),
    });
  });

  test('通知先メールは必須で、障害と復旧の両方を通知する', () => {
    const template = makeTemplate();
    template.hasParameter('AlertEmail', { Type: 'String' });
    template.hasResourceProperties('AWS::SNS::Subscription', { Protocol: 'email' });
    for (const name of [
      'guide-production-elb-5xx',
      'guide-production-no-healthy-targets',
      'guide-production-low-database-storage',
      'guide-production-low-database-memory',
      'guide-production-high-database-connections',
      'guide-production-low-database-cpu-credit',
      'guide-production-health-check-failed',
    ]) {
      template.hasResourceProperties('AWS::CloudWatch::Alarm', { AlarmName: name });
    }
    const alarms = Object.values(template.findResources('AWS::CloudWatch::Alarm'));
    for (const alarm of alarms) {
      expect(alarm.Properties.AlarmActions).toHaveLength(1);
      expect(alarm.Properties.OKActions).toHaveLength(1);
    }
    template.hasResourceProperties('AWS::CloudWatch::Alarm', {
      AlarmName: 'guide-production-no-healthy-targets',
      TreatMissingData: 'breaching',
    });
  });

  test('外形監視の Canary と AWS Backup がある', () => {
    const template = makeTemplate();
    template.hasResourceProperties('AWS::Synthetics::Canary', {
      Name: 'guide-prod-health',
    });
    template.hasResourceProperties('AWS::Backup::BackupPlan', {
      BackupPlan: Match.objectLike({
        BackupPlanRule: Match.arrayWith([
          Match.objectLike({ RuleName: 'Daily', Lifecycle: { DeleteAfterDays: 35 } }),
          Match.objectLike({ RuleName: 'Monthly', Lifecycle: { DeleteAfterDays: 365 } }),
        ]),
      }),
    });
  });

  test('既定では Slack 通知のリソースを作らない', () => {
    const template = makeTemplate();
    expect(
      Object.keys(template.findResources('AWS::SNS::Subscription', {
        Properties: { Protocol: 'lambda' },
      })),
    ).toHaveLength(0);
    expect(Object.keys(template.findParameters('SlackWebhookUrl'))).toHaveLength(0);
  });

  test('enableSlackAlerts で SNS を購読する Lambda と NoEcho パラメータを作る', () => {
    const template = makeTemplate({ appImageTag: 'abc123', enableSlackAlerts: true });

    template.hasParameter('SlackWebhookUrl', { NoEcho: true });
    template.hasResourceProperties('AWS::Lambda::Function', {
      Runtime: 'nodejs22.x',
      Timeout: 10,
      Environment: { Variables: { SLACK_WEBHOOK_URL: { Ref: 'SlackWebhookUrl' } } },
    });
    expect(
      Object.keys(template.findResources('AWS::SNS::Subscription', {
        Properties: { Protocol: 'lambda' },
      })),
    ).toHaveLength(1);
  });

  test('すべてのアラームが復旧時も通知する', () => {
    const template = makeTemplate();

    // 既存の this.node.findAll() ループが全 Alarm に OK アクションを付けている。
    // Slack 通知を足したあともこの性質が保たれることの回帰ガード。
    const alarms = template.findResources('AWS::CloudWatch::Alarm');
    expect(Object.keys(alarms)).toHaveLength(10);
    for (const [id, alarm] of Object.entries(alarms)) {
      expect(alarm.Properties.OKActions, id).toBeDefined();
      expect(alarm.Properties.AlarmActions, id).toBeDefined();
    }
  });

  test('ECS タスクに Slack ログ設定を渡す', () => {
    const template = makeTemplate();

    template.hasResourceProperties('AWS::ECS::TaskDefinition', {
      ContainerDefinitions: Match.arrayWith([
        Match.objectLike({
          Environment: Match.arrayWith([
            // slack チャンネルは stack 経由でしか効かないため LOG_CHANNEL は stack にする。
            { Name: 'LOG_CHANNEL', Value: 'stack' },
            { Name: 'LOG_STACK', Value: 'stderr,slack' },
            { Name: 'LOG_SLACK_LEVEL', Value: 'error' },
          ]),
        }),
      ]),
    });
  });

  test('enableSlackAlerts で PHP と Next.js の両方に webhook を渡す', () => {
    const template = makeTemplate({ appImageTag: 'abc123', enableSlackAlerts: true });

    // Web タスクは 1 コンテナで PHP-FPM と Next.js を動かすため、両方の変数名が必要。
    template.hasResourceProperties('AWS::ECS::TaskDefinition', {
      ContainerDefinitions: Match.arrayWith([
        Match.objectLike({
          Secrets: Match.arrayWith([
            Match.objectLike({ Name: 'LOG_SLACK_WEBHOOK_URL' }),
            Match.objectLike({ Name: 'SLACK_WEBHOOK_URL' }),
          ]),
        }),
      ]),
    });
  });

  test('GuardDuty と AWS Config はアプリのスタックに含めない', () => {
    const template = makeTemplate();
    template.resourceCountIs('AWS::GuardDuty::Detector', 0);
    template.resourceCountIs('AWS::Config::ConfigurationRecorder', 0);
  });
});

describe('GovernanceStack', () => {
  test('GuardDuty と AWS Config を作る', () => {
    const app = new cdk.App();
    const template = Template.fromStack(new GovernanceStack(app, 'Gov', { env }));
    template.resourceCountIs('AWS::GuardDuty::Detector', 1);
    template.resourceCountIs('AWS::Config::ConfigurationRecorder', 1);
    template.hasParameter('AlertEmail', { Type: 'String' });
  });

  test('既に有効な機能は context で外せる', () => {
    const app = new cdk.App({
      context: { enableGuardDuty: false, enableConfig: false },
    });
    const template = Template.fromStack(new GovernanceStack(app, 'Gov', { env }));
    template.resourceCountIs('AWS::GuardDuty::Detector', 0);
    template.resourceCountIs('AWS::Config::ConfigurationRecorder', 0);
  });
});
