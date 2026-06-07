import * as cdk from 'aws-cdk-lib';
import * as ec2 from 'aws-cdk-lib/aws-ec2';
import * as iam from 'aws-cdk-lib/aws-iam';
import * as s3 from 'aws-cdk-lib/aws-s3';
import { Construct } from 'constructs';

export class GuideStack extends cdk.Stack {
  constructor(scope: Construct, id: string, props?: cdk.StackProps) {
    super(scope, id, props);

    const allowedSshCidrs: string[] =
      this.node.tryGetContext('allowedSshCidrs') ?? [];
    if (allowedSshCidrs.length === 0) {
      throw new Error(
        'allowedSshCidrs is required. Set it in cdk.json context as an array: ["x.x.x.x/32"]',
      );
    }

    // デフォルトVPC
    const vpc = ec2.Vpc.fromLookup(this, 'Vpc', { isDefault: true });

    // S3: 添付ファイル用
    const filesBucket = new s3.Bucket(this, 'FilesBucket', {
      bucketName: 'guide-production-files',
      blockPublicAccess: s3.BlockPublicAccess.BLOCK_ALL,
      removalPolicy: cdk.RemovalPolicy.RETAIN,
      enforceSSL: true,
    });

    // S3: バックアップ用（7日で自動削除）
    const backupsBucket = new s3.Bucket(this, 'BackupsBucket', {
      bucketName: 'guide-production-backups',
      blockPublicAccess: s3.BlockPublicAccess.BLOCK_ALL,
      removalPolicy: cdk.RemovalPolicy.RETAIN,
      enforceSSL: true,
      lifecycleRules: [
        {
          enabled: true,
          expiration: cdk.Duration.days(7),
        },
      ],
    });

    // IAM Role（EC2からS3・SSMへのアクセス権限）
    const role = new iam.Role(this, 'Ec2Role', {
      assumedBy: new iam.ServicePrincipal('ec2.amazonaws.com'),
      description: 'Guide app EC2 role',
      managedPolicies: [
        iam.ManagedPolicy.fromAwsManagedPolicyName('AmazonSSMManagedInstanceCore'),
      ],
    });
    filesBucket.grantReadWrite(role);
    backupsBucket.grantReadWrite(role);

    // Security Group
    const sg = new ec2.SecurityGroup(this, 'SecurityGroup', {
      vpc,
      description: 'Guide app: SSH/HTTP/HTTPS',
      allowAllOutbound: true,
    });
    allowedSshCidrs.forEach((cidr) => {
      sg.addIngressRule(
        ec2.Peer.ipv4(cidr),
        ec2.Port.tcp(22),
        `SSH from ${cidr}`,
      );
    });
    sg.addIngressRule(ec2.Peer.anyIpv4(), ec2.Port.tcp(80), 'HTTP');
    sg.addIngressRule(ec2.Peer.anyIpv4(), ec2.Port.tcp(443), 'HTTPS');

    // EC2 Key Pair（秘密鍵はSSM Parameter Storeに保存）
    const keyPair = new ec2.KeyPair(this, 'KeyPair', {
      keyPairName: 'guide-ec2-key',
    });

    // User Data（Docker, Docker Compose v2, Git, AWS CLI v2 を自動インストール）
    const userData = ec2.UserData.forLinux();
    userData.addCommands(
      'apt-get update -y',
      'apt-get install -y ca-certificates curl gnupg unzip git',
      // Docker
      'install -m 0755 -d /etc/apt/keyrings',
      'curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg',
      'chmod a+r /etc/apt/keyrings/docker.gpg',
      'echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo $VERSION_CODENAME) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null',
      'apt-get update -y',
      'apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin',
      'systemctl enable docker',
      'systemctl start docker',
      'usermod -aG docker ubuntu',
      // AWS CLI v2（ARM）
      'curl -s "https://awscli.amazonaws.com/awscli-exe-linux-aarch64.zip" -o "/tmp/awscliv2.zip"',
      'unzip -q /tmp/awscliv2.zip -d /tmp',
      '/tmp/aws/install',
      'rm -rf /tmp/awscliv2.zip /tmp/aws',
    );

    // EC2インスタンス（t4g.small, Ubuntu 24.04 LTS ARM, EBS gp3 20GB）
    const instance = new ec2.Instance(this, 'Instance', {
      vpc,
      instanceType: ec2.InstanceType.of(
        ec2.InstanceClass.T4G,
        ec2.InstanceSize.SMALL,
      ),
      machineImage: ec2.MachineImage.fromSsmParameter(
        '/aws/service/canonical/ubuntu/server/24.04/stable/current/arm64/hvm/ebs-gp3/ami-id',
      ),
      securityGroup: sg,
      keyPair,
      role,
      userData,
      blockDevices: [
        {
          deviceName: '/dev/sda1',
          volume: ec2.BlockDeviceVolume.ebs(20, {
            volumeType: ec2.EbsDeviceVolumeType.GP3,
            deleteOnTermination: true,
          }),
        },
      ],
    });

    // Elastic IP
    const eip = new ec2.CfnEIP(this, 'ElasticIp', {
      domain: 'vpc',
      instanceId: instance.instanceId,
    });

    // Outputs
    new cdk.CfnOutput(this, 'ElasticIpAddress', {
      value: eip.ref,
      description: 'DNSのAレコードにこのIPを設定する',
    });
    new cdk.CfnOutput(this, 'SshPrivateKeyParameter', {
      value: keyPair.privateKey.parameterName,
      description: 'SSH秘密鍵のSSMパラメータ名',
    });
    new cdk.CfnOutput(this, 'InstanceId', {
      value: instance.instanceId,
      description: 'EC2インスタンスID',
    });
  }
}
