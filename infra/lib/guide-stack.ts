import * as path from 'node:path';
import * as cdk from 'aws-cdk-lib';
import * as acm from 'aws-cdk-lib/aws-certificatemanager';
import * as cloudwatch from 'aws-cdk-lib/aws-cloudwatch';
import * as cloudwatchActions from 'aws-cdk-lib/aws-cloudwatch-actions';
import * as ec2 from 'aws-cdk-lib/aws-ec2';
import * as ecs from 'aws-cdk-lib/aws-ecs';
import * as elbv2 from 'aws-cdk-lib/aws-elasticloadbalancingv2';
import * as iam from 'aws-cdk-lib/aws-iam';
import * as lambda from 'aws-cdk-lib/aws-lambda';
import * as logs from 'aws-cdk-lib/aws-logs';
import * as route53 from 'aws-cdk-lib/aws-route53';
import * as secretsmanager from 'aws-cdk-lib/aws-secretsmanager';
import * as sns from 'aws-cdk-lib/aws-sns';
import * as subscriptions from 'aws-cdk-lib/aws-sns-subscriptions';
import * as ssm from 'aws-cdk-lib/aws-ssm';
import * as synthetics from 'aws-cdk-lib/aws-synthetics';
import * as wafv2 from 'aws-cdk-lib/aws-wafv2';
import { Construct } from 'constructs';
import { DATABASE_NAME, GuideEnvironmentConfig, resourcePrefix } from './environments';
import { GuideDataStack } from './guide-data-stack';

export interface GuideStackProps extends cdk.StackProps {
  readonly config: GuideEnvironmentConfig;
  /** 同じ環境の VPC・RDS・S3・ECR・Secret を持つスタック。 */
  readonly data: GuideDataStack;
}

/**
 * docs/aws_architecture_ecs.py のうち、作り直せるもの（ECS・ALB・WAF・監視・デプロイ用 Role）を
 * 持つスタック。DB やバケットなど消えると戻せないものは GuideDataStack にある。
 *
 * 初回だけは次の順に手動で実行する（docs/AWS_DEPLOYMENT_DONE.md）。
 * 1. GuideDataStack をデプロイ（ECR・RDS・Secret ができる）
 * 2. Secret に値を入れ、Docker イメージを ECR へ push
 * 3. cdk deploy -c appImageTag=<commit-sha> -c desiredCount=<台数> でこのスタックをデプロイ
 * 以降は GitHub Actions が appImageTag だけを渡してデプロイする。
 * desiredCount を渡さない場合、CloudFormation は現在のタスク数を変更しない。
 */
