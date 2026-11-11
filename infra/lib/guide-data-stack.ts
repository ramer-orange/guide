import * as cdk from 'aws-cdk-lib';
import * as backup from 'aws-cdk-lib/aws-backup';
import * as ec2 from 'aws-cdk-lib/aws-ec2';
import * as ecr from 'aws-cdk-lib/aws-ecr';
import * as events from 'aws-cdk-lib/aws-events';
import * as logs from 'aws-cdk-lib/aws-logs';
import * as rds from 'aws-cdk-lib/aws-rds';
import * as s3 from 'aws-cdk-lib/aws-s3';
import * as secretsmanager from 'aws-cdk-lib/aws-secretsmanager';
import { Construct } from 'constructs';
import {
  DATABASE_ADMIN_USERNAME,
  DATABASE_APP_USERNAME,
  DATABASE_NAME,
  GuideEnvironmentConfig,
  resourcePrefix,
} from './environments';

export interface GuideDataStackProps extends cdk.StackProps {
  readonly config: GuideEnvironmentConfig;
}

/**
 * 消えると戻せないもの（VPC・RDS・S3・ECR・Secret・バックアップ）だけを持つスタック。
 *
 * ECS・ALB・監視を持つ GuideStack から分けてあるのは、アプリ側のデプロイや
 * context の付け忘れが DB やバケットの差分として現れないようにするため。
 * このスタックは合成時の context を一切読まない。GitHub Actions はこのスタックを
 * デプロイせず（`cdk deploy --exclusively` でアプリ側だけを更新する）、変更は手動で反映する。
 */
export class GuideDataStack extends cdk.Stack {
  readonly vpc: ec2.Vpc;
  readonly databaseSecurityGroup: ec2.SecurityGroup;
  readonly database: rds.DatabaseInstance;
  /** RDS が管理・自動ローテーションするマスターユーザーの Secret。Migration タスクだけが読む。 */
  readonly databaseAdminSecret: secretsmanager.ISecret;
  /** Web タスクが接続に使う権限を絞ったユーザーの Secret。 */
  readonly databaseAppSecret: secretsmanager.Secret;
  readonly appKeySecret: secretsmanager.Secret;
  readonly googleOAuthSecret: secretsmanager.Secret;
  readonly filesBucket: s3.Bucket;
  readonly auditBucket: s3.Bucket;
  readonly applicationRepository: ecr.Repository;

