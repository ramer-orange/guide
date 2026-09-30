import * as cdk from 'aws-cdk-lib';
import * as acm from 'aws-cdk-lib/aws-certificatemanager';
import * as cloudtrail from 'aws-cdk-lib/aws-cloudtrail';
import * as cloudwatch from 'aws-cdk-lib/aws-cloudwatch';
import * as cloudwatchActions from 'aws-cdk-lib/aws-cloudwatch-actions';
import * as config from 'aws-cdk-lib/aws-config';
import * as ec2 from 'aws-cdk-lib/aws-ec2';
import * as ecr from 'aws-cdk-lib/aws-ecr';
import * as ecs from 'aws-cdk-lib/aws-ecs';
import * as elbv2 from 'aws-cdk-lib/aws-elasticloadbalancingv2';
import * as events from 'aws-cdk-lib/aws-events';
import * as eventTargets from 'aws-cdk-lib/aws-events-targets';
import * as guardduty from 'aws-cdk-lib/aws-guardduty';
import * as iam from 'aws-cdk-lib/aws-iam';
import * as logs from 'aws-cdk-lib/aws-logs';
import * as rds from 'aws-cdk-lib/aws-rds';
import * as route53 from 'aws-cdk-lib/aws-route53';
import * as s3 from 'aws-cdk-lib/aws-s3';
import * as secretsmanager from 'aws-cdk-lib/aws-secretsmanager';
import * as sns from 'aws-cdk-lib/aws-sns';
import * as subscriptions from 'aws-cdk-lib/aws-sns-subscriptions';
import * as ssm from 'aws-cdk-lib/aws-ssm';
import * as wafv2 from 'aws-cdk-lib/aws-wafv2';
import { Construct } from 'constructs';

/**
 * docs/aws_architecture_ecs.py を実装した本番向けスタック。
 *
 * 初回はECRが空なので、デフォルトのdesiredCountは0にしている。
 * 1. cdk deploy でECRを含む基盤を作成
 * 2. DockerイメージをECRへpush
 * 3. cdk deploy -c appImageTag=<commit-sha> -c desiredCount=2
 * の順に実行すると、サービスを2タスクで起動できる。
 */
