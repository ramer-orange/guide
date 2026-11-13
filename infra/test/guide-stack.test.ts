import { describe, expect, test } from 'vitest';
import * as cdk from 'aws-cdk-lib';
import { Annotations, Match, Template } from 'aws-cdk-lib/assertions';
import {
  environments,
  GuideEnvironmentConfig,
} from '../lib/environments';
import { GovernanceStack } from '../lib/governance-stack';
import { GuideDataStack } from '../lib/guide-data-stack';
import { GuideStack } from '../lib/guide-stack';

const env = { account: '123456789012', region: 'ap-northeast-1' };
// AWS への問い合わせを避けるため、AZ の検索結果を固定する。
const lookupContext = {
  'availability-zones:account=123456789012:region=ap-northeast-1': [
    'ap-northeast-1a',
    'ap-northeast-1c',
  ],
};

function makeStacks(
  context: Record<string, unknown> = { appImageTag: 'abc123' },
  config: GuideEnvironmentConfig = environments.production,
) {
  const app = new cdk.App({ context: { ...lookupContext, ...context } });
  const data = new GuideDataStack(app, 'TestDataStack', { env, config });
  const stack = new GuideStack(app, 'TestStack', { env, config, data });
  return { app, data, stack };
}

function makeGuideStack(context?: Record<string, unknown>) {
  return makeStacks(context).stack;
}

function makeTemplate(context?: Record<string, unknown>): Template {
  return Template.fromStack(makeGuideStack(context));
}

function makeDataTemplate(
  config: GuideEnvironmentConfig = environments.production,
): Template {
  return Template.fromStack(makeStacks(undefined, config).data);
}

