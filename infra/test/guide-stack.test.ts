import * as cdk from 'aws-cdk-lib';
import { Template } from 'aws-cdk-lib/assertions';
import { GuideStack } from '../lib/guide-stack';

function makeStack(): Template {
  const app = new cdk.App();
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
