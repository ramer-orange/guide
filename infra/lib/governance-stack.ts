import * as cdk from 'aws-cdk-lib';
import * as cloudtrail from 'aws-cdk-lib/aws-cloudtrail';
import * as config from 'aws-cdk-lib/aws-config';
import * as events from 'aws-cdk-lib/aws-events';
import * as eventTargets from 'aws-cdk-lib/aws-events-targets';
import * as guardduty from 'aws-cdk-lib/aws-guardduty';
import * as iam from 'aws-cdk-lib/aws-iam';
import * as logs from 'aws-cdk-lib/aws-logs';
import * as s3 from 'aws-cdk-lib/aws-s3';
import * as sns from 'aws-cdk-lib/aws-sns';
import * as subscriptions from 'aws-cdk-lib/aws-sns-subscriptions';
import { Construct } from 'constructs';

/**
 * アカウントに 1 つだけ置くもの（GuardDuty・AWS Config・CloudTrail・GitHub Actions の
 * OIDC Provider）をまとめたスタック。
 *
 * アプリのスタック（GuideStack）から分けることで、
 * - 既に有効なアカウントでもアプリのデプロイが失敗しない
 * - アプリのスタックを作り直しても監査が止まらない
 * - production と staging を同じアカウントに並べても、監査や OIDC Provider が重複しない
 * ようにする。既にあるものは -c enableGuardDuty=false / -c enableConfig=false /
 * -c enableCloudTrail=false / -c createGithubOidcProvider=false で外す。
 */
export class GovernanceStack extends cdk.Stack {
  constructor(scope: Construct, id: string, props?: cdk.StackProps) {
    super(scope, id, props);

    const enableGuardDuty = this.booleanContext('enableGuardDuty', true);
    const enableConfig = this.booleanContext('enableConfig', true);
    const enableCloudTrail = this.booleanContext('enableCloudTrail', true);
    const createGithubOidcProvider = this.booleanContext(
      'createGithubOidcProvider',
      true,
    );

    const alertEmailParameter = new cdk.CfnParameter(this, 'AlertEmail', {
      type: 'String',
      description: 'GuardDuty・AWS Config の通知先メールアドレス（必須）',
      allowedPattern: '^[^@\\s]+@[^@\\s]+\\.[^@\\s]+$',
      constraintDescription: 'メールアドレスを指定してください',
    });

    const alertTopic = new sns.Topic(this, 'SecurityTopic', {
      topicName: 'guide-account-security',
      displayName: 'Guide account security',
    });
    alertTopic.addSubscription(
      new subscriptions.EmailSubscription(alertEmailParameter.valueAsString),
    );

    // GitHub Actions OIDC Provider
    // 環境ごとのデプロイ用 Role（GuideStack）が信頼する。同じ URL の Provider は
    // アカウントに 1 つしか作れないため、環境ごとのスタックではなくここに置く。
    // Role 側は ARN を名前から組み立てて参照する（スタック間の Export を作らない）。
    if (createGithubOidcProvider) {
      new iam.OpenIdConnectProvider(this, 'GitHubOidcProvider', {
        url: 'https://token.actions.githubusercontent.com',
        clientIds: ['sts.amazonaws.com'],
      });
    }

    // CloudTrail
    // AWS APIの操作履歴をCloudWatch Logsと監査用S3へ保存する。
    // 証跡はアカウント全体を記録するので、環境ごとに作ると同じイベントを二重に保存して課金される。
    if (enableCloudTrail) {
      const trailBucket = new s3.Bucket(this, 'TrailBucket', {
        blockPublicAccess: s3.BlockPublicAccess.BLOCK_ALL,
        encryption: s3.BucketEncryption.S3_MANAGED,
        enforceSSL: true,
        lifecycleRules: [{ expiration: cdk.Duration.days(365) }],
        removalPolicy: cdk.RemovalPolicy.RETAIN,
      });
      const trailLogGroup = new logs.LogGroup(this, 'CloudTrailLogGroup', {
        logGroupName: '/aws/cloudtrail/guide-account',
        retention: logs.RetentionDays.THREE_MONTHS,
        removalPolicy: cdk.RemovalPolicy.RETAIN,
      });
      new cloudtrail.Trail(this, 'AuditTrail', {
        trailName: 'guide-account',
        bucket: trailBucket,
        s3KeyPrefix: 'cloudtrail',
        sendToCloudWatchLogs: true,
        cloudWatchLogGroup: trailLogGroup,
        includeGlobalServiceEvents: true,
        managementEvents: cloudtrail.ReadWriteType.ALL,
      });
    }

    if (enableGuardDuty) {
      const detector = new guardduty.CfnDetector(this, 'GuardDutyDetector', {
        enable: true,
        findingPublishingFrequency: 'FIFTEEN_MINUTES',
      });

      new events.Rule(this, 'GuardDutyFindingsRule', {
        description: 'Notify about GuardDuty findings (severity >= 4)',
        eventPattern: {
          source: ['aws.guardduty'],
          detailType: ['GuardDuty Finding'],
          detail: {
            detectorId: [detector.ref],
            severity: [{ numeric: ['>=', 4] }],
          },
        },
        targets: [new eventTargets.SnsTopic(alertTopic)],
      });
    }

    if (enableConfig) {
      const configBucket = new s3.Bucket(this, 'ConfigBucket', {
        blockPublicAccess: s3.BlockPublicAccess.BLOCK_ALL,
        encryption: s3.BucketEncryption.S3_MANAGED,
        enforceSSL: true,
        lifecycleRules: [{ expiration: cdk.Duration.days(365) }],
        removalPolicy: cdk.RemovalPolicy.RETAIN,
      });

      const configRole = new iam.Role(this, 'ConfigRecorderRole', {
        assumedBy: new iam.ServicePrincipal('config.amazonaws.com'),
        managedPolicies: [
          iam.ManagedPolicy.fromAwsManagedPolicyName(
            'service-role/AWS_ConfigRole',
          ),
        ],
      });
      configBucket.grantReadWrite(configRole);
      alertTopic.grantPublish(configRole);

      const recorder = new config.CfnConfigurationRecorder(
        this,
        'ConfigurationRecorder',
        {
          name: 'guide-account',
          roleArn: configRole.roleArn,
          recordingGroup: {
            allSupported: true,
            includeGlobalResourceTypes: true,
          },
        },
      );
      const deliveryChannel = new config.CfnDeliveryChannel(
        this,
        'ConfigDeliveryChannel',
        {
          name: 'guide-account',
          s3BucketName: configBucket.bucketName,
          s3KeyPrefix: 'config',
          configSnapshotDeliveryProperties: {
            deliveryFrequency: 'TwentyFour_Hours',
          },
        },
      );
      deliveryChannel.addDependency(recorder);

      new events.Rule(this, 'ConfigComplianceRule', {
        description: 'Notify about AWS Config compliance changes',
        eventPattern: {
          source: ['aws.config'],
          detailType: ['Config Rules Compliance Change'],
          detail: {
            newEvaluationResult: { complianceType: ['NON_COMPLIANT'] },
          },
        },
        targets: [new eventTargets.SnsTopic(alertTopic)],
      });
    }
  }

  private booleanContext(name: string, defaultValue: boolean): boolean {
    const rawValue = this.node.tryGetContext(name);
    if (rawValue === undefined) {
      return defaultValue;
    }
    if (rawValue === true || rawValue === 'true') {
      return true;
    }
    if (rawValue === false || rawValue === 'false') {
      return false;
    }
    throw new Error(`CDK context ${name} must be true or false`);
  }
}
