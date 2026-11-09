#!/usr/bin/env node
import 'source-map-support/register';
import * as cdk from 'aws-cdk-lib';
import { GovernanceStack } from '../lib/governance-stack';
import { GuideStack } from '../lib/guide-stack';

const app = new cdk.App();
const env = {
  account: process.env.CDK_DEFAULT_ACCOUNT,
  region: 'ap-northeast-1',
};

// アカウント共通の監査（GuardDuty・AWS Config）。アプリとは別に一度だけデプロイする。
new GovernanceStack(app, 'GuideGovernanceStack', { env });

// アプリ本体（ALB・ECS・RDS など）。
new GuideStack(app, 'GuideStack', { env });
