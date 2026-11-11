import * as cdk from 'aws-cdk-lib';
import * as acm from 'aws-cdk-lib/aws-certificatemanager';
import * as backup from 'aws-cdk-lib/aws-backup';
import * as cloudtrail from 'aws-cdk-lib/aws-cloudtrail';
import * as cloudwatch from 'aws-cdk-lib/aws-cloudwatch';
import * as cloudwatchActions from 'aws-cdk-lib/aws-cloudwatch-actions';
import * as ec2 from 'aws-cdk-lib/aws-ec2';
import * as ecr from 'aws-cdk-lib/aws-ecr';
import * as ecs from 'aws-cdk-lib/aws-ecs';
import * as elbv2 from 'aws-cdk-lib/aws-elasticloadbalancingv2';
import * as events from 'aws-cdk-lib/aws-events';
import * as iam from 'aws-cdk-lib/aws-iam';
import * as logs from 'aws-cdk-lib/aws-logs';
import * as rds from 'aws-cdk-lib/aws-rds';
import * as route53 from 'aws-cdk-lib/aws-route53';
import * as s3 from 'aws-cdk-lib/aws-s3';
import * as secretsmanager from 'aws-cdk-lib/aws-secretsmanager';
import * as sns from 'aws-cdk-lib/aws-sns';
import * as subscriptions from 'aws-cdk-lib/aws-sns-subscriptions';
import * as ssm from 'aws-cdk-lib/aws-ssm';
import * as synthetics from 'aws-cdk-lib/aws-synthetics';
import * as wafv2 from 'aws-cdk-lib/aws-wafv2';
import { Construct } from 'constructs';

/**
 * docs/aws_architecture_ecs.py を実装した本番向けスタック。
 *
 * 初回だけは ECR が空なので、次の順に手動で実行する（docs/AWS_DEPLOYMENT_DONE.md）。
 * 1. cdk deploy -c desiredCount=0 で ECR を含む基盤を作成
 * 2. Docker イメージを ECR へ push
 * 3. cdk deploy -c appImageTag=<commit-sha> -c desiredCount=2
 * 以降は GitHub Actions が appImageTag だけを渡してデプロイする。
 * desiredCount を渡さない場合、CloudFormation は現在のタスク数を変更しない。
 */