export class GuideStack extends cdk.Stack {
  constructor(scope: Construct, id: string, props: GuideStackProps) {
    super(scope, id, props);

    const { config, data } = props;
    const prefix = resourcePrefix(config);
    const vpc = data.vpc;
    const database = data.database;
    const filesBucket = data.filesBucket;
    const auditBucket = data.auditBucket;
    const applicationRepository = data.applicationRepository;
    const appKeySecret = data.appKeySecret;
    const googleOAuthSecret = data.googleOAuthSecret;
    // 作り直せるログは、立てて壊す環境では残さない（同名での作り直しが衝突するため）。
    const logRemovalPolicy = config.retainData
      ? cdk.RemovalPolicy.RETAIN
      : cdk.RemovalPolicy.DESTROY;

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
    // 既存のRoute 53 Hosted Zoneと独自ドメインをデプロイ時のParameterとして受け取る。
    // 秘密の値はここで受け取らない（GuideDataStack の Secret と、下の Slack 用 Secret を参照）。
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
    const alertEmailParameter = new cdk.CfnParameter(this, 'AlertEmail', {
      type: 'String',
      description: 'CloudWatch アラームの通知先メールアドレス（必須）',
      allowedPattern: '^[^@\\s]+@[^@\\s]+\\.[^@\\s]+$',
      constraintDescription: 'メールアドレスを指定してください',
    });

    // Slack 障害通知（docs/SLACK_ALERTS.md）
    // 既定では何も作らない。-c enableSlackAlerts=true のときだけ Webhook URL を入れる Secret を作る。
    // URL は CloudFormation のパラメータで受け取らず、デプロイ後に put-secret-value で入れる。
    // 値を入れるまでは "unset" で、Laravel・Next.js・Lambda はいずれも http(s):// で
    // 始まらない値を未設定として扱い、何も送らない。
    const enableSlackAlerts = this.booleanContext('enableSlackAlerts', false);
    const slackWebhookSecret = enableSlackAlerts
      ? new secretsmanager.Secret(this, 'SlackWebhookSecret', {
          // 名前は付けない。enableSlackAlerts を切ると Secret は削除予約（既定 30 日）に入るため、
          // 固定名だと入れ直しのデプロイが同名衝突で失敗する。ECS と Lambda は ARN で参照する。
          description:
            'Slack Incoming Webhook URL ({"url": "https://hooks.slack.com/services/..."}); set with put-secret-value after deploy',
          // この generateSecretString は変えないこと。変えると CloudFormation が値を作り直し、
          // 手で入れた URL が "unset" に戻る。
          generateSecretString: {
            secretStringTemplate: JSON.stringify({ url: 'unset' }),
            generateStringKey: 'placeholder',
            excludePunctuation: true,
          },
        })
      : undefined;

    const domainName = domainNameParameter.valueAsString;
    const applicationUrl = cdk.Fn.join('', ['https://', domainName]);

    // Security Group
    // Internet -> ALB -> Fargate -> RDSの順に必要な通信だけを許可する。
    // Fargateの80番はALBからのみ、RDSの5432番はFargateからのみ許可する。
    // VPC と RDS の Security Group は GuideDataStack にある。
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

    // RDS の Security Group は GuideDataStack にあるが、ルールはこちらのスタックに作る。
    // addIngressRule を使うとルールが GuideDataStack 側に作られ、スタック同士が互いに依存する。
    new ec2.CfnSecurityGroupIngress(this, 'DatabaseIngressFromTasks', {
      groupId: data.databaseSecurityGroup.securityGroupId,
      sourceSecurityGroupId: taskSecurityGroup.securityGroupId,
      ipProtocol: 'tcp',
      fromPort: 5432,
      toPort: 5432,
      description: 'PostgreSQL from Fargate tasks only',
    });

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
    // S3 の Gateway Endpoint（無料）は GuideDataStack にある。ECR・Secrets Manager・CloudWatch Logs・
    // Parameter Store の Interface Endpoint は -c enableInterfaceEndpoints=true のときだけ作る。
    // （月額費用がかかり、パブリック IP を持つ現構成ではセキュリティ上の効果が小さいため）
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
        new ec2.InterfaceVpcEndpoint(this, `${endpointId}Endpoint`, {
          vpc,
          service,
          privateDnsEnabled: true,
          securityGroups: [endpointSecurityGroup],
          subnets: { subnetType: ec2.SubnetType.PUBLIC },
        });
      }
    }

    // Parameter Store
    // 秘密ではないアプリ設定を保存し、ECS Task起動時に環境変数として読み込む。
    const appNameParameter = new ssm.StringParameter(
      this,
      'AppNameParameter',
      {
        parameterName: `/guide/${config.name}/app-name`,
        stringValue: 'Guide',
      },
    );
    const appEnvironmentParameter = new ssm.StringParameter(
      this,
      'AppEnvironmentParameter',
      {
        parameterName: `/guide/${config.name}/app-environment`,
        stringValue: config.name,
      },
    );
    const appUrlParameter = new ssm.StringParameter(
      this,
      'AppUrlParameter',
      {
        parameterName: `/guide/${config.name}/app-url`,
        stringValue: applicationUrl,
      },
    );

    // ECS用IAM Role
    // Execution RoleはECR・Secrets・Parameter Store・Logsへの起動時アクセスに使う。
    // Task Roleは実行中のLaravelが添付ファイル用S3へアクセスするために使う。
    // Web と Migration で Role を分ける。DB のマスターユーザーの Secret を読めるのは
    // Migration タスクの Execution Role だけで、Migration タスクは S3 に触れない。
    const executionRolePolicy = iam.ManagedPolicy.fromAwsManagedPolicyName(
      'service-role/AmazonECSTaskExecutionRolePolicy',
    );
    const executionRole = new iam.Role(this, 'EcsExecutionRole', {
      assumedBy: new iam.ServicePrincipal('ecs-tasks.amazonaws.com'),
      description: 'Pull Guide images and load web task secrets/configuration',
      managedPolicies: [executionRolePolicy],
    });
    const migrationExecutionRole = new iam.Role(
      this,
      'MigrationExecutionRole',
      {
        assumedBy: new iam.ServicePrincipal('ecs-tasks.amazonaws.com'),
        description:
          'Pull Guide images and load migration task secrets (incl. DB master user)',
        managedPolicies: [executionRolePolicy],
      },
    );

    const taskRole = new iam.Role(this, 'EcsTaskRole', {
      assumedBy: new iam.ServicePrincipal('ecs-tasks.amazonaws.com'),
      description: 'Least-privilege AWS access used by the Laravel app',
    });
    const migrationTaskRole = new iam.Role(this, 'MigrationTaskRole', {
      assumedBy: new iam.ServicePrincipal('ecs-tasks.amazonaws.com'),
      description: 'Migration task; needs no AWS API access',
    });

    for (const role of [executionRole, migrationExecutionRole]) {
      applicationRepository.grantPull(role);
      data.databaseAppSecret.grantRead(role);
      appKeySecret.grantRead(role);
      googleOAuthSecret.grantRead(role);
      appNameParameter.grantRead(role);
      appEnvironmentParameter.grantRead(role);
      appUrlParameter.grantRead(role);
    }
    data.databaseAdminSecret.grantRead(migrationExecutionRole);
    filesBucket.grantReadWrite(taskRole);

    // ECS ClusterとCloudWatch Logs
    // Fargate Taskを収容するClusterを作り、ログ出力を有効にする。
    // Container Insights は切る。CPU とメモリは ECS の標準メトリクスで取れており、
    // アラームもそちらで組んでいるため、追加課金のカスタムメトリクスを払う理由がない。
    // 詳細なタスク単位の内訳が必要になった時点で ENABLED / ENHANCED に戻す。
    const cluster = new ecs.Cluster(this, 'Cluster', {
      vpc,
      clusterName: prefix,
      containerInsightsV2: ecs.ContainerInsights.DISABLED,
    });

    const applicationLogGroup = new logs.LogGroup(
      this,
      'ApplicationLogGroup',
      {
        logGroupName: `/ecs/${prefix}/application`,
        retention: logs.RetentionDays.ONE_MONTH,
        removalPolicy: logRemovalPolicy,
      },
    );
    const migrationLogGroup = new logs.LogGroup(this, 'MigrationLogGroup', {
      logGroupName: `/ecs/${prefix}/migration`,
      retention: logs.RetentionDays.ONE_MONTH,
      removalPolicy: logRemovalPolicy,
    });

    // Laravel共通設定
    // Web TaskとMigration Taskで共通利用する環境変数とSecretをまとめる。
    const commonEnvironment: Record<string, string> = {
      APP_DEBUG: 'false',
      APP_TIMEZONE: 'Asia/Tokyo',
      // stderr（CloudWatch Logs）へ出しつつ、error 以上は slack チャンネルへも送る。
      // slack チャンネルは stack 経由でしか効かないため、LOG_CHANNEL は stack にする。
      // Webhook URL が無いあいだ slack チャンネルは何もしないので、常にこの構成で渡す。
      LOG_CHANNEL: 'stack',
      LOG_STACK: 'stderr,slack',
      LOG_SLACK_LEVEL: 'error',
      LOG_LEVEL: 'info',
      DB_CONNECTION: 'pgsql',
      DB_HOST: database.dbInstanceEndpointAddress,
      DB_PORT: database.dbInstanceEndpointPort,
      DB_DATABASE: DATABASE_NAME,
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
      GOOGLE_CLIENT_ID: ecs.Secret.fromSecretsManager(
        googleOAuthSecret,
        'client_id',
      ),
      GOOGLE_CLIENT_SECRET: ecs.Secret.fromSecretsManager(
        googleOAuthSecret,
        'client_secret',
      ),
    };
    if (slackWebhookSecret) {
      // Web タスクは 1 コンテナで nginx・PHP-FPM・Next.js を動かす。
      // Laravel は LOG_SLACK_WEBHOOK_URL、Next.js は SLACK_WEBHOOK_URL を読むため、
      // 同じ Secret を両方の名前で渡す。
      commonSecrets.LOG_SLACK_WEBHOOK_URL = ecs.Secret.fromSecretsManager(
        slackWebhookSecret,
        'url',
      );
      commonSecrets.SLACK_WEBHOOK_URL = ecs.Secret.fromSecretsManager(
        slackWebhookSecret,
        'url',
      );
      slackWebhookSecret.grantRead(executionRole);
      slackWebhookSecret.grantRead(migrationExecutionRole);
    }
    // DB の接続ユーザーはタスクごとに変える。
    // Web はテーブルの読み書きだけができるユーザー、Migration は DDL を流すマスターユーザー。
    const webSecrets: Record<string, ecs.Secret> = {
      ...commonSecrets,
      DB_USERNAME: ecs.Secret.fromSecretsManager(
        data.databaseAppSecret,
        'username',
      ),
      DB_PASSWORD: ecs.Secret.fromSecretsManager(
        data.databaseAppSecret,
        'password',
      ),
    };
    const migrationSecrets: Record<string, ecs.Secret> = {
      ...commonSecrets,
      DB_USERNAME: ecs.Secret.fromSecretsManager(
        data.databaseAdminSecret,
        'username',
      ),
      DB_PASSWORD: ecs.Secret.fromSecretsManager(
        data.databaseAdminSecret,
        'password',
      ),
      // db:provision-app-user が作成・更新するアプリ用ユーザー。
      DB_APP_USERNAME: ecs.Secret.fromSecretsManager(
        data.databaseAppSecret,
        'username',
      ),
      DB_APP_PASSWORD: ecs.Secret.fromSecretsManager(
        data.databaseAppSecret,
        'password',
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
        family: `${prefix}-application`,
        cpu: config.web.cpu,
        memoryLimitMiB: config.web.memoryMiB,
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
        secrets: webSecrets,
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
    // デプロイ時に一度だけ実行する単発Taskを定義する。マスターユーザーで接続し、
    // 1. db:provision-app-user で Web タスク用のユーザーを作成・更新（権限とパスワードを Secret に合わせる）
    // 2. php artisan migrate
    // の順に流す。--isolatedにより、誤って複数起動した場合の同時実行を防ぐ。
    // --isolated のロックは CACHE_STORE=database の cache_locks テーブルに取るため、
    // テーブルが 1 つも無い空の DB では --isolated 付きの migrate そのものが失敗する。
    // migrations テーブルが無い（migrate:status が失敗する）初回だけ、ロック無しで先に流す。
    const migrationCommand = [
      'php artisan db:provision-app-user',
      '{ php artisan migrate:status > /dev/null 2>&1 || php artisan migrate --force; }',
      'php artisan migrate --force --isolated',
    ].join(' && ');
    const migrationTaskDefinition = new ecs.FargateTaskDefinition(
      this,
      'MigrationTaskDefinition',
      {
        family: `${prefix}-migration`,
        cpu: 256,
        memoryLimitMiB: 512,
        runtimePlatform: {
          cpuArchitecture: ecs.CpuArchitecture.ARM64,
          operatingSystemFamily: ecs.OperatingSystemFamily.LINUX,
        },
        executionRole: migrationExecutionRole,
        taskRole: migrationTaskRole,
      },
    );
    migrationTaskDefinition.addContainer('MigrationContainer', {
      containerName: 'migration',
      image,
      command: ['sh', '-c', migrationCommand],
      environment: commonEnvironment,
      secrets: migrationSecrets,
      essential: true,
      logging: ecs.LogDrivers.awsLogs({
        logGroup: migrationLogGroup,
        streamPrefix: 'migration',
      }),
    });

    // ECS ServiceとApplication Auto Scaling
    // Web Taskの必要数を維持し、障害Taskを自動交換する。
    // 台数の下限と上限は環境ごとの設定（environments.ts）で決め、CPU・メモリ使用率に応じて増減する。
    // desiredCount 未指定時は DesiredCount を出力せず、デプロイで台数が変わらないようにする。
    const service = new ecs.FargateService(this, 'ApplicationService', {
      cluster,
      serviceName: `${prefix}-web`,
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
      minCapacity: isBootstrap ? 0 : config.web.minTasks,
      // 上限に到達しなければ費用はかからないが、実需と無関係な
      // スケールアウト（メモリ基準の張り付きなど）が起きたときの上限額を決める。
      maxCapacity: config.web.maxTasks,
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
      loadBalancerName: prefix,
      securityGroup: albSecurityGroup,
      vpcSubnets: { subnetType: ec2.SubnetType.PUBLIC },
      deletionProtection: config.retainData,
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
      name: `${prefix}-alb`,
      scope: 'REGIONAL',
      defaultAction: { allow: {} },
      visibilityConfig: {
        cloudWatchMetricsEnabled: true,
        metricName: `${prefix}-alb-waf`,
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
    const wafLogGroupName = `aws-waf-logs-${prefix}`;
    const wafLogGroup = new logs.LogGroup(this, 'WafLogGroup', {
      logGroupName: wafLogGroupName,
      retention: logs.RetentionDays.THREE_MONTHS,
      removalPolicy: logRemovalPolicy,
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
      topicName: `${prefix}-operations`,
      displayName: `Guide ${config.name} operations`,
    });
    alertTopic.addSubscription(
      new subscriptions.EmailSubscription(alertEmailParameter.valueAsString),
    );

    // アラームの発生と復旧を Slack へ転送する Lambda。
    // Webhook URL は環境変数に入れず、実行時に Secrets Manager から読む。
    // 環境変数だと、関数の設定を見られる人全員に URL が平文で見えてしまう。
    // AWS SDK v3 は Node.js 22 ランタイムに同梱されているので、パッケージには含めない。
    let slackNotifier: lambda.Function | undefined;
    if (slackWebhookSecret) {
      const slackNotifierLogGroup = new logs.LogGroup(
        this,
        'SlackAlarmNotifierLogGroup',
        {
          logGroupName: `/aws/lambda/${prefix}-slack-alarm-notifier`,
          retention: logs.RetentionDays.ONE_MONTH,
          // 通知の送信ログだけなので残さない。
          // 残すと enableSlackAlerts を切って入れ直すときに同名で衝突する。
          removalPolicy: cdk.RemovalPolicy.DESTROY,
        },
      );
      slackNotifier = new lambda.Function(this, 'SlackAlarmNotifier', {
        functionName: `${prefix}-slack-alarm-notifier`,
        description: 'CloudWatch アラームの状態変化を Slack へ転送する',
        runtime: lambda.Runtime.NODEJS_22_X,
        handler: 'index.handler',
        code: lambda.Code.fromAsset(
          path.join(__dirname, '../lambda/slack-alarm-notifier'),
          // 型定義はテスト専用なので、デプロイパッケージには含めない。
          { exclude: ['*.d.mts'] },
        ),
        timeout: cdk.Duration.seconds(10),
        memorySize: 128,
        logGroup: slackNotifierLogGroup,
        environment: {
          SLACK_WEBHOOK_SECRET_ARN: slackWebhookSecret.secretArn,
        },
      });
      slackWebhookSecret.grantRead(slackNotifier);
      alertTopic.addSubscription(
        new subscriptions.LambdaSubscription(slackNotifier),
      );
    }

    const alarmAction = new cloudwatchActions.SnsAction(alertTopic);
    service
      .metricCpuUtilization({ period: cdk.Duration.minutes(1) })
      .createAlarm(this, 'HighTaskCpuAlarm', {
        alarmName: `${prefix}-high-task-cpu`,
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
        alarmName: `${prefix}-target-5xx`,
        threshold: 5,
        evaluationPeriods: 2,
        datapointsToAlarm: 2,
        treatMissingData: cloudwatch.TreatMissingData.NOT_BREACHING,
      })
      .addAlarmAction(alarmAction);
    database
      .metricCPUUtilization({ period: cdk.Duration.minutes(1) })
      .createAlarm(this, 'HighDatabaseCpuAlarm', {
        alarmName: `${prefix}-high-database-cpu`,
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
        alarmName: `${prefix}-elb-5xx`,
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
        alarmName: `${prefix}-no-healthy-targets`,
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
        alarmName: `${prefix}-low-database-storage`,
        threshold: 2 * 1024 ** 3,
        comparisonOperator: cloudwatch.ComparisonOperator.LESS_THAN_THRESHOLD,
        evaluationPeriods: 1,
        treatMissingData: cloudwatch.TreatMissingData.NOT_BREACHING,
      })
      .addAlarmAction(alarmAction);
    database
      .metricFreeableMemory({ period: cdk.Duration.minutes(5) })
      .createAlarm(this, 'LowDatabaseMemoryAlarm', {
        alarmName: `${prefix}-low-database-memory`,
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
        alarmName: `${prefix}-high-database-connections`,
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
        alarmName: `${prefix}-low-database-cpu-credit`,
        threshold: 30,
        comparisonOperator: cloudwatch.ComparisonOperator.LESS_THAN_THRESHOLD,
        evaluationPeriods: 2,
        datapointsToAlarm: 2,
        treatMissingData: cloudwatch.TreatMissingData.NOT_BREACHING,
      })
      .addAlarmAction(alarmAction);

    // 外形監視
    // AWS の外から公開 URL の /healthz を定期的に確認する。
    // ALB のターゲットヘルスチェックと no-healthy-targets アラームが別にあり、
    // この Canary が固有に見ているのは DNS・証明書・WAF の外側からの経路。
    // DNS・証明書・WAF・ALB・アプリのどこで壊れても検知できる。
    // 実行回数がそのまま課金になるので、間隔は環境ごとの設定（environments.ts）で決める。
    if (config.canaryIntervalMinutes !== undefined) {
      const canaryInterval = cdk.Duration.minutes(config.canaryIntervalMinutes);
      const healthCanary = new synthetics.Canary(this, 'HealthCanary', {
        // Canary 名は 21 文字までなので環境名を縮める。
        canaryName: `guide-${config.name === 'production' ? 'prod' : 'stg'}-health`,
        runtime: synthetics.Runtime.SYNTHETICS_NODEJS_PUPPETEER_9_1,
        schedule: synthetics.Schedule.rate(canaryInterval),
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
        .metricSuccessPercent({ period: canaryInterval })
        .createAlarm(this, 'HealthCanaryAlarm', {
          alarmName: `${prefix}-health-check-failed`,
          threshold: 100,
          comparisonOperator:
            cloudwatch.ComparisonOperator.LESS_THAN_THRESHOLD,
          evaluationPeriods: 2,
          datapointsToAlarm: 2,
          treatMissingData: cloudwatch.TreatMissingData.BREACHING,
        })
        .addAlarmAction(alarmAction);
    }

    // すべてのアラームで、復旧したときも通知する。
    for (const construct of this.node.findAll()) {
      if (construct instanceof cloudwatch.Alarm) {
        construct.addOkAction(alarmAction);
      }
    }

    // GitHub Actions OIDC
    // 長期Access Keyを保存せず、GitHub の Environment を指定したジョブだけが一時認証で引き受ける。
    // ブランチやタグではなく Environment で絞るのは、Environment 側の保護ルール
    // （デプロイできるブランチの制限、承認者）を必ず通させるため。
    // OIDC Provider はアカウントに 1 つしか作れないので GuideGovernanceStack が持つ。
    const githubOidcProviderArn = this.formatArn({
      service: 'iam',
      region: '',
      resource: 'oidc-provider',
      resourceName: 'token.actions.githubusercontent.com',
    });

    const githubActionsRole = new iam.Role(this, 'GitHubActionsRole', {
      roleName: `${prefix}-github-actions`,
      description: `OIDC role for ECR push and ECS deployments to ${config.name} from GitHub`,
      assumedBy: new iam.FederatedPrincipal(
        githubOidcProviderArn,
        {
          StringEquals: {
            'token.actions.githubusercontent.com:aud': 'sts.amazonaws.com',
            'token.actions.githubusercontent.com:sub': `repo:${githubOwner}/${githubRepository}:environment:${config.githubEnvironment}`,
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
    // 使うのは deploy / file-publishing / lookup の 3 つだけなので、cdk-* 全体ではなく名指しする
    // （Docker イメージは CDK のアセットにしていないため image-publishing は要らない）。
    // 注意: この先の CloudFormation 実行 Role（cdk-…-cfn-exec-role）の権限は bootstrap が決める。
    // 絞り方は docs/AWS_DEPLOYMENT_DONE.md の「cdk bootstrap」を参照。
    const bootstrapQualifier = cdk.DefaultStackSynthesizer.DEFAULT_QUALIFIER;
    githubActionsRole.addToPolicy(
      new iam.PolicyStatement({
        actions: ['sts:AssumeRole'],
        resources: ['deploy-role', 'file-publishing-role', 'lookup-role'].map(
          (role) =>
            this.formatArn({
              service: 'iam',
              region: '',
              resource: 'role',
              resourceName: `cdk-${bootstrapQualifier}-${role}-${this.account}-${this.region}`,
            }),
        ),
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
            resourceName: `${prefix}-migration:*`,
          }),
        ],
        conditions: {
          ArnEquals: { 'ecs:cluster': cluster.clusterArn },
        },
      }),
    );
    // Migration タスクの定義を登録・実行するために渡せる Role。
    // Web タスクの Role は CloudFormation（cfn-exec-role）が渡すので、ここには含めない。
    githubActionsRole.addToPolicy(
      new iam.PolicyStatement({
        actions: ['iam:PassRole'],
        resources: [migrationExecutionRole.roleArn, migrationTaskRole.roleArn],
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
      description: 'Route 53からALBへ向ける公開URL',
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
    new cdk.CfnOutput(this, 'MigrationTaskDefinitionFamily', {
      value: migrationTaskDefinition.family,
      description: 'GitHub Actionsがイメージを差し替えて登録するMigration Taskのfamily',
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
    new cdk.CfnOutput(this, 'GitHubActionsRoleArn', {
      value: githubActionsRole.roleArn,
    });
    new cdk.CfnOutput(this, 'OperationsTopicArn', {
      value: alertTopic.topicArn,
    });
    if (slackNotifier && slackWebhookSecret) {
      new cdk.CfnOutput(this, 'SlackNotifierFunctionName', {
        value: slackNotifier.functionName,
        description: 'CloudWatch アラームを Slack へ転送する Lambda 関数名',
      });
      new cdk.CfnOutput(this, 'SlackWebhookSecretArn', {
        value: slackWebhookSecret.secretArn,
        description:
          'デプロイ後に put-secret-value で Webhook URL を入れる Secret',
      });
    }
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
