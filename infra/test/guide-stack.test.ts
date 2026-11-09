import * as cdk from 'aws-cdk-lib';
import { Template } from 'aws-cdk-lib/assertions';
import { GuideStack } from '../lib/guide-stack';

function makeStack(context: Record<string, unknown> = {}): Template {
  const app = new cdk.App({ context });
  const stack = new GuideStack(app, 'TestStack', {
    env: { account: '123456789012', region: 'ap-northeast-1' },
  });
  return Template.fromStack(stack);
}

test('AWSインフラは料金停止のためpaused状態にする', () => {
  const template = makeStack();

  template.resourceCountIs('AWS::EC2::VPC', 0);
  template.resourceCountIs('AWS::EC2::NatGateway', 0);
  template.resourceCountIs('AWS::ElasticLoadBalancingV2::LoadBalancer', 0);
  template.resourceCountIs('AWS::CloudFront::Distribution', 0);
  template.resourceCountIs('AWS::AutoScaling::AutoScalingGroup', 0);
  template.resourceCountIs('AWS::RDS::DBInstance', 0);
  template.resourceCountIs('AWS::ECR::Repository', 0);
});

test('pauseInfrastructure=falseでECS Fargate構成を作成する', () => {
  const template = makeStack({ pauseInfrastructure: 'false' });

  template.resourceCountIs('AWS::EC2::VPC', 1);
  template.resourceCountIs('AWS::EC2::NatGateway', 0);
  template.resourceCountIs('AWS::ElasticLoadBalancingV2::LoadBalancer', 1);
  template.resourceCountIs('AWS::ECS::Service', 1);
  template.resourceCountIs('AWS::RDS::DBInstance', 1);
  template.resourceCountIs('AWS::ECR::Repository', 1);
});