export class GuideStack extends cdk.Stack {
  constructor(scope: Construct, id: string, props?: cdk.StackProps) {
    super(scope, id, props);

    // デプロイ設定
    // イメージタグ、起動タスク数、任意機能の有効化をCDK Contextから受け取る。
    // desiredCount は初回構築（0）や手動での台数変更のときだけ渡す。
    // 省略時は DesiredCount をテンプレートに出さず、現在の台数と Auto Scaling に任せる。
    const desiredCount = this.optionalNumberContext('desiredCount', 0, 3);
    const isBootstrap = desiredCount === 0;
    // イメージタグは必須。latest のような可変タグに戻ってしまう事故を防ぐ。
    // 初回構築（desiredCount=0）だけは ECR が空なのでプレースホルダーを許す。
    const rawImageTag = String(
      this.node.tryGetContext('appImageTag') ?? '',
    ).trim();
    // エラー注釈は GuideStack のデプロイだけを止め、他スタック単体のデプロイは妨げない。
    if (!rawImageTag && !isBootstrap) {
      cdk.Annotations.of(this).addError(
        'CDK context appImageTag is required (e.g. -c appImageTag=<commit-sha>). ' +
          'Use -c desiredCount=0 only for the first bootstrap deploy.',
      );
    }
    if (rawImageTag === 'latest') {
      cdk.Annotations.of(this).addError(
        'appImageTag must be an immutable tag such as a commit SHA',
      );
    }
    const appImageTag = rawImageTag || 'bootstrap';
    // タスクにはパブリック IP があり外向き通信できるため、Interface Endpoint は既定で作らない。
    const enableInterfaceEndpoints = this.booleanContext(
      'enableInterfaceEndpoints',
      false,
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

    const alertEmailParameter = new cdk.CfnParameter(this, 'AlertEmail', {
      type: 'String',
      description: 'CloudWatch アラームの通知先メールアドレス（必須）',
      allowedPattern: '^[^@\\s]+@[^@\\s]+\\.[^@\\s]+$',
      constraintDescription: 'メールアドレスを指定してください',
    });

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
    albSecurityGroup.addIngressRule(
      ec2.Peer.anyIpv4(),
      ec2.Port.tcp(80),
      'HTTP from the Internet (redirected to HTTPS)',
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
    // S3 は無料の Gateway Endpoint を使う。ECR・Secrets Manager・CloudWatch Logs・
    // Parameter Store の Interface Endpoint は -c enableInterfaceEndpoints=true のときだけ作る。
    // （月額費用がかかり、パブリック IP を持つ現構成ではセキュリティ上の効果が小さいため）
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
        // 同じタグを上書きできないようにし、コミット SHA とイメージを 1 対 1 に保つ。
        imageTagMutability: ecr.TagMutability.IMMUTABLE,
        encryption: ecr.RepositoryEncryption.AES_256,
        lifecycleRules: [
          {
            // 1イメージが数百MB〜1GB（PHP + Next.js ビルド込み）あるため、
            // ロールバック先として十分な本数だけ残す。
            description: 'Keep the latest 10 deployable images',
            maxImageCount: 10,
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
      // セッション・キャッシュ・キューも DB に置くため、micro（1GB）ではなく small（2GB）にする。
      instanceType: ec2.InstanceType.of(
        ec2.InstanceClass.T4G,
        ec2.InstanceSize.SMALL,
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
      enablePerformanceInsights: true,
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
    // Fargate Taskを収容するClusterを作り、ログ出力を有効にする。
    // Container Insights は切る。CPU とメモリは ECS の標準メトリクスで取れており、
    // アラームもそちらで組んでいるため、追加課金のカスタムメトリクスを払う理由がない。
    // 詳細なタスク単位の内訳が必要になった時点で ENABLED / ENHANCED に戻す。
    const cluster = new ecs.Cluster(this, 'Cluster', {
      vpc,
      clusterName: 'guide-production',
      containerInsightsV2: ecs.ContainerInsights.DISABLED,
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
      // migrate は Migration タスクだけで実行し、Web タスクの起動時には行わない。
      RUN_MIGRATIONS_ON_START: 'false',
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
    // /healthz（Next.js → nginx → Laravel の /up）をヘルスチェックに使い、
    // 画面を返す Next.js と API の Laravel の両方が動いていることを確認する。
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
            'curl --fail --silent http://127.0.0.1/healthz || exit 1',
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
    // desiredCount 未指定時は DesiredCount を出力せず、デプロイで台数が変わらないようにする。
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
      minCapacity: isBootstrap ? 0 : 2,
      // 上限は 3。到達しなければ費用はかからないが、実需と無関係な
      // スケールアウト（メモリ基準の張り付きなど）が起きたときの上限額を決める。
      // 2 台で捌けない負荷では db.t4g.small 側が先に詰まるため、
      // Web タスクだけを横に伸ばしてもこれ以上は効果が出ない。
      maxCapacity: 3,
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
    // 新Taskが/healthzのヘルスチェックに成功してからリクエストを流す。
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
          path: '/healthz',
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

    // HTTP（80番）は HTTPS へ 301 リダイレクトする。
    alb.addListener('HttpRedirectListener', {
      port: 80,
      protocol: elbv2.ApplicationProtocol.HTTP,
      open: false,
      defaultAction: elbv2.ListenerAction.redirect({
        protocol: 'HTTPS',
        port: '443',
        permanent: true,
      }),
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
          name: 'AWSManagedRulesSQLiRuleSet',
          priority: 2,
          overrideAction: { none: {} },
          statement: {
            managedRuleGroupStatement: {
              vendorName: 'AWS',
              name: 'AWSManagedRulesSQLiRuleSet',
            },
          },
          visibilityConfig: {
            cloudWatchMetricsEnabled: true,
            metricName: 'guide-sqli-rules',
            sampledRequestsEnabled: true,
          },
        },
        {
          name: 'IpRateLimit',
          priority: 3,
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

    // WAF ログ
    // 遮断した判定だけを CloudWatch Logs に残す。許可まで残すと全リクエストが
    // ログ取り込みの課金対象になり、量がトラフィック次第で読めなくなる。
    // 誤検知を追うときは Conditions に COUNT を足して一時的に広げる。
    // Cookie と Authorization ヘッダーはログに残さない。
    const wafLogGroupName = 'aws-waf-logs-guide-production';
    const wafLogGroup = new logs.LogGroup(this, 'WafLogGroup', {
      logGroupName: wafLogGroupName,
      retention: logs.RetentionDays.THREE_MONTHS,
      removalPolicy: cdk.RemovalPolicy.RETAIN,
    });
    const wafLogging = new wafv2.CfnLoggingConfiguration(
      this,
      'AlbWebAclLogging',
      {
        resourceArn: webAcl.attrArn,
        // WAF は末尾に :* が付かない Log Group ARN だけを受け付ける。
        logDestinationConfigs: [
          this.formatArn({
            service: 'logs',
            resource: 'log-group',
            resourceName: wafLogGroupName,
            arnFormat: cdk.ArnFormat.COLON_RESOURCE_NAME,
          }),
        ],
        // CloudFormation の LoggingFilter はパスカルケースのキーを取る。
        loggingFilter: {
          DefaultBehavior: 'DROP',
          Filters: [
            {
              Behavior: 'KEEP',
              Requirement: 'MEETS_ANY',
              Conditions: [{ ActionCondition: { Action: 'BLOCK' } }],
            },
          ],
        },
        redactedFields: [
          { singleHeader: { Name: 'cookie' } },
          { singleHeader: { Name: 'authorization' } },
        ],
      },
    );
    wafLogging.node.addDependency(wafLogGroup);

    // CloudWatch監視とSNS通知
    // ECS・ALB・RDSの主要メトリクスを監視し、閾値超過時にSNSへ通知する。
    const alertTopic = new sns.Topic(this, 'OperationsTopic', {
      topicName: 'guide-production-operations',
      displayName: 'Guide production operations',
    });
    alertTopic.addSubscription(
      new subscriptions.EmailSubscription(alertEmailParameter.valueAsString),
    );

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

    // 全タスク停止などで ALB 自身が 502/503/504 を返している。
    alb.metrics
      .httpCodeElb(elbv2.HttpCodeElb.ELB_5XX_COUNT, {
        period: cdk.Duration.minutes(1),
        statistic: 'Sum',
      })
      .createAlarm(this, 'Elb5xxAlarm', {
        alarmName: 'guide-production-elb-5xx',
        threshold: 5,
        evaluationPeriods: 2,
        datapointsToAlarm: 2,
        treatMissingData: cloudwatch.TreatMissingData.NOT_BREACHING,
      })
      .addAlarmAction(alarmAction);

    // 正常なタスクが 1 つも無い。データが来ない場合も障害として扱う。
    targetGroup.metrics
      .healthyHostCount({
        period: cdk.Duration.minutes(1),
        statistic: 'Minimum',
      })
      .createAlarm(this, 'NoHealthyTargetsAlarm', {
        alarmName: 'guide-production-no-healthy-targets',
        threshold: 1,
        comparisonOperator: cloudwatch.ComparisonOperator.LESS_THAN_THRESHOLD,
        evaluationPeriods: 2,
        datapointsToAlarm: 2,
        treatMissingData: cloudwatch.TreatMissingData.BREACHING,
      })
      .addAlarmAction(alarmAction);

    // RDS の容量・メモリ・接続数・CPU クレジット
    database
      .metricFreeStorageSpace({ period: cdk.Duration.minutes(5) })
      .createAlarm(this, 'LowDatabaseStorageAlarm', {
        alarmName: 'guide-production-low-database-storage',
        threshold: 2 * 1024 ** 3,
        comparisonOperator: cloudwatch.ComparisonOperator.LESS_THAN_THRESHOLD,
        evaluationPeriods: 1,
        treatMissingData: cloudwatch.TreatMissingData.NOT_BREACHING,
      })
      .addAlarmAction(alarmAction);
    database
      .metricFreeableMemory({ period: cdk.Duration.minutes(5) })
      .createAlarm(this, 'LowDatabaseMemoryAlarm', {
        alarmName: 'guide-production-low-database-memory',
        threshold: 128 * 1024 ** 2,
        comparisonOperator: cloudwatch.ComparisonOperator.LESS_THAN_THRESHOLD,
        evaluationPeriods: 3,
        datapointsToAlarm: 3,
        treatMissingData: cloudwatch.TreatMissingData.NOT_BREACHING,
      })
      .addAlarmAction(alarmAction);
    database
      .metricDatabaseConnections({ period: cdk.Duration.minutes(5) })
      .createAlarm(this, 'HighDatabaseConnectionsAlarm', {
        alarmName: 'guide-production-high-database-connections',
        // db.t4g.small の max_connections は約 200。
        threshold: 160,
        evaluationPeriods: 2,
        datapointsToAlarm: 2,
        treatMissingData: cloudwatch.TreatMissingData.NOT_BREACHING,
      })
      .addAlarmAction(alarmAction);
    database
      .metric('CPUCreditBalance', {
        period: cdk.Duration.minutes(5),
        statistic: 'Minimum',
      })
      .createAlarm(this, 'LowDatabaseCpuCreditAlarm', {
        alarmName: 'guide-production-low-database-cpu-credit',
        threshold: 30,
        comparisonOperator: cloudwatch.ComparisonOperator.LESS_THAN_THRESHOLD,
        evaluationPeriods: 2,
        datapointsToAlarm: 2,
        treatMissingData: cloudwatch.TreatMissingData.NOT_BREACHING,
      })
      .addAlarmAction(alarmAction);

    // 外形監視
    // AWS の外から本番 URL の /healthz を 15 分ごとに確認する。
    // 実行回数がそのまま課金なので、5 分間隔（月 8,640 回）までは要らない。
    // ALB のターゲットヘルスチェックと no-healthy-targets アラームが別にあり、
    // この Canary が固有に見ているのは DNS・証明書・WAF の外側からの経路。
    // DNS・証明書・WAF・ALB・アプリのどこで壊れても検知できる。
    const healthCanary = new synthetics.Canary(this, 'HealthCanary', {
      canaryName: 'guide-prod-health',
      runtime: synthetics.Runtime.SYNTHETICS_NODEJS_PUPPETEER_9_1,
      schedule: synthetics.Schedule.rate(cdk.Duration.minutes(15)),
      test: synthetics.Test.custom({
        handler: 'index.handler',
        code: synthetics.Code.fromInline(`
const https = require('https');
exports.handler = async () => {
  const url = process.env.TARGET_URL;
  await new Promise((resolve, reject) => {
    const request = https.get(url, (response) => {
      response.resume();
      if (response.statusCode === 200) {
        resolve();
      } else {
        reject(new Error(url + ' returned ' + response.statusCode));
      }
    });
    request.setTimeout(10000, () => request.destroy(new Error('timeout')));
    request.on('error', reject);
  });
};
`),
      }),
      environmentVariables: {
        TARGET_URL: cdk.Fn.join('', [applicationUrl, '/healthz']),
      },
      artifactsBucketLifecycleRules: [
        { expiration: cdk.Duration.days(30) },
      ],
    });
    healthCanary
      // period は Canary の実行間隔と揃える。実行間隔より短いと、
      // データの無い期間が treatMissingData: BREACHING に拾われて誤報になる。
      .metricSuccessPercent({ period: cdk.Duration.minutes(15) })
      .createAlarm(this, 'HealthCanaryAlarm', {
        alarmName: 'guide-production-health-check-failed',
        threshold: 100,
        comparisonOperator: cloudwatch.ComparisonOperator.LESS_THAN_THRESHOLD,
        evaluationPeriods: 2,
        datapointsToAlarm: 2,
        treatMissingData: cloudwatch.TreatMissingData.BREACHING,
      })
      .addAlarmAction(alarmAction);

    // すべてのアラームで、復旧したときも通知する。
    for (const construct of this.node.findAll()) {
      if (construct instanceof cloudwatch.Alarm) {
        construct.addOkAction(alarmAction);
      }
    }

    // AWS Backup
    // RDS 自動バックアップ（7 日）に加えて、日次 35 日・月次 1 年の長期保管を行う。
    const backupVault = new backup.BackupVault(this, 'BackupVault', {
      backupVaultName: 'guide-production',
      removalPolicy: cdk.RemovalPolicy.RETAIN,
    });
    const backupPlan = new backup.BackupPlan(this, 'BackupPlan', {
      backupPlanName: 'guide-production',
      backupVault,
      backupPlanRules: [
        new backup.BackupPlanRule({
          // RDS 自身の自動バックアップ（7 日・PITR 可）と重なる範囲なので、
          // 35 日は持たない。2 週間前まで戻せれば個人開発では足りる。
          ruleName: 'Daily',
          scheduleExpression: events.Schedule.cron({ hour: '18', minute: '0' }),
          deleteAfter: cdk.Duration.days(14),
        }),
        new backup.BackupPlanRule({
          ruleName: 'Monthly',
          scheduleExpression: events.Schedule.cron({
            day: '1',
            hour: '18',
            minute: '30',
          }),
          // 1 年分は持たない。四半期ぶん遡れれば足りる。
          deleteAfter: cdk.Duration.days(90),
        }),
      ],
    });
    backupPlan.addSelection('Database', {
      resources: [backup.BackupResource.fromRdsDatabaseInstance(database)],
    });

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
        actions: ['ecr:DescribeImages'],
        resources: [applicationRepository.repositoryArn],
      }),
    );
    // CDK のデプロイは cdk bootstrap が作った Role を引き受けて行う。
    githubActionsRole.addToPolicy(
      new iam.PolicyStatement({
        actions: ['sts:AssumeRole'],
        resources: [
          this.formatArn({
            service: 'iam',
            region: '',
            resource: 'role',
            resourceName: 'cdk-*',
          }),
        ],
      }),
    );
    githubActionsRole.addToPolicy(
      new iam.PolicyStatement({
        actions: ['cloudformation:DescribeStacks'],
        resources: [this.stackId],
      }),
    );
    // Migration タスクのログを CI に表示する。
    migrationLogGroup.grantRead(githubActionsRole);
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
    new cdk.CfnOutput(this, 'MigrationLogGroupName', {
      value: migrationLogGroup.logGroupName,
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

  private optionalNumberContext(
    name: string,
    minimum: number,
    maximum: number,
  ): number | undefined {
    const rawValue = this.node.tryGetContext(name);
    if (rawValue === undefined) {
      return undefined;
    }
    const value = Number(rawValue);
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
