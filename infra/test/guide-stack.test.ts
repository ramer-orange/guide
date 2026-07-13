import * as cdk from 'aws-cdk-lib';
import { Match, Template } from 'aws-cdk-lib/assertions';
import { GuideStack } from '../lib/guide-stack';

function makeStack(): Template {
  const app = new cdk.App({
    context: {
      cloudFrontOriginPrefixListId: 'pl-1234567890abcdef0',
    },
  });
  const stack = new GuideStack(app, 'TestStack', {
    env: { account: '123456789012', region: 'ap-northeast-1' },
  });
  return Template.fromStack(stack);
}

test('VPCが2AZ構成でPublic/App/Dataサブネットを持つ', () => {
  const template = makeStack();
  template.resourceCountIs('AWS::EC2::Subnet', 6);
  template.resourceCountIs('AWS::EC2::NatGateway', 2);
});

test('CloudFront Distributionが独自ドメインなしで作成される', () => {
  const template = makeStack();
  template.resourceCountIs('AWS::CloudFront::Distribution', 1);
  template.hasResourceProperties('AWS::CloudFront::Distribution', {
    DistributionConfig: Match.objectLike({
      Aliases: Match.absent(),
      DefaultCacheBehavior: Match.objectLike({
        ViewerProtocolPolicy: 'redirect-to-https',
      }),
    }),
  });
});

test('ALBはCloudFront managed prefix listからのHTTPのみを許可する', () => {
  const template = makeStack();
  template.hasResourceProperties('AWS::EC2::SecurityGroupIngress', {
    SourcePrefixListId: 'pl-1234567890abcdef0',
    FromPort: 80,
    ToPort: 80,
  });
});

test('アプリはPrivate SubnetのAuto Scaling Groupで2台起動する', () => {
  const template = makeStack();
  template.hasResourceProperties('AWS::AutoScaling::AutoScalingGroup', {
    MinSize: '2',
    MaxSize: '4',
  });
});

test('RDS PostgreSQLがMulti-AZとDeletionProtectionで作成される', () => {
  const template = makeStack();
  template.hasResourceProperties('AWS::RDS::DBInstance', {
    Engine: 'postgres',
    MultiAZ: true,
    DeletionProtection: true,
    StorageEncrypted: true,
  });
});

test('Redisは2ノード構成で自動フェイルオーバーを有効にする', () => {
  const template = makeStack();
  template.hasResourceProperties('AWS::ElastiCache::ReplicationGroup', {
    AutomaticFailoverEnabled: true,
    MultiAZEnabled: true,
    NumCacheClusters: 2,
  });
});

test('監査ログ用S3バケットは公開アクセスをブロックする', () => {
  const template = makeStack();
  template.resourceCountIs('AWS::S3::Bucket', 1);
  template.allResourcesProperties('AWS::S3::Bucket', {
    PublicAccessBlockConfiguration: {
      BlockPublicAcls: true,
      BlockPublicPolicy: true,
      IgnorePublicAcls: true,
      RestrictPublicBuckets: true,
    },
  });
});

test('GitHub Actions OIDC Roleがmainブランチだけを信頼する', () => {
  const template = makeStack();
  template.hasResourceProperties('Custom::AWSCDKOpenIdConnectProvider', {
    Url: 'https://token.actions.githubusercontent.com',
    ClientIDList: ['sts.amazonaws.com'],
  });
  template.hasResourceProperties('AWS::IAM::Role', {
    RoleName: 'guide-github-actions',
    AssumeRolePolicyDocument: {
      Statement: Match.arrayWith([
        Match.objectLike({
          Action: 'sts:AssumeRoleWithWebIdentity',
          Condition: {
            StringEquals: {
              'token.actions.githubusercontent.com:aud': 'sts.amazonaws.com',
            },
            StringLike: {
              'token.actions.githubusercontent.com:sub':
                'repo:ramer-orange/guide:ref:refs/heads/main',
            },
          },
        }),
      ]),
    },
    ManagedPolicyArns: Match.arrayWith([
      {
        'Fn::Join': [
          '',
          [
            'arn:',
            { Ref: 'AWS::Partition' },
            ':iam::aws:policy/AdministratorAccess',
          ],
        ],
      },
    ]),
  });
});
