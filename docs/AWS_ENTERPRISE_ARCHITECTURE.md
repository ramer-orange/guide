# AWS Enterprise Architecture

## 目的

コスト最適化よりも、可用性、セキュリティ、変更容易性、監査性、障害復旧を優先する。
単一AZ、単一アカウント、手動復旧、SSH運用、長期アクセスキーを許容しない。

## サービスレベル目標

- 可用性目標: 月間 `99.95%` 以上。
- Primary Region内のAZ障害は自動復旧する。
- Region障害はWarm Standbyへ切り替える。
- 目標RPO: 1分未満。目標RTO: 30分以内。
- 四半期ごとにDR切替訓練と復旧テストを実施する。

## ワークロード構成

- 動的トラフィックはRoute 53とGlobal Acceleratorから、各RegionのALBへルーティングする。
- ALB配下ではECS Fargateサービスを2AZへ分散する。
- 構成図ではPrimary RegionのAZ A/Bごとに、Web Task、Worker Task、Auroraインスタンス、Redisノードを分けて示す。
- ECS Service、Aurora Cluster Endpoint、Redis Cluster Endpointなどのリージョナルな論理エンドポイントと、各AZの実体を分離して示す。
- Web、Queue Worker、定期実行を別コンポーネントとしてスケールさせる。
- PostgreSQLはAurora PostgreSQL Multi-AZとRDS Proxyを使用する。
- DR RegionにはAurora Global Database Secondary Clusterと縮小構成のFargateを常時稼働させる。
- DR Regionも単一AZにせず、Warm Standbyを2AZへ配置する。
- セッションとキャッシュはElastiCache Redis Multi-AZへ保存し、アプリをステートレス化する。
- 非同期処理はSQSとDLQ、定期処理はEventBridge Scheduler、複雑な処理はStep Functionsを使用する。
- 添付ファイルはS3へ保存し、Versioning、暗号化、Cross-Region Replicationを有効化する。
- メール送信はSESを使用する。

## マルチアカウント構成

- AWS OrganizationsとControl TowerでLanding Zoneを管理する。
- Management、Audit、Log Archive、Network、Shared Services、Production、Non-Production、DRアカウントを分離する。
- SCP、Control Tower Guardrails、AWS Config Conformance Packsで逸脱を防止する。
- CloudTrail、Config、VPC Flow Logs、ALB Logs、アプリログをLog Archiveアカウントへ集約する。
- 監査ログとバックアップにはObject LockまたはVault Lockを適用し、通常の添付ファイルには適用しない。
- AWS BackupのCross-Account BackupとVault Lockでバックアップを本番アカウントから分離する。

## セキュリティ

- インターネット境界ではShield AdvancedとAWS WAF Managed Rulesを使用する。
- GuardDuty、Inspector、Macie、Security Hubを全アカウント・全Regionで有効化する。
- シークレットはSecrets Managerで管理し、自動ローテーションする。
- KMSキーは用途別に分離し、ログとバックアップは変更・削除を制限する。
- CI/CDはGitHub OIDCの短期認証を使用し、長期AWSアクセスキーを使用しない。
- 本番へのデプロイは承認、Blue/Green、ヘルスチェック、段階的切替、自動ロールバックを必須とする。

## 運用

- CloudWatch Application Signals、Logs、Metrics、Alarms、X-RayでSLOを監視する。
- 重大アラートはIncident Management、Pager、ChatOpsへ通知する。
- Runbook、障害対応手順、DR手順をSystems Manager Automationで自動化する。
- Well-Architected Review、脅威モデリング、負荷試験、GameDayを定期実施する。
- 依存パッケージ、コンテナイメージ、IaC、アプリコードをCIで検査する。

## 図

- `aws_architecture_enterprise_workload.png`: アプリケーション、データ、Multi-AZ、Multi-Region DR。
- `aws_architecture_enterprise_platform.png`: マルチアカウント、ネットワーク、監査、セキュリティ、CI/CD。