  constructor(scope: Construct, id: string, props: GuideDataStackProps) {
    super(scope, id, props);

    const { config } = props;
    const prefix = resourcePrefix(config);
    const dataRemovalPolicy = config.retainData
      ? cdk.RemovalPolicy.RETAIN
      : cdk.RemovalPolicy.DESTROY;

    // ネットワーク
    // ALBとFargate用のPublic Subnet、RDS用のPrivate Isolated Subnetを2AZに作る。
    // NAT Gatewayは置かず、FargateにはPublic IPを付与する。
    this.vpc = new ec2.Vpc(this, 'Vpc', {
      vpcName: `${prefix}-vpc`,
      ipAddresses: ec2.IpAddresses.cidr(config.vpcCidr),
      maxAzs: 2,
      natGateways: 0,
      restrictDefaultSecurityGroup: true,
      subnetConfiguration: [
        {
          name: 'public',
          subnetType: ec2.SubnetType.PUBLIC,
          cidrMask: 24,
        },
        {
          name: 'database',
          subnetType: ec2.SubnetType.PRIVATE_ISOLATED,
          cidrMask: 24,
        },
      ],
    });

    // S3 は無料の Gateway Endpoint を使う。
    this.vpc.addGatewayEndpoint('S3GatewayEndpoint', {
      service: ec2.GatewayVpcEndpointAwsService.S3,
      subnets: [{ subnetType: ec2.SubnetType.PUBLIC }],
    });

    // RDS の Security Group。
    // Fargate タスクからの 5432 番を許可するルールは GuideStack 側で足す。
    // ここでタスクの Security Group を参照すると、スタック同士が互いに依存してしまう。
    this.databaseSecurityGroup = new ec2.SecurityGroup(
      this,
      'DatabaseSecurityGroup',
      {
        vpc: this.vpc,
        description: 'Guide PostgreSQL; inbound only from ECS tasks',
        allowAllOutbound: false,
      },
    );

    // S3
    // ユーザーの添付ファイルと、ALB のアクセスログを用途別の非公開バケットへ保存する。
    this.filesBucket = new s3.Bucket(this, 'FilesBucket', {
      blockPublicAccess: s3.BlockPublicAccess.BLOCK_ALL,
      encryption: s3.BucketEncryption.S3_MANAGED,
      enforceSSL: true,
      versioned: true,
      lifecycleRules: [
        {
          noncurrentVersionExpiration: cdk.Duration.days(90),
          abortIncompleteMultipartUploadAfter: cdk.Duration.days(7),
        },
      ],
      removalPolicy: dataRemovalPolicy,
      autoDeleteObjects: !config.retainData,
    });

    this.auditBucket = new s3.Bucket(this, 'AuditBucket', {
      blockPublicAccess: s3.BlockPublicAccess.BLOCK_ALL,
      encryption: s3.BucketEncryption.S3_MANAGED,
      enforceSSL: true,
      lifecycleRules: [{ expiration: cdk.Duration.days(365) }],
      removalPolicy: dataRemovalPolicy,
      autoDeleteObjects: !config.retainData,
    });

    // ECR
    // GitHub Actionsが作成したDockerイメージを保存する。
    // NEXT_PUBLIC_SITE_URL がビルド時に埋め込まれるため、イメージは環境ごとに別になる。
    this.applicationRepository = new ecr.Repository(
      this,
      'ApplicationRepository',
      {
        repositoryName: `${prefix}-app`,
        imageScanOnPush: true,
        // 同じタグを上書きできないようにし、コミット SHA とイメージを 1 対 1 に保つ。
        imageTagMutability: ecr.TagMutability.IMMUTABLE,
        encryption: ecr.RepositoryEncryption.AES_256,
        lifecycleRules: [
          {
            // 1イメージが数百MB〜1GB（PHP + Next.js ビルド込み）ある。
            // ライフサイクルは「そのイメージが稼働中か」を見ないため、
            // 短時間に保持数を超えてデプロイすると動いているタスクの
            // イメージが消えうる。保管費は月1 USD未満なので余裕を持たせる。
            description: 'Keep the latest 20 deployable images',
            maxImageCount: 20,
          },
        ],
        removalPolicy: dataRemovalPolicy,
        emptyOnDelete: !config.retainData,
      },
    );

    // Secrets Manager
    // 値を CloudFormation のパラメータで受け取らない。パラメータ経由だと、NoEcho でも
    // 値が CloudFormation の変更履歴やデプロイしたシェルの履歴を通ってしまう。
    // 生成できるものは Secrets Manager に生成させ、外から貰う値（Google OAuth）は
    // 仮の値で作っておいて、デプロイ後に `aws secretsmanager put-secret-value` で入れる。
    this.appKeySecret = new secretsmanager.Secret(this, 'AppKeySecret', {
      secretName: `guide/${config.name}/app-key`,
      description: 'Laravel APP_KEY (32 byte raw application key)',
      generateSecretString: {
        excludePunctuation: true,
        passwordLength: 32,
      },
    });

    this.googleOAuthSecret = new secretsmanager.Secret(
      this,
      'GoogleOAuthSecret',
      {
        secretName: `guide/${config.name}/google-oauth`,
        description:
          'Google OAuth client credentials ({"client_id": "...", "client_secret": "..."}); set with put-secret-value after deploy',
        // ECS は client_id と client_secret の両方のキーが無いとタスクを起動できない。
        // 仮の値で両方のキーを用意し、実際の値を入れる前でもアプリは起動できるようにする
        // （その間 Google ログインだけが失敗する）。
        // この generateSecretString は変えないこと。変えると CloudFormation が値を作り直し、
        // 手で入れた値が仮の値に戻る。
        generateSecretString: {
          secretStringTemplate: JSON.stringify({ client_id: 'unset' }),
          generateStringKey: 'client_secret',
          excludePunctuation: true,
        },
      },
    );

    this.databaseAppSecret = new secretsmanager.Secret(
      this,
      'DatabaseAppSecret',
      {
        secretName: `guide/${config.name}/database-app`,
        description:
          'Least-privilege PostgreSQL user for the web tasks (created by the migration task)',
        generateSecretString: {
          secretStringTemplate: JSON.stringify({
            username: DATABASE_APP_USERNAME,
          }),
          generateStringKey: 'password',
          excludePunctuation: true,
          passwordLength: 40,
        },
      },
    );

    // RDS PostgreSQL
    // Private DB Subnetへ配置する。production は Multi-AZ で、障害時はStandbyへFailoverする。
    // マスターユーザーのパスワードは RDS 自身が Secrets Manager で管理し、自動でローテーションする。
    // マスターユーザーを使うのは Migration タスクだけで、Web タスクは databaseAppSecret の
    // ユーザー（テーブルの読み書きだけができる）で接続する。
    this.database = new rds.DatabaseInstance(this, 'Database', {
      vpc: this.vpc,
      vpcSubnets: { subnetType: ec2.SubnetType.PRIVATE_ISOLATED },
      engine: rds.DatabaseInstanceEngine.postgres({
        version: rds.PostgresEngineVersion.VER_17,
      }),
      credentials: rds.Credentials.fromUsername(DATABASE_ADMIN_USERNAME),
      manageMasterUserPassword: true,
      databaseName: DATABASE_NAME,
      instanceType: ec2.InstanceType.of(
        ec2.InstanceClass.T4G,
        config.database.instanceSize,
      ),
      multiAz: config.database.multiAz,
      publiclyAccessible: false,
      securityGroups: [this.databaseSecurityGroup],
      allocatedStorage: 20,
      maxAllocatedStorage: 100,
      storageEncrypted: true,
      backupRetention: cdk.Duration.days(config.database.backupRetentionDays),
      deletionProtection: config.retainData,
      autoMinorVersionUpgrade: true,
      enablePerformanceInsights: true,
      cloudwatchLogsExports: ['postgresql'],
      cloudwatchLogsRetention: logs.RetentionDays.ONE_MONTH,
      removalPolicy: config.retainData
        ? cdk.RemovalPolicy.SNAPSHOT
        : cdk.RemovalPolicy.DESTROY,
    });
    const databaseAdminSecret = this.database.secret;
    if (!databaseAdminSecret) {
      throw new Error('RDS managed master user secret was not created');
    }
    this.databaseAdminSecret = databaseAdminSecret;

    // AWS Backup
    // RDS 自動バックアップ（PITR）に加えて、日次と月次の長期保管を行う。
    // 保持期間の根拠と復元手順は docs/AWS_RESTORE_RUNBOOK.md。
    if (config.backup) {
      const backupVault = new backup.BackupVault(this, 'BackupVault', {
        backupVaultName: prefix,
        removalPolicy: cdk.RemovalPolicy.RETAIN,
      });
      const backupPlan = new backup.BackupPlan(this, 'BackupPlan', {
        backupPlanName: prefix,
        backupVault,
        backupPlanRules: [
          new backup.BackupPlanRule({
            ruleName: 'Daily',
            // 18:00 UTC = 03:00 JST。利用の少ない時間帯に取る。
            scheduleExpression: events.Schedule.cron({
              hour: '18',
              minute: '0',
            }),
            deleteAfter: cdk.Duration.days(config.backup.dailyRetentionDays),
          }),
          new backup.BackupPlanRule({
            ruleName: 'Monthly',
            scheduleExpression: events.Schedule.cron({
              day: '1',
              hour: '18',
              minute: '30',
            }),
            deleteAfter: cdk.Duration.days(
              config.backup.monthlyRetentionDays,
            ),
          }),
        ],
      });
      backupPlan.addSelection('Database', {
        resources: [
          backup.BackupResource.fromRdsDatabaseInstance(this.database),
        ],
      });
    }

    // 初回構築で使う値。デプロイ後に Secret へ値を入れ、ECR へイメージを push する。
    new cdk.CfnOutput(this, 'ApplicationRepositoryUri', {
      value: this.applicationRepository.repositoryUri,
      description: 'GitHub ActionsがDockerイメージをpushするECR URI',
    });
    new cdk.CfnOutput(this, 'GoogleOAuthSecretName', {
      value: this.googleOAuthSecret.secretName,
      description:
        'デプロイ後に put-secret-value で Google OAuth の値を入れる Secret',
    });
    new cdk.CfnOutput(this, 'FilesBucketName', {
      value: this.filesBucket.bucketName,
    });
    new cdk.CfnOutput(this, 'DatabaseEndpoint', {
      value: this.database.dbInstanceEndpointAddress,
    });
  }
}
