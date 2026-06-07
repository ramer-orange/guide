import * as cdk from 'aws-cdk-lib';
import { Match, Template } from 'aws-cdk-lib/assertions';
import { GuideStack } from '../lib/guide-stack';

function makeStack(allowedSshCidr = '1.2.3.4/32'): Template {
  const app = new cdk.App({ context: { allowedSshCidr } });
  const stack = new GuideStack(app, 'TestStack', {
    env: { account: '123456789012', region: 'ap-northeast-1' },
  });
  return Template.fromStack(stack);
}

test('S3バケットが2つ作成され、パブリックアクセスがブロックされている', () => {
  const template = makeStack();
  template.resourceCountIs('AWS::S3::Bucket', 2);
  template.allResourcesProperties('AWS::S3::Bucket', {
    PublicAccessBlockConfiguration: {
      BlockPublicAcls: true,
      BlockPublicPolicy: true,
      IgnorePublicAcls: true,
      RestrictPublicBuckets: true,
    },
  });
});

test('バックアップバケットに7日ライフサイクルルールがある', () => {
  const template = makeStack();
  template.hasResourceProperties('AWS::S3::Bucket', {
    BucketName: 'guide-production-backups',
    LifecycleConfiguration: {
      Rules: Match.arrayWith([
        Match.objectLike({ ExpirationInDays: 7, Status: 'Enabled' }),
      ]),
    },
  });
});

test('EC2インスタンスがt4g.smallである', () => {
  const template = makeStack();
  template.hasResourceProperties('AWS::EC2::Instance', {
    InstanceType: 't4g.small',
  });
});

test('Security GroupがSSH/HTTP/HTTPSを許可している', () => {
  const template = makeStack();
  template.hasResourceProperties('AWS::EC2::SecurityGroup', {
    SecurityGroupIngress: Match.arrayWith([
      Match.objectLike({ CidrIp: '1.2.3.4/32', FromPort: 22, ToPort: 22 }),
      Match.objectLike({ CidrIp: '0.0.0.0/0', FromPort: 80, ToPort: 80 }),
      Match.objectLike({ CidrIp: '0.0.0.0/0', FromPort: 443, ToPort: 443 }),
    ]),
  });
});

test('Elastic IPが作成されている', () => {
  const template = makeStack();
  template.resourceCountIs('AWS::EC2::EIP', 1);
});