export class GuideStack extends cdk.Stack {
  constructor(scope: Construct, id: string, props?: cdk.StackProps) {
    super(scope, id, props);

    // デプロイ設定
    // イメージタグ、起動タスク数、任意機能の有効化をCDK Contextから受け取る。
    // 初回構築時はECRが空なので、デフォルトのタスク数は0にする。
    const appImageTag =
      String(this.node.tryGetContext('appImageTag') ?? 'latest').trim() ||
      'latest';
    const desiredCount = this.numberContext('desiredCount', 0, 0, 6);
    const enableInterfaceEndpoints = this.booleanContext(
      'enableInterfaceEndpoints',
      true,
    );
    const enableAccountGovernance = this.booleanContext(
      'enableAccountGovernance',
      true,
    );
    const githubOwner = String(
      this.node.tryGetContext('githubOwner') ?? 'ramer-orange',
    );
    const githubRepository = String(
      this.node.tryGetContext('githubRepository') ?? 'guide',
    );

    // CloudFormation入力値
    // 既存のRoute 53 Hosted Zone、独自ドメイン、Google OAuthの設定を
    // デプロイ時のParameterとして受け取る。
    const domainNameParameter = new cdk.CfnParameter(this, 'DomainName', {
      type: 'String',
      description: 'ALBへ向ける完全修飾ドメイン名（例: guide.example.com）',
      allowedPattern:
        '^(?=.{1,253}$)(?!-)(?:[a-zA-Z0-9-]{1,63}\\.)+[a-zA-Z]{2,63}$',
    });
    const hostedZoneIdParameter = new cdk.CfnParameter(
      this,
      'HostedZoneId',
      {
        type: 'AWS::Route53::HostedZone::Id',
        description: '既存Route 53 Public Hosted ZoneのID',
      },
    );
    const hostedZoneNameParameter = new cdk.CfnParameter(
      this,
      'HostedZoneName',
      {
        type: 'String',
        description: '既存Route 53 Public Hosted Zone名（例: example.com）',
      },
    );
    const googleClientIdParameter = new cdk.CfnParameter(
      this,
      'GoogleClientId',
      {
        type: 'String',
        noEcho: true,
        description: 'Google OAuth Client ID',
      },
    );
    const googleClientSecretParameter = new cdk.CfnParameter(
      this,
      'GoogleClientSecret',
      {
        type: 'String',
        noEcho: true,
        description: 'Google OAuth Client Secret',
      },
    );

    const domainName = domainNameParameter.valueAsString;
    const applicationUrl = cdk.Fn.join('', ['https://', domainName]);

    // ネットワーク
    // ALBとFargate用のPublic Subnet、RDS用のPrivate Isolated Subnetを2AZに作る。
    // NAT Gatewayは置かず、FargateにはPublic IPを付与する。
    const vpc = new ec2.Vpc(this, 'Vpc', {
      vpcName: 'guide-production-vpc',
      ipAddresses: ec2.IpAddresses.cidr('10.0.0.0/16'),
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

    // Security Group
    // Internet -> ALB -> Fargate -> RDSの順に必要な通信だけを許可する。
    // Fargateの80番はALBからのみ、RDSの5432番はFargateからのみ許可する。
    const albSecurityGroup = new ec2.SecurityGroup(
      this,
      'AlbSecurityGroup',
      {
        vpc,
        description: 'Guide public ALB',
        allowAllOutbound: true,
      },
    );
    albSecurityGroup.addIngressRule(
      ec2.Peer.anyIpv4(),
      ec2.Port.tcp(443),
      'HTTPS from the Internet',
    );
    albSecurityGroup.addIngressRule(
      ec2.Peer.anyIpv6(),
      ec2.Port.tcp(443),
      'HTTPS from the Internet over IPv6',
    );

    const taskSecurityGroup = new ec2.SecurityGroup(
      this,
      'TaskSecurityGroup',
      {
        vpc,
        description: 'Guide Fargate tasks; inbound only from ALB',
        allowAllOutbound: true,
      },
    );
    taskSecurityGroup.addIngressRule(
      albSecurityGroup,
      ec2.Port.tcp(80),
      'HTTP from ALB only',
    );

    const databaseSecurityGroup = new ec2.SecurityGroup(
      this,
      'DatabaseSecurityGroup',
      {
        vpc,
        description: 'Guide PostgreSQL; inbound only from ECS tasks',
        allowAllOutbound: false,
      },
    );
    databaseSecurityGroup.addIngressRule(
      taskSecurityGroup,
      ec2.Port.tcp(5432),
      'PostgreSQL from Fargate tasks only',
    );

    const endpointSecurityGroup = new ec2.SecurityGroup(
      this,
      'EndpointSecurityGroup',
      {
        vpc,
        description: 'HTTPS from Guide tasks to interface endpoints',
        allowAllOutbound: false,
      },
    );
    endpointSecurityGroup.addIngressRule(
      taskSecurityGroup,
      ec2.Port.tcp(443),
      'HTTPS from Fargate tasks',
    );

    // VPC Endpoint
    // S3はGateway Endpointを使う。ECR、Secrets Manager、CloudWatch Logs、
    // Parameter Storeは設定が有効な場合にInterface Endpointを経由させる。
    vpc.addGatewayEndpoint('S3GatewayEndpoint', {
      service: ec2.GatewayVpcEndpointAwsService.S3,
      subnets: [{ subnetType: ec2.SubnetType.PUBLIC }],
    });

    if (enableInterfaceEndpoints) {
      const endpointServices: Record<
        string,
        ec2.InterfaceVpcEndpointAwsService
      > = {
        EcrApi: ec2.InterfaceVpcEndpointAwsService.ECR,
        EcrDocker: ec2.InterfaceVpcEndpointAwsService.ECR_DOCKER,
        SecretsManager: ec2.InterfaceVpcEndpointAwsService.SECRETS_MANAGER,
        CloudWatchLogs: ec2.InterfaceVpcEndpointAwsService.CLOUDWATCH_LOGS,
        SystemsManager: ec2.InterfaceVpcEndpointAwsService.SSM,
      };

      for (const [endpointId, service] of Object.entries(endpointServices)) {
        vpc.addInterfaceEndpoint(`${endpointId}Endpoint`, {
          service,
          privateDnsEnabled: true,
          securityGroups: [endpointSecurityGroup],
          subnets: { subnetType: ec2.SubnetType.PUBLIC },
        });
      }
    }

    // S3
    // ユーザーの添付ファイルと、CloudTrail・ALB・AWS Configの監査ログを
    // 用途別の非公開バケットへ保存する。
    const filesBucket = new s3.Bucket(this, 'FilesBucket', {
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
      removalPolicy: cdk.RemovalPolicy.RETAIN,
    });

    const auditBucket = new s3.Bucket(this, 'AuditBucket', {
      blockPublicAccess: s3.BlockPublicAccess.BLOCK_ALL,
      encryption: s3.BucketEncryption.S3_MANAGED,
      enforceSSL: true,
      lifecycleRules: [{ expiration: cdk.Duration.days(365) }],
      removalPolicy: cdk.RemovalPolicy.RETAIN,
    });

    // ECRとSecrets Manager
    // GitHub Actionsが作成したDockerイメージをECRへ保存する。
    // APP_KEYとGoogle OAuth認証情報はSecrets Managerで管理する。
    const applicationRepository = new ecr.Repository(
      this,
      'ApplicationRepository',
      {
        repositoryName: 'guide-production-app',
        imageScanOnPush: true,
        encryption: ecr.RepositoryEncryption.AES_256,
        lifecycleRules: [
          {
            description: 'Keep the latest 30 deployable images',
            maxImageCount: 30,
          },
        ],
        removalPolicy: cdk.RemovalPolicy.RETAIN,
      },
    );

    const appKeySecret = new secretsmanager.Secret(this, 'AppKeySecret', {
      secretName: 'guide/production/app-key',
      description: 'Laravel APP_KEY (32 byte raw application key)',
      generateSecretString: {
        excludePunctuation: true,
        passwordLength: 32,
      },
    });

    const googleOAuthSecret = new secretsmanager.Secret(
      this,
      'GoogleOAuthSecret',
      {
        secretName: 'guide/production/google-oauth',
        description: 'Google OAuth client credentials',
        secretObjectValue: {
          client_id: cdk.SecretValue.cfnParameter(googleClientIdParameter),
          client_secret: cdk.SecretValue.cfnParameter(
            googleClientSecretParameter,
          ),
        },
      },
    );

    // RDS PostgreSQL
    // Private DB SubnetへMulti-AZ構成で配置し、障害時はStandbyへFailoverする。
    // DB認証情報はRDSが作成するSecrets ManagerのSecretへ保存する。
    const database = new rds.DatabaseInstance(this, 'Database', {
      vpc,
      vpcSubnets: { subnetType: ec2.SubnetType.PRIVATE_ISOLATED },
      engine: rds.DatabaseInstanceEngine.postgres({
        version: rds.PostgresEngineVersion.VER_17,
      }),
      credentials: rds.Credentials.fromGeneratedSecret('guide'),
      databaseName: 'guide',
      instanceType: ec2.InstanceType.of(
        ec2.InstanceClass.T4G,
        ec2.InstanceSize.MICRO,
      ),
      multiAz: true,
      publiclyAccessible: false,
      securityGroups: [databaseSecurityGroup],
      allocatedStorage: 20,
      maxAllocatedStorage: 100,
      storageEncrypted: true,
      backupRetention: cdk.Duration.days(7),
      deletionProtection: true,
      autoMinorVersionUpgrade: true,
      cloudwatchLogsExports: ['postgresql'],
      cloudwatchLogsRetention: logs.RetentionDays.ONE_MONTH,
      removalPolicy: cdk.RemovalPolicy.SNAPSHOT,
    });
    const databaseSecret = database.secret;
    if (!databaseSecret) {
      throw new Error('RDS credentials secret was not created');
    }

    // Parameter Store
    // 秘密ではないアプリ設定を保存し、ECS Task起動時に環境変数として読み込む。
    const appNameParameter = new ssm.StringParameter(
      this,
      'AppNameParameter',
      {
        parameterName: '/guide/production/app-name',
        stringValue: 'Guide',
      },
    );
    const appEnvironmentParameter = new ssm.StringParameter(
      this,
      'AppEnvironmentParameter',
      {
        parameterName: '/guide/production/app-environment',
        stringValue: 'production',
      },
    );
    const appUrlParameter = new ssm.StringParameter(
      this,
      'AppUrlParameter',
      {
        parameterName: '/guide/production/app-url',
        stringValue: applicationUrl,
      },
    );

    // ECS用IAM Role
    // Execution RoleはECR・Secrets・Parameter Store・Logsへの起動時アクセスに使う。
    // Task Roleは実行中のLaravelが添付ファイル用S3へアクセスするために使う。
    const executionRole = new iam.Role(this, 'EcsExecutionRole', {
      assumedBy: new iam.ServicePrincipal('ecs-tasks.amazonaws.com'),
      description: 'Pull Guide images and load task secrets/configuration',
      managedPolicies: [
        iam.ManagedPolicy.fromAwsManagedPolicyName(
          'service-role/AmazonECSTaskExecutionRolePolicy',
        ),
      ],
    });

    const taskRole = new iam.Role(this, 'EcsTaskRole', {
      assumedBy: new iam.ServicePrincipal('ecs-tasks.amazonaws.com'),
      description: 'Least-privilege AWS access used by the Laravel app',
    });

    applicationRepository.grantPull(executionRole);
    databaseSecret.grantRead(executionRole);
    appKeySecret.grantRead(executionRole);
    googleOAuthSecret.grantRead(executionRole);
    appNameParameter.grantRead(executionRole);
    appEnvironmentParameter.grantRead(executionRole);
    appUrlParameter.grantRead(executionRole);
    filesBucket.grantReadWrite(taskRole);

    // ECS ClusterとCloudWatch Logs
    // Fargate Taskを収容するClusterを作り、Container Insightsとログ出力を有効にする。
    const cluster = new ecs.Cluster(this, 'Cluster', {
      vpc,
      clusterName: 'guide-production',
      containerInsightsV2: ecs.ContainerInsights.ENABLED,
    });

    const applicationLogGroup = new logs.LogGroup(
      this,
      'ApplicationLogGroup',
      {
        logGroupName: '/ecs/guide-production/application',
        retention: logs.RetentionDays.ONE_MONTH,
        removalPolicy: cdk.RemovalPolicy.RETAIN,
      },
    );
    const migrationLogGroup = new logs.LogGroup(this, 'MigrationLogGroup', {
      logGroupName: '/ecs/guide-production/migration',
      retention: logs.RetentionDays.ONE_MONTH,
      removalPolicy: cdk.RemovalPolicy.RETAIN,
    });

    // Laravel共通設定
    // Web TaskとMigration Taskで共通利用する環境変数とSecretをまとめる。
    const commonEnvironment: Record<string, string> = {
      APP_DEBUG: 'false',
      APP_TIMEZONE: 'Asia/Tokyo',
      LOG_CHANNEL: 'stderr',
      LOG_LEVEL: 'info',
      DB_CONNECTION: 'pgsql',
      DB_HOST: database.dbInstanceEndpointAddress,
      DB_PORT: database.dbInstanceEndpointPort,
      DB_DATABASE: 'guide',
      SESSION_DRIVER: 'database',
      SESSION_SECURE_COOKIE: 'true',
      CACHE_STORE: 'database',
      QUEUE_CONNECTION: 'database',
      FILESYSTEM_DISK: 's3',
      FILESYSTEM_UPLOADS_DISK: 's3',
      FILESYSTEM_TEMPORARY_URL_TTL: '10',
      AWS_DEFAULT_REGION: this.region,
      AWS_BUCKET: filesBucket.bucketName,
      AWS_USE_PATH_STYLE_ENDPOINT: 'false',
      GOOGLE_REDIRECT_URI: cdk.Fn.join('', [
        applicationUrl,
        '/auth/google/callback',
      ]),
      MAIL_MAILER: 'log',
    };
    const commonSecrets: Record<string, ecs.Secret> = {
      APP_NAME: ecs.Secret.fromSsmParameter(appNameParameter),
      APP_ENV: ecs.Secret.fromSsmParameter(appEnvironmentParameter),
      APP_URL: ecs.Secret.fromSsmParameter(appUrlParameter),
      APP_KEY: ecs.Secret.fromSecretsManager(appKeySecret),
      DB_USERNAME: ecs.Secret.fromSecretsManager(databaseSecret, 'username'),
      DB_PASSWORD: ecs.Secret.fromSecretsManager(databaseSecret, 'password'),
      GOOGLE_CLIENT_ID: ecs.Secret.fromSecretsManager(
        googleOAuthSecret,
        'client_id',
      ),
      GOOGLE_CLIENT_SECRET: ecs.Secret.fromSecretsManager(
        googleOAuthSecret,
        'client_secret',
      ),
    };
    const image = ecs.ContainerImage.fromEcrRepository(
      applicationRepository,
      appImageTag,
    );

    // Web Task Definition
    // ALBからHTTPリクエストを受けるLaravelコンテナを定義する。
    // /upをコンテナのヘルスチェックとして利用する。
    const taskDefinition = new ecs.FargateTaskDefinition(
      this,
      'ApplicationTaskDefinition',
      {
        family: 'guide-production-application',
        cpu: 512,
        memoryLimitMiB: 1024,
        runtimePlatform: {
          cpuArchitecture: ecs.CpuArchitecture.ARM64,
          operatingSystemFamily: ecs.OperatingSystemFamily.LINUX,
        },
        executionRole,
        taskRole,
      },
    );
    const applicationContainer = taskDefinition.addContainer(
      'ApplicationContainer',
      {
        containerName: 'application',
        image,
        environment: commonEnvironment,
        secrets: commonSecrets,
        logging: ecs.LogDrivers.awsLogs({
          logGroup: applicationLogGroup,
          streamPrefix: 'app',
        }),
        healthCheck: {
          command: [
            'CMD-SHELL',
            'curl --fail --silent http://127.0.0.1/up || exit 1',
          ],
          interval: cdk.Duration.seconds(30),
          timeout: cdk.Duration.seconds(5),
          retries: 3,
          startPeriod: cdk.Duration.seconds(30),
        },
      },
    );
    applicationContainer.addPortMappings({
      name: 'http',
      containerPort: 80,
      protocol: ecs.Protocol.TCP,
    });

    // Migration Task Definition
    // デプロイ時にphp artisan migrateを一度だけ実行する単発Taskを定義する。
    // --isolatedにより、誤って複数起動した場合の同時実行を防ぐ。
    const migrationTaskDefinition = new ecs.FargateTaskDefinition(
      this,
      'MigrationTaskDefinition',
      {
        family: 'guide-production-migration',
        cpu: 256,
        memoryLimitMiB: 512,
        runtimePlatform: {
          cpuArchitecture: ecs.CpuArchitecture.ARM64,
          operatingSystemFamily: ecs.OperatingSystemFamily.LINUX,
        },
        executionRole,
        taskRole,
      },
    );
    migrationTaskDefinition.addContainer('MigrationContainer', {
      containerName: 'migration',
      image,
      command: ['php', 'artisan', 'migrate', '--force', '--isolated'],
      environment: commonEnvironment,
      secrets: commonSecrets,
      essential: true,
      logging: ecs.LogDrivers.awsLogs({
        logGroup: migrationLogGroup,
        streamPrefix: 'migration',
      }),
    });

    // ECS ServiceとApplication Auto Scaling
    // Web Taskの必要数を維持し、障害Taskを自動交換する。
    // 通常運用では最低2タスクとし、CPU・メモリ使用率に応じて最大6まで増減する。
    const service = new ecs.FargateService(this, 'ApplicationService', {
      cluster,
      serviceName: 'guide-production-web',
      taskDefinition,
      desiredCount,
      assignPublicIp: true,
      vpcSubnets: { subnetType: ec2.SubnetType.PUBLIC },
      securityGroups: [taskSecurityGroup],
      circuitBreaker: { rollback: true },
      minHealthyPercent: 100,
      maxHealthyPercent: 200,
      healthCheckGracePeriod: cdk.Duration.seconds(60),
      enableECSManagedTags: true,
      propagateTags: ecs.PropagatedTagSource.SERVICE,
    });

    const scalableTarget = service.autoScaleTaskCount({
      minCapacity: desiredCount === 0 ? 0 : 2,
      maxCapacity: 6,
    });
    scalableTarget.scaleOnCpuUtilization('CpuScaling', {
      targetUtilizationPercent: 60,
      scaleInCooldown: cdk.Duration.minutes(5),
      scaleOutCooldown: cdk.Duration.minutes(1),
    });
    scalableTarget.scaleOnMemoryUtilization('MemoryScaling', {
      targetUtilizationPercent: 70,
      scaleInCooldown: cdk.Duration.minutes(5),
      scaleOutCooldown: cdk.Duration.minutes(1),
    });

    // 独自ドメインとTLS証明書
    // 既存Hosted Zoneを参照し、ACM証明書をDNS検証で発行する。
    const hostedZone = route53.HostedZone.fromHostedZoneAttributes(
      this,
      'HostedZone',
      {
        hostedZoneId: hostedZoneIdParameter.valueAsString,
        zoneName: hostedZoneNameParameter.valueAsString,
      },
    );
    const certificate = new acm.Certificate(this, 'AlbCertificate', {
      domainName,
      validation: acm.CertificateValidation.fromDns(hostedZone),
    });

    // ALBとTarget Group
    // HTTPSを終端して正常なFargate TaskへHTTPで転送する。
    // 新Taskが/upのヘルスチェックに成功してからリクエストを流す。
    const alb = new elbv2.ApplicationLoadBalancer(this, 'Alb', {
      vpc,
      internetFacing: true,
      loadBalancerName: 'guide-production',
      securityGroup: albSecurityGroup,
      vpcSubnets: { subnetType: ec2.SubnetType.PUBLIC },
      deletionProtection: true,
    });
    alb.logAccessLogs(auditBucket, 'alb');

    const targetGroup = new elbv2.ApplicationTargetGroup(
      this,
      'ApplicationTargetGroup',
      {
        vpc,
        port: 80,
        protocol: elbv2.ApplicationProtocol.HTTP,
        targetType: elbv2.TargetType.IP,
        deregistrationDelay: cdk.Duration.seconds(30),
        healthCheck: {
          path: '/up',
          healthyHttpCodes: '200',
          interval: cdk.Duration.seconds(30),
          timeout: cdk.Duration.seconds(5),
          healthyThresholdCount: 2,
          unhealthyThresholdCount: 3,
        },
      },
    );
    service.attachToApplicationTargetGroup(targetGroup);

    alb.addListener('HttpsListener', {
      port: 443,
      protocol: elbv2.ApplicationProtocol.HTTPS,
      certificates: [certificate],
      sslPolicy: elbv2.SslPolicy.RECOMMENDED_TLS,
      defaultTargetGroups: [targetGroup],
    });

    // Route 53 Alias Record
    // 独自ドメインのAレコードをALBへ向ける。
    new route53.CfnRecordSet(this, 'ApplicationAliasRecord', {
      hostedZoneId: hostedZoneIdParameter.valueAsString,
      name: domainName,
      type: 'A',
      aliasTarget: {
        dnsName: alb.loadBalancerDnsName,
        hostedZoneId: alb.loadBalancerCanonicalHostedZoneId,
        evaluateTargetHealth: true,
      },
    });

    // AWS WAF
    // AWS Managed RulesとIP単位のレート制限で不正リクエストを遮断する。
    // 正常なファイルアップロードを妨げないようBodyサイズ制限ルールは除外する。
    const webAcl = new wafv2.CfnWebACL(this, 'AlbWebAcl', {
      name: 'guide-production-alb',
      scope: 'REGIONAL',
      defaultAction: { allow: {} },
      visibilityConfig: {
        cloudWatchMetricsEnabled: true,
        metricName: 'guide-production-alb-waf',
        sampledRequestsEnabled: true,
      },
      rules: [
        {
          name: 'AWSManagedRulesCommonRuleSet',
          priority: 0,
          overrideAction: { none: {} },
          statement: {
            managedRuleGroupStatement: {
              vendorName: 'AWS',
              name: 'AWSManagedRulesCommonRuleSet',
              // 添付ファイルのrequest bodyをサイズだけで遮断しない。
              excludedRules: [{ name: 'SizeRestrictions_BODY' }],
            },
          },
          visibilityConfig: {
            cloudWatchMetricsEnabled: true,
            metricName: 'guide-common-rules',
            sampledRequestsEnabled: true,
          },
        },
        {
          name: 'AWSManagedRulesKnownBadInputsRuleSet',
          priority: 1,
          overrideAction: { none: {} },
          statement: {
            managedRuleGroupStatement: {
              vendorName: 'AWS',
              name: 'AWSManagedRulesKnownBadInputsRuleSet',
            },
          },
          visibilityConfig: {
            cloudWatchMetricsEnabled: true,
            metricName: 'guide-known-bad-inputs',
            sampledRequestsEnabled: true,
          },
        },
        {
          name: 'IpRateLimit',
          priority: 2,
          action: { block: {} },
          statement: {
            rateBasedStatement: {
              aggregateKeyType: 'IP',
              limit: 2000,
            },
          },
          visibilityConfig: {
            cloudWatchMetricsEnabled: true,
            metricName: 'guide-ip-rate-limit',
            sampledRequestsEnabled: true,
          },
        },
      ],
    });
    new wafv2.CfnWebACLAssociation(this, 'AlbWebAclAssociation', {
      resourceArn: alb.loadBalancerArn,
      webAclArn: webAcl.attrArn,
    });

    // CloudWatch監視とSNS通知
    // ECS・ALB・RDSの主要メトリクスを監視し、閾値超過時にSNSへ通知する。
    const alertTopic = new sns.Topic(this, 'OperationsTopic', {
      topicName: 'guide-production-operations',
      displayName: 'Guide production operations',
    });
    const alertEmail = this.node.tryGetContext('alertEmail');
    if (alertEmail) {
      alertTopic.addSubscription(
        new subscriptions.EmailSubscription(String(alertEmail)),
      );
    }

    const alarmAction = new cloudwatchActions.SnsAction(alertTopic);
    service
      .metricCpuUtilization({ period: cdk.Duration.minutes(1) })
      .createAlarm(this, 'HighTaskCpuAlarm', {
        alarmName: 'guide-production-high-task-cpu',
        threshold: 80,
        evaluationPeriods: 3,
        datapointsToAlarm: 3,
        treatMissingData: cloudwatch.TreatMissingData.NOT_BREACHING,
      })
      .addAlarmAction(alarmAction);
    targetGroup.metrics
      .httpCodeTarget(elbv2.HttpCodeTarget.TARGET_5XX_COUNT, {
        period: cdk.Duration.minutes(1),
        statistic: 'Sum',
      })
      .createAlarm(this, 'Target5xxAlarm', {
        alarmName: 'guide-production-target-5xx',
        threshold: 5,
        evaluationPeriods: 2,
        datapointsToAlarm: 2,
        treatMissingData: cloudwatch.TreatMissingData.NOT_BREACHING,
      })
      .addAlarmAction(alarmAction);
    database
      .metricCPUUtilization({ period: cdk.Duration.minutes(1) })
      .createAlarm(this, 'HighDatabaseCpuAlarm', {
        alarmName: 'guide-production-high-database-cpu',
        threshold: 80,
        evaluationPeriods: 5,
        datapointsToAlarm: 3,
        treatMissingData: cloudwatch.TreatMissingData.NOT_BREACHING,
      })
      .addAlarmAction(alarmAction);

    // CloudTrail
    // AWS APIの操作履歴をCloudWatch Logsと監査用S3へ保存する。
    const trailLogGroup = new logs.LogGroup(this, 'CloudTrailLogGroup', {
      logGroupName: '/aws/cloudtrail/guide-production',
      retention: logs.RetentionDays.THREE_MONTHS,
      removalPolicy: cdk.RemovalPolicy.RETAIN,
    });
    new cloudtrail.Trail(this, 'AuditTrail', {
      trailName: 'guide-production',
      bucket: auditBucket,
      s3KeyPrefix: 'cloudtrail',
      sendToCloudWatchLogs: true,
      cloudWatchLogGroup: trailLogGroup,
      includeGlobalServiceEvents: true,
      managementEvents: cloudtrail.ReadWriteType.ALL,
    });

    // GuardDutyとAWS Config
    // アカウント単位の脅威検知と構成変更監査を有効にし、
    // 重要なFindingや非準拠への変化をSNSへ通知する。
    if (enableAccountGovernance) {
      const guardDutyDetector = new guardduty.CfnDetector(
        this,
        'GuardDutyDetector',
        {
          enable: true,
          findingPublishingFrequency: 'FIFTEEN_MINUTES',
        },
      );

      new events.Rule(this, 'GuardDutyFindingsRule', {
        description: 'Notify operations about GuardDuty findings',
        eventPattern: {
          source: ['aws.guardduty'],
          detailType: ['GuardDuty Finding'],
          detail: {
            detectorId: [guardDutyDetector.ref],
            severity: [{ numeric: ['>=', 4] }],
          },
        },
        targets: [new eventTargets.SnsTopic(alertTopic)],
      });

      const configRole = new iam.Role(this, 'ConfigRecorderRole', {
        assumedBy: new iam.ServicePrincipal('config.amazonaws.com'),
        managedPolicies: [
          iam.ManagedPolicy.fromAwsManagedPolicyName(
            'service-role/AWS_ConfigRole',
          ),
        ],
      });
      auditBucket.grantReadWrite(configRole);
      alertTopic.grantPublish(configRole);

      const recorder = new config.CfnConfigurationRecorder(
        this,
        'ConfigurationRecorder',
        {
          name: 'guide-production',
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
          name: 'guide-production',
          s3BucketName: auditBucket.bucketName,
          s3KeyPrefix: 'config',
          snsTopicArn: alertTopic.topicArn,
          configSnapshotDeliveryProperties: {
            deliveryFrequency: 'TwentyFour_Hours',
          },
        },
      );
      deliveryChannel.addDependency(recorder);

      new events.Rule(this, 'ConfigComplianceRule', {
        description: 'Notify operations about AWS Config compliance changes',
        eventPattern: {
          source: ['aws.config'],
          detailType: ['Config Rules Compliance Change'],
          detail: { newEvaluationResult: { complianceType: ['NON_COMPLIANT'] } },
        },
        targets: [new eventTargets.SnsTopic(alertTopic)],
      });
    }

    // GitHub Actions OIDC
    // 長期Access Keyを保存せず、mainブランチとタグから一時認証する。
    // ECRへのpush、Migration Task実行、ECS Service更新に必要な権限を付与する。
    const githubOidcProviderArn = this.node.tryGetContext(
      'githubOidcProviderArn',
    );
    const githubOidcProvider = githubOidcProviderArn
      ? iam.OpenIdConnectProvider.fromOpenIdConnectProviderArn(
          this,
          'GitHubOidcProvider',
          String(githubOidcProviderArn),
        )
      : new iam.OpenIdConnectProvider(this, 'GitHubOidcProvider', {
          url: 'https://token.actions.githubusercontent.com',
          clientIds: ['sts.amazonaws.com'],
        });

    const githubActionsRole = new iam.Role(this, 'GitHubActionsRole', {
      roleName: 'guide-github-actions',
      description: 'OIDC role for ECR push and ECS deployments from GitHub',
      assumedBy: new iam.FederatedPrincipal(
        githubOidcProvider.openIdConnectProviderArn,
        {
          StringEquals: {
            'token.actions.githubusercontent.com:aud': 'sts.amazonaws.com',
          },
          StringLike: {
            'token.actions.githubusercontent.com:sub': [
              `repo:${githubOwner}/${githubRepository}:ref:refs/heads/main`,
              `repo:${githubOwner}/${githubRepository}:ref:refs/tags/*`,
            ],
          },
        },
        'sts:AssumeRoleWithWebIdentity',
      ),
    });
    applicationRepository.grantPullPush(githubActionsRole);
    githubActionsRole.addToPolicy(
      new iam.PolicyStatement({
        actions: ['ecs:DescribeServices', 'ecs:UpdateService'],
        resources: [service.serviceArn],
      }),
    );
    githubActionsRole.addToPolicy(
      new iam.PolicyStatement({
        actions: ['ecs:DescribeTasks', 'ecs:StopTask'],
        resources: ['*'],
        conditions: {
          ArnEquals: { 'ecs:cluster': cluster.clusterArn },
        },
      }),
    );
    githubActionsRole.addToPolicy(
      new iam.PolicyStatement({
        actions: [
          'ecs:RegisterTaskDefinition',
          'ecs:DescribeTaskDefinition',
          'ecs:DeregisterTaskDefinition',
          'ecs:TagResource',
        ],
        resources: ['*'],
      }),
    );
    githubActionsRole.addToPolicy(
      new iam.PolicyStatement({
        actions: ['ecs:RunTask'],
        resources: [
          this.formatArn({
            service: 'ecs',
            resource: 'task-definition',
            resourceName: 'guide-production-migration:*',
          }),
        ],
        conditions: {
          ArnEquals: { 'ecs:cluster': cluster.clusterArn },
        },
      }),
    );
    githubActionsRole.addToPolicy(
      new iam.PolicyStatement({
        actions: ['iam:PassRole'],
        resources: [executionRole.roleArn, taskRole.roleArn],
        conditions: {
          StringEquals: { 'iam:PassedToService': 'ecs-tasks.amazonaws.com' },
        },
      }),
    );

    // CloudFormation Outputs
    // GitHub ActionsがECRへのpush、Migration Task実行、ECS更新で利用する識別子と、
    // 運用時に必要なURL・Secret ARN・通知先を出力する。
    const publicSubnetIds = vpc
      .selectSubnets({ subnetType: ec2.SubnetType.PUBLIC })
      .subnetIds.join(',');

    new cdk.CfnOutput(this, 'ApplicationUrl', {
      value: applicationUrl,
      description: 'Route 53からALBへ向ける本番URL',
    });
    new cdk.CfnOutput(this, 'ApplicationRepositoryUri', {
      value: applicationRepository.repositoryUri,
      description: 'GitHub ActionsがDockerイメージをpushするECR URI',
    });
    new cdk.CfnOutput(this, 'EcsClusterName', {
      value: cluster.clusterName,
    });
    new cdk.CfnOutput(this, 'EcsServiceName', {
      value: service.serviceName,
    });
    new cdk.CfnOutput(this, 'ApplicationTaskDefinitionArn', {
      value: taskDefinition.taskDefinitionArn,
    });
    new cdk.CfnOutput(this, 'MigrationTaskDefinitionArn', {
      value: migrationTaskDefinition.taskDefinitionArn,
    });
    new cdk.CfnOutput(this, 'PublicSubnetIds', {
      value: publicSubnetIds,
      description: 'Migration Task実行時に指定するPublic Subnet IDs',
    });
    new cdk.CfnOutput(this, 'TaskSecurityGroupId', {
      value: taskSecurityGroup.securityGroupId,
      description: 'Migration Task実行時に指定するSecurity Group',
    });
    new cdk.CfnOutput(this, 'FilesBucketName', {
      value: filesBucket.bucketName,
    });
    new cdk.CfnOutput(this, 'DatabaseEndpoint', {
      value: database.dbInstanceEndpointAddress,
    });
    new cdk.CfnOutput(this, 'DatabaseSecretArn', {
      value: databaseSecret.secretArn,
    });
    new cdk.CfnOutput(this, 'AppKeySecretArn', {
      value: appKeySecret.secretArn,
    });
    new cdk.CfnOutput(this, 'GitHubActionsRoleArn', {
      value: githubActionsRole.roleArn,
    });
    new cdk.CfnOutput(this, 'OperationsTopicArn', {
      value: alertTopic.topicArn,
    });
    new cdk.CfnOutput(this, 'BootstrapNextStep', {
      value:
        'Push an image to ApplicationRepositoryUri, then deploy with -c appImageTag=<sha> -c desiredCount=2',
      description: 'ECR初回作成後にアプリを起動する手順',
    });
  }

  private numberContext(
    name: string,
    defaultValue: number,
    minimum: number,
    maximum: number,
  ): number {
    const rawValue = this.node.tryGetContext(name);
    const value = rawValue === undefined ? defaultValue : Number(rawValue);
    if (!Number.isInteger(value) || value < minimum || value > maximum) {
      throw new Error(
        `CDK context ${name} must be an integer between ${minimum} and ${maximum}`,
      );
    }
    return value;
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
