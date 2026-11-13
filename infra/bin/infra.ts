#!/usr/bin/env node
import 'source-map-support/register';
import * as cdk from 'aws-cdk-lib';
import { environments } from '../lib/environments';
import { GovernanceStack } from '../lib/governance-stack';
import { GuideDataStack } from '../lib/guide-data-stack';
import { GuideStack } from '../lib/guide-stack';

const app = new cdk.App();
const env = {
  account: process.env.CDK_DEFAULT_ACCOUNT,
  region: 'ap-northeast-1',
};

// アカウント共通の監査（GuardDuty・AWS Config・CloudTrail）と GitHub Actions の OIDC Provider。
// アプリとは別に一度だけデプロイする。
new GovernanceStack(app, 'GuideGovernanceStack', { env });

// 環境ごとに、消えると戻せないもの（Data）と作り直せるもの（App）を別スタックにする。
// production のスタック名には環境名を付けない（従来の GuideStack をそのまま使う）。
const production = new GuideDataStack(app, 'GuideDataStack', {
  env,
  config: environments.production,
  terminationProtection: true,
});
new GuideStack(app, 'GuideStack', {
  env,
  config: environments.production,
  data: production,
});

// staging は production と同じ構成を小さくしたもの。本番へ出す前に、
// マイグレーションと CDK の変更をここで先に流す。使わない期間は destroy してよい。
const staging = new GuideDataStack(app, 'GuideStagingDataStack', {
  env,
  config: environments.staging,
});
new GuideStack(app, 'GuideStagingStack', {
  env,
  config: environments.staging,
  data: staging,
});
