import * as cdk from 'aws-cdk-lib';
import * as autoscaling from 'aws-cdk-lib/aws-autoscaling';
import * as cloudfront from 'aws-cdk-lib/aws-cloudfront';
import * as origins from 'aws-cdk-lib/aws-cloudfront-origins';
import * as ec2 from 'aws-cdk-lib/aws-ec2';
import * as elasticache from 'aws-cdk-lib/aws-elasticache';
import * as elbv2 from 'aws-cdk-lib/aws-elasticloadbalancingv2';
import * as iam from 'aws-cdk-lib/aws-iam';
import * as rds from 'aws-cdk-lib/aws-rds';
import * as s3 from 'aws-cdk-lib/aws-s3';
import * as secretsmanager from 'aws-cdk-lib/aws-secretsmanager';
import { Construct } from 'constructs';

export class GuideStack extends cdk.Stack {
  constructor(scope: Construct, id: string, props?: cdk.StackProps) {
    super(scope, id, props);

    const githubOidcProvider = new iam.OpenIdConnectProvider(
      this,
      'GitHubOidcProvider',
      {
        url: 'https://token.actions.githubusercontent.com',
        clientIds: ['sts.amazonaws.com'],
      },
    );

    const githubActionsRole = new iam.Role(this, 'GitHubActionsRole', {
      roleName: 'guide-github-actions',
      description: 'GitHub Actions OIDC role for Guide deployments',
      assumedBy: new iam.FederatedPrincipal(
        githubOidcProvider.openIdConnectProviderArn,
        {
          StringEquals: {
            'token.actions.githubusercontent.com:aud': 'sts.amazonaws.com',
          },
          StringLike: {
            'token.actions.githubusercontent.com:sub':
              'repo:ramer-orange/guide:ref:refs/heads/main',
          },
        },
        'sts:AssumeRoleWithWebIdentity',
      ),
      managedPolicies: [
        iam.ManagedPolicy.fromAwsManagedPolicyName('AdministratorAccess'),
      ],
    });

    const vpc = new ec2.Vpc(this, 'Vpc', {
      vpcName: 'guide-vpc',
      ipAddresses: ec2.IpAddresses.cidr('10.0.0.0/16'),
      maxAzs: 2,
      natGateways: 2,
      subnetConfiguration: [
        {
          name: 'public',
          subnetType: ec2.SubnetType.PUBLIC,
          cidrMask: 24,
        },
        {
          name: 'app',
          subnetType: ec2.SubnetType.PRIVATE_WITH_EGRESS,
          cidrMask: 24,
        },
        {
          name: 'data',
          subnetType: ec2.SubnetType.PRIVATE_ISOLATED,
          cidrMask: 24,
        },
      ],
    });

    const filesBucket = s3.Bucket.fromBucketName(
      this,
      'FilesBucket',
      'guide-production-files',
    );

    const auditBucket = new s3.Bucket(this, 'AuditBucket', {
      bucketName: 'guide-production-audit-logs',
      blockPublicAccess: s3.BlockPublicAccess.BLOCK_ALL,
      encryption: s3.BucketEncryption.S3_MANAGED,
      enforceSSL: true,
      lifecycleRules: [{ expiration: cdk.Duration.days(90) }],
      removalPolicy: cdk.RemovalPolicy.RETAIN,
    });

    const appKeySecret = new secretsmanager.Secret(this, 'AppKeySecret', {
      secretName: 'guide/production/app-key',
      generateSecretString: {
        excludePunctuation: true,
        passwordLength: 32,
      },
    });

    const appSecurityGroup = new ec2.SecurityGroup(this, 'AppSecurityGroup', {
      vpc,
      description: 'Guide app EC2 instances',
      allowAllOutbound: true,
    });

    const albSecurityGroup = new ec2.SecurityGroup(this, 'AlbSecurityGroup', {
      vpc,
      description: 'Guide ALB - CloudFront origin only',
      allowAllOutbound: true,
    });

    const cloudFrontPrefixListId = this.node.tryGetContext(
      'cloudFrontOriginPrefixListId',
    );
    if (cloudFrontPrefixListId) {
      albSecurityGroup.addIngressRule(
        ec2.Peer.prefixList(cloudFrontPrefixListId),
        ec2.Port.tcp(80),
        'HTTP from CloudFront origin-facing prefix list',
      );
    } else {
      const cloudFrontOriginFacing = ec2.PrefixList.fromLookup(
        this,
        'CloudFrontOriginFacingPrefixList',
        {
          prefixListName: 'com.amazonaws.global.cloudfront.origin-facing',
        },
      );
      albSecurityGroup.addIngressRule(
        cloudFrontOriginFacing,
        ec2.Port.tcp(80),
        'HTTP from CloudFront origin-facing prefix list',
      );
    }

    appSecurityGroup.addIngressRule(
      albSecurityGroup,
      ec2.Port.tcp(80),
      'HTTP from ALB',
    );
    vpc.selectSubnets({ subnetType: ec2.SubnetType.PUBLIC }).subnets.forEach(
      (subnet, index) => {
        appSecurityGroup.addIngressRule(
          ec2.Peer.ipv4(subnet.ipv4CidrBlock),
          ec2.Port.tcp(80),
          `HTTP from public subnet ${index + 1}`,
        );
      },
    );

    const dbSecurityGroup = new ec2.SecurityGroup(this, 'DatabaseSecurityGroup', {
      vpc,
      description: 'Guide PostgreSQL',
      allowAllOutbound: true,
    });
    dbSecurityGroup.addIngressRule(
      appSecurityGroup,
      ec2.Port.tcp(5432),
      'PostgreSQL from app instances',
    );

    const redisSecurityGroup = new ec2.SecurityGroup(this, 'RedisSecurityGroup', {
      vpc,
      description: 'Guide Redis',
      allowAllOutbound: true,
    });
    redisSecurityGroup.addIngressRule(
      appSecurityGroup,
      ec2.Port.tcp(6379),
      'Redis from app instances',
    );

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
      allocatedStorage: 20,
      maxAllocatedStorage: 100,
      multiAz: true,
      securityGroups: [dbSecurityGroup],
      backupRetention: cdk.Duration.days(7),
      deletionProtection: true,
      storageEncrypted: true,
      removalPolicy: cdk.RemovalPolicy.RETAIN,
    });

    const redisSubnetGroup = new elasticache.CfnSubnetGroup(
      this,
      'RedisSubnetGroup',
      {
        description: 'Guide Redis isolated subnets',
        subnetIds: vpc
          .selectSubnets({ subnetType: ec2.SubnetType.PRIVATE_ISOLATED })
          .subnetIds,
      },
    );

    const redis = new elasticache.CfnReplicationGroup(this, 'Redis', {
      replicationGroupDescription: 'Guide Redis cache',
      engine: 'redis',
      cacheNodeType: 'cache.t4g.micro',
      numCacheClusters: 2,
      automaticFailoverEnabled: true,
      multiAzEnabled: true,
      atRestEncryptionEnabled: true,
      cacheSubnetGroupName: redisSubnetGroup.ref,
      securityGroupIds: [redisSecurityGroup.securityGroupId],
    });

    const role = new iam.Role(this, 'AppInstanceRole', {
      assumedBy: new iam.ServicePrincipal('ec2.amazonaws.com'),
      description: 'Guide app EC2 role',
      managedPolicies: [
        iam.ManagedPolicy.fromAwsManagedPolicyName(
          'AmazonSSMManagedInstanceCore',
        ),
      ],
    });
    filesBucket.grantReadWrite(role);
    auditBucket.grantWrite(role);
    appKeySecret.grantRead(role);
    database.secret?.grantRead(role);

    const userData = ec2.UserData.forLinux();
    userData.addCommands(
      'set -eux',
      'apt-get update -y',
      'apt-get install -y ca-certificates curl gnupg unzip git jq',
      'install -m 0755 -d /etc/apt/keyrings',
      'curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg',
      'chmod a+r /etc/apt/keyrings/docker.gpg',
      'echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo $VERSION_CODENAME) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null',
      'apt-get update -y',
      'apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin',
      'systemctl enable docker',
      'systemctl start docker',
      'usermod -aG docker ubuntu',
      'curl -s "https://awscli.amazonaws.com/awscli-exe-linux-aarch64.zip" -o "/tmp/awscliv2.zip"',
      'unzip -q /tmp/awscliv2.zip -d /tmp',
      '/tmp/aws/install',
      'rm -rf /tmp/awscliv2.zip /tmp/aws',
      'mkdir -p /opt/guide',
      'chown ubuntu:ubuntu /opt/guide',
    );

    const launchTemplate = new ec2.LaunchTemplate(this, 'AppLaunchTemplate', {
      instanceType: ec2.InstanceType.of(
        ec2.InstanceClass.T4G,
        ec2.InstanceSize.SMALL,
      ),
      machineImage: ec2.MachineImage.fromSsmParameter(
        '/aws/service/canonical/ubuntu/server/24.04/stable/current/arm64/hvm/ebs-gp3/ami-id',
      ),
      role,
      securityGroup: appSecurityGroup,
      userData,
      blockDevices: [
        {
          deviceName: '/dev/sda1',
          volume: ec2.BlockDeviceVolume.ebs(20, {
            encrypted: true,
            volumeType: ec2.EbsDeviceVolumeType.GP3,
            deleteOnTermination: true,
          }),
        },
      ],
    });

    const asg = new autoscaling.AutoScalingGroup(this, 'AppAutoScalingGroup', {
      vpc,
      vpcSubnets: { subnetType: ec2.SubnetType.PRIVATE_WITH_EGRESS },
      minCapacity: 2,
      maxCapacity: 4,
      launchTemplate,
    });
    cdk.Tags.of(asg).add('GuideRole', 'app', { applyToLaunchedInstances: true });

    const alb = new elbv2.ApplicationLoadBalancer(this, 'Alb', {
      vpc,
      internetFacing: true,
      securityGroup: albSecurityGroup,
      vpcSubnets: { subnetType: ec2.SubnetType.PUBLIC },
    });

    alb.logAccessLogs(auditBucket, 'alb');

    const listener = alb.addListener('HttpListener', {
      port: 80,
      open: false,
    });
    listener.addTargets('AppTargets', {
      port: 80,
      targets: [asg],
      healthCheck: {
        path: '/up',
        healthyHttpCodes: '200',
      },
    });

    const distribution = new cloudfront.Distribution(this, 'Distribution', {
      comment: 'Guide app domainless distribution',
      defaultBehavior: {
        origin: new origins.LoadBalancerV2Origin(alb, {
          protocolPolicy: cloudfront.OriginProtocolPolicy.HTTP_ONLY,
        }),
        allowedMethods: cloudfront.AllowedMethods.ALLOW_ALL,
        cachePolicy: cloudfront.CachePolicy.CACHING_DISABLED,
        originRequestPolicy:
          cloudfront.OriginRequestPolicy.ALL_VIEWER_EXCEPT_HOST_HEADER,
        viewerProtocolPolicy: cloudfront.ViewerProtocolPolicy.REDIRECT_TO_HTTPS,
      },
      minimumProtocolVersion: cloudfront.SecurityPolicyProtocol.TLS_V1_2_2021,
      priceClass: cloudfront.PriceClass.PRICE_CLASS_200,
    });

    new cdk.CfnOutput(this, 'CloudFrontUrl', {
      value: `https://${distribution.distributionDomainName}`,
      description: 'ドメインなし本番URL',
    });
    new cdk.CfnOutput(this, 'CloudFrontDomainName', {
      value: distribution.distributionDomainName,
      description: 'CloudFront標準ドメイン',
    });
    new cdk.CfnOutput(this, 'AlbDnsName', {
      value: alb.loadBalancerDnsName,
      description: 'ALB DNS名。通常はCloudFront経由でアクセスする',
    });
    new cdk.CfnOutput(this, 'AutoScalingGroupName', {
      value: asg.autoScalingGroupName,
      description: 'アプリEC2 Auto Scaling Group名',
    });
    new cdk.CfnOutput(this, 'FilesBucketName', {
      value: filesBucket.bucketName,
      description: '添付ファイル用S3バケット',
    });
    new cdk.CfnOutput(this, 'DatabaseEndpoint', {
      value: database.dbInstanceEndpointAddress,
      description: 'PostgreSQL endpoint',
    });
    new cdk.CfnOutput(this, 'DatabaseSecretArn', {
      value: database.secret?.secretArn ?? '',
      description: 'PostgreSQL認証情報のSecrets Manager ARN',
    });
    new cdk.CfnOutput(this, 'RedisEndpoint', {
      value: redis.attrPrimaryEndPointAddress,
      description: 'Redis primary endpoint',
    });
    new cdk.CfnOutput(this, 'AppKeySecretArn', {
      value: appKeySecret.secretArn,
      description: 'Laravel APP_KEYのSecrets Manager ARN',
    });
    new cdk.CfnOutput(this, 'GitHubActionsRoleArn', {
      value: githubActionsRole.roleArn,
      description: 'GitHub Actions OIDCでAssumeするIAM Role ARN',
    });
  }
}
