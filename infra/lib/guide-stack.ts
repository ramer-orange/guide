import * as cdk from 'aws-cdk-lib';
import { Construct } from 'constructs';

export class GuideStack extends cdk.Stack {
  constructor(scope: Construct, id: string, props?: cdk.StackProps) {
    super(scope, id, props);

    new cdk.CfnOutput(this, 'InfrastructureStatus', {
      value: 'paused',
      description:
        'AWS infrastructure is intentionally paused to avoid running costs.',
    });
  }
}