/** Family が一致するタスク定義の先頭コンテナを返す。 */
function containerOf(template: Template, family: string) {
  const definitions = Object.values(
    template.findResources('AWS::ECS::TaskDefinition', {
      Properties: { Family: family },
    }),
  );
  expect(definitions).toHaveLength(1);
  return definitions[0].Properties.ContainerDefinitions[0];
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
      MaxCapacity: 3,
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
    makeDataTemplate().hasResourceProperties('AWS::ECR::Repository', {
      ImageTagMutability: 'IMMUTABLE',
    });
  });

  test('RDS は t4g.small・Multi-AZ・Performance Insights 有効', () => {
    makeDataTemplate().hasResourceProperties('AWS::RDS::DBInstance', {
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

  test('外形監視の Canary は 5 分間隔で、アラームの period も同じ', () => {
    const template = makeTemplate();
    template.hasResourceProperties('AWS::Synthetics::Canary', {
      Name: 'guide-prod-health',
      Schedule: Match.objectLike({ Expression: 'rate(5 minutes)' }),
    });
    // 実行間隔より短い period にすると、データの無い期間が BREACHING に拾われて誤報になる。
    template.hasResourceProperties('AWS::CloudWatch::Alarm', {
      AlarmName: 'guide-production-health-check-failed',
      Period: 300,
      TreatMissingData: 'breaching',
    });
  });

  test('AWS Backup は日次 35 日・月次 1 年で保管する', () => {
    makeDataTemplate().hasResourceProperties('AWS::Backup::BackupPlan', {
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
    template.resourceCountIs('AWS::SecretsManager::Secret', 0);
    template.resourceCountIs('AWS::Lambda::Function', 0);
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

    // Web タスクに入っていることを確かめる。Family を指定しないと
    // Migration タスクだけが一致していても通ってしまう。
    template.hasResourceProperties('AWS::ECS::TaskDefinition', {
      Family: 'guide-production-application',
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
      Family: 'guide-production-application',
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

  test('課金が量に比例する設定を絞っている', () => {
    const template = makeTemplate();

    // Container Insights はカスタムメトリクスの追加課金になるため切る。
    template.hasResourceProperties('AWS::ECS::Cluster', {
      ClusterSettings: Match.arrayWith([
        { Name: 'containerInsights', Value: 'disabled' },
      ]),
    });

    // WAF ログは遮断した判定だけ。許可まで残すと全リクエストが課金対象になる。
    template.hasResourceProperties('AWS::WAFv2::LoggingConfiguration', {
      LoggingFilter: {
        DefaultBehavior: 'DROP',
        Filters: [
          Match.objectLike({
            Behavior: 'KEEP',
            Conditions: [{ ActionCondition: { Action: 'BLOCK' } }],
          }),
        ],
      },
    });

    // ECR は1イメージが大きいが、稼働中イメージの失効を避けて余裕を持たせる。
    makeDataTemplate().hasResourceProperties('AWS::ECR::Repository', {
      LifecyclePolicy: {
        LifecyclePolicyText: Match.stringLikeRegexp('"countNumber":20'),
      },
    });
  });

  test('アカウントに 1 つだけのものはアプリのスタックに含めない', () => {
    for (const template of [makeTemplate(), makeDataTemplate()]) {
      template.resourceCountIs('AWS::GuardDuty::Detector', 0);
      template.resourceCountIs('AWS::Config::ConfigurationRecorder', 0);
      template.resourceCountIs('AWS::CloudTrail::Trail', 0);
      template.resourceCountIs('Custom::AWSCDKOpenIdConnectProvider', 0);
    }
  });

  test('消えると戻せないものはアプリのスタックに置かない', () => {
    const template = makeTemplate({ appImageTag: 'abc123', enableSlackAlerts: true });
    for (const type of [
      'AWS::EC2::VPC',
      'AWS::RDS::DBInstance',
      'AWS::ECR::Repository',
      'AWS::Backup::BackupVault',
    ]) {
      template.resourceCountIs(type, 0);
    }
    // S3 は Canary の実行結果（30 日で失効）を置くバケットだけ。添付ファイルと監査ログは無い。
    template.resourceCountIs('AWS::S3::Bucket', 1);
  });

  test('CloudFormation のパラメータで受け取る秘密の値は Slack の Webhook URL だけ', () => {
    const { data, stack } = makeStacks({ appImageTag: 'abc123', enableSlackAlerts: true });
    // データ側のスタックはパラメータで何も受け取らない（Google OAuth は Secret へ直接入れる）。
    expect(Object.keys(Template.fromStack(data).findParameters('*'))).toEqual(['BootstrapVersion']);

    const parameters = Template.fromStack(stack).findParameters('*');
    const noEcho = Object.entries(parameters)
      .filter(([, parameter]) => parameter.NoEcho)
      .map(([name]) => name);
    expect(noEcho).toEqual(['SlackWebhookUrl']);
    expect(Object.keys(parameters).join(',')).not.toMatch(/google|password/i);
  });

  test('Web タスクは権限を絞った DB ユーザーで接続し、マスターユーザーの Secret を読めない', () => {
    const { data, stack } = makeStacks();
    const template = Template.fromStack(stack);
    const dataTemplate = Template.fromStack(data);

    // マスターユーザーは guide_admin で、アプリ用の guide_app とは別の Secret を持つ。
    dataTemplate.hasResourceProperties('AWS::RDS::DBInstance', {
      MasterUsername: 'guide_admin',
    });

    const secretOf = (family: string, name: string) =>
      JSON.stringify(
        containerOf(template, family).Secrets.find(
          (secret: { Name: string }) => secret.Name === name,
        ),
      );
    // Web はアプリ用ユーザーの Secret、Migration はマスターユーザーの Secret を参照する。
    expect(secretOf('guide-production-application', 'DB_PASSWORD')).toContain('DatabaseAppSecret');
    expect(secretOf('guide-production-application', 'DB_PASSWORD')).not.toContain('DatabaseSecretAttachment');
    expect(secretOf('guide-production-migration', 'DB_PASSWORD')).toContain('DatabaseSecretAttachment');
    expect(secretOf('guide-production-migration', 'DB_APP_PASSWORD')).toContain('DatabaseAppSecret');
    expect(
      containerOf(template, 'guide-production-application').Secrets.map(
        (secret: { Name: string }) => secret.Name,
      ),
    ).not.toContain('DB_APP_PASSWORD');

    // Web タスクの Execution Role のポリシーにマスター Secret が含まれない。
    const policies = template.findResources('AWS::IAM::Policy');
    const policyFor = (rolePrefix: string) =>
      Object.values(policies).filter((policy) =>
        policy.Properties.Roles.some((role: { Ref: string }) => role.Ref.startsWith(rolePrefix)),
      );
    expect(JSON.stringify(policyFor('EcsExecutionRole'))).not.toContain('DatabaseSecretAttachment');
    expect(JSON.stringify(policyFor('MigrationExecutionRole'))).toContain('DatabaseSecretAttachment');
    // Migration タスクは S3 に触れない。
    expect(JSON.stringify(policyFor('MigrationTaskRole'))).not.toContain('s3:');
  });

  test('Migration タスクはアプリ用ユーザーを用意してから migrate する', () => {
    expect(containerOf(makeTemplate(), 'guide-production-migration').Command).toEqual([
      'sh',
      '-c',
      // 空の DB には --isolated のロックを取る cache_locks が無いので、初回だけロック無しで流す。
      'php artisan db:provision-app-user' +
        ' && { php artisan migrate:status > /dev/null 2>&1 || php artisan migrate --force; }' +
        ' && php artisan migrate --force --isolated',
    ]);
  });

  test('RDS へは Fargate タスクの Security Group からだけ接続できる', () => {
    const { data, stack } = makeStacks();
    Template.fromStack(stack).hasResourceProperties('AWS::EC2::SecurityGroupIngress', {
      IpProtocol: 'tcp',
      FromPort: 5432,
      ToPort: 5432,
      SourceSecurityGroupId: { 'Fn::GetAtt': [Match.stringLikeRegexp('^TaskSecurityGroup'), 'GroupId'] },
    });
    // データ側のスタックには、アプリ側を参照するルールを置かない（循環依存になる）。
    Template.fromStack(data).resourceCountIs('AWS::EC2::SecurityGroupIngress', 0);
  });

  test('デプロイ用 Role は GitHub の Environment を指定したジョブだけが引き受けられる', () => {
    const template = makeTemplate();
    template.hasResourceProperties('AWS::IAM::Role', {
      RoleName: 'guide-production-github-actions',
      AssumeRolePolicyDocument: {
        Statement: [
          Match.objectLike({
            Action: 'sts:AssumeRoleWithWebIdentity',
            // ブランチやタグを見る StringLike は使わない。
            Condition: {
              StringEquals: {
                'token.actions.githubusercontent.com:aud': 'sts.amazonaws.com',
                'token.actions.githubusercontent.com:sub':
                  'repo:ramer-orange/guide:environment:production',
              },
            },
          }),
        ],
      },
    });

    // cdk-* 全体ではなく、bootstrap の Role を名指しで引き受ける。
    const policies = JSON.stringify(template.findResources('AWS::IAM::Policy'));
    expect(policies).not.toContain('role/cdk-*');
    for (const role of ['deploy-role', 'file-publishing-role', 'lookup-role']) {
      expect(policies).toContain(`role/cdk-hnb659fds-${role}-123456789012-ap-northeast-1`);
    }
    expect(policies).not.toContain('cfn-exec-role');
  });
});

describe('staging', () => {
  const staging = () => makeStacks({ appImageTag: 'abc123' }, environments.staging);

  test('production と同じアカウントに並べられるよう、名前に環境名が入る', () => {
    const { data, stack } = staging();
    const template = Template.fromStack(stack);
    template.hasResourceProperties('AWS::ECS::Cluster', { ClusterName: 'guide-staging' });
    template.hasResourceProperties('AWS::ECS::Service', { ServiceName: 'guide-staging-web' });
    template.hasResourceProperties('AWS::IAM::Role', {
      RoleName: 'guide-staging-github-actions',
      AssumeRolePolicyDocument: {
        Statement: [
          Match.objectLike({
            Condition: Match.objectLike({
              StringEquals: Match.objectLike({
                'token.actions.githubusercontent.com:sub':
                  'repo:ramer-orange/guide:environment:staging',
              }),
            }),
          }),
        ],
      },
    });
    Template.fromStack(data).hasResourceProperties('AWS::ECR::Repository', {
      RepositoryName: 'guide-staging-app',
    });
    // どちらのテンプレートにも production の名前が残っていない。
    for (const target of [data, stack]) {
      expect(JSON.stringify(Template.fromStack(target).toJSON())).not.toMatch(
        /guide[-/]production|guide-prod-/,
      );
    }
  });

  test('小さく安く作り、壊せる', () => {
    const { data, stack } = staging();
    const dataTemplate = Template.fromStack(data);
    dataTemplate.hasResourceProperties('AWS::RDS::DBInstance', {
      DBInstanceClass: 'db.t4g.micro',
      MultiAZ: false,
      DeletionProtection: false,
    });
    dataTemplate.hasResource('AWS::RDS::DBInstance', { DeletionPolicy: 'Delete' });
    dataTemplate.resourceCountIs('AWS::Backup::BackupPlan', 0);

    const template = Template.fromStack(stack);
    template.hasResourceProperties('AWS::ApplicationAutoScaling::ScalableTarget', {
      MinCapacity: 1,
      MaxCapacity: 1,
    });
    template.resourceCountIs('AWS::Synthetics::Canary', 0);
    // Canary が無いぶん、アラームは production より 1 本少ない。
    template.resourceCountIs('AWS::CloudWatch::Alarm', 9);
  });

  test('production のデータは削除から守る', () => {
    const dataTemplate = makeDataTemplate();
    dataTemplate.hasResourceProperties('AWS::RDS::DBInstance', { DeletionProtection: true });
    dataTemplate.hasResource('AWS::RDS::DBInstance', { DeletionPolicy: 'Snapshot' });
    for (const bucket of Object.values(dataTemplate.findResources('AWS::S3::Bucket'))) {
      expect(bucket.DeletionPolicy).toBe('Retain');
    }
    dataTemplate.hasResource('AWS::ECR::Repository', { DeletionPolicy: 'Retain' });
  });
});

describe('GovernanceStack', () => {
  test('GuardDuty・AWS Config・CloudTrail・GitHub の OIDC Provider を作る', () => {
    const app = new cdk.App();
    const template = Template.fromStack(new GovernanceStack(app, 'Gov', { env }));
    template.resourceCountIs('AWS::GuardDuty::Detector', 1);
    template.resourceCountIs('AWS::Config::ConfigurationRecorder', 1);
    template.resourceCountIs('AWS::CloudTrail::Trail', 1);
    template.hasResourceProperties('Custom::AWSCDKOpenIdConnectProvider', {
      Url: 'https://token.actions.githubusercontent.com',
      ClientIDList: ['sts.amazonaws.com'],
    });
    template.hasParameter('AlertEmail', { Type: 'String' });
  });

  test('既に有効な機能は context で外せる', () => {
    const app = new cdk.App({
      context: {
        enableGuardDuty: false,
        enableConfig: false,
        enableCloudTrail: false,
        createGithubOidcProvider: false,
      },
    });
    const template = Template.fromStack(new GovernanceStack(app, 'Gov', { env }));
    template.resourceCountIs('AWS::GuardDuty::Detector', 0);
    template.resourceCountIs('AWS::Config::ConfigurationRecorder', 0);
    template.resourceCountIs('AWS::CloudTrail::Trail', 0);
    template.resourceCountIs('Custom::AWSCDKOpenIdConnectProvider', 0);
  });
});
