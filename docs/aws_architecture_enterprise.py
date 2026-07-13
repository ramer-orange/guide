"""
Guide enterprise AWS reference architecture.

This file generates two reviewable diagrams instead of one unreadable mega-diagram:
- aws_architecture_enterprise_workload.png: runtime, data, and regional DR
- aws_architecture_enterprise_platform.png: multi-account governance and operations
"""
from diagrams import Cluster, Diagram, Edge
from diagrams.aws.analytics import AmazonOpensearchService
from diagrams.aws.compute import ECR, Fargate, Lambda
from diagrams.aws.database import Aurora, ElastiCache, RDS
from diagrams.aws.devtools import Codeartifact, XRay
from diagrams.aws.engagement import SES
from diagrams.aws.general import User as AwsUser
from diagrams.aws.integration import EventbridgeScheduler, SNS, SQS, StepFunctions
from diagrams.aws.management import (
    Cloudtrail,
    Cloudwatch,
    Config,
    ControlTower,
    Organizations,
    OrganizationsAccount,
    SystemsManagerAppConfig,
)
from diagrams.aws.network import (
    ALB,
    CloudFront,
    DirectConnect,
    GlobalAccelerator,
    NetworkFirewall,
    Route53,
    TransitGateway,
)
from diagrams.aws.security import (
    Guardduty,
    Inspector,
    KMS,
    Macie,
    SecretsManager,
    SecurityHub,
    ShieldAdvanced,
    WAF,
)
from diagrams.aws.storage import Backup, S3
from diagrams.onprem.vcs import Github


GRAPH = {
    "fontsize": "20",
    "fontname": "Helvetica",
    "bgcolor": "white",
    "pad": "1.4",
    "margin": "0.6",
    "nodesep": "0.9",
    "ranksep": "1.3",
    "dpi": "180",
}
NODE = {
    "fontsize": "12",
    "fontname": "Helvetica",
    "margin": "0.20,0.14",
}
EDGE = {
    "fontsize": "11",
    "fontname": "Helvetica",
    "penwidth": "1.5",
    "color": "#526274",
}
CLUSTER = {
    "margin": "32",
    "fontsize": "15",
    "fontname": "Helvetica",
}
SUPPORT = {
    "style": "dashed",
    "color": "#7c8a9a",
    "constraint": "false",
}


def workload_diagram() -> None:
    with Diagram(
        "Guide Enterprise Workload - Multi-AZ and Multi-Region",
        filename="/Users/kai/guide/docs/aws_architecture_enterprise_workload",
        outformat="png",
        show=False,
        direction="LR",
        curvestyle="ortho",
        graph_attr=GRAPH,
        node_attr=NODE,
        edge_attr=EDGE,
    ):
        users = AwsUser("ユーザー")

        with Cluster("Global Services", graph_attr=CLUSTER):
            dns = Route53("Route 53\nDNS・ヘルスチェック")
            accelerator = GlobalAccelerator("Global Accelerator\nリージョン自動切替")
            cloudfront = CloudFront("CloudFront\n静的配信・画像配信")
            edge_waf = WAF("CloudFront WAF\nManaged Rules")
            shield = ShieldAdvanced("Shield Advanced\nDDoS対策")

        with Cluster("Primary Region  ap-northeast-1", graph_attr=CLUSTER):
            with Cluster("Ingress  Multi-AZ", graph_attr=CLUSTER):
                primary_waf = WAF("Regional WAF\nManaged Rules")
                primary_alb = ALB("ALB\n2 AZのPublic Subnet\nTLS終端")

            with Cluster("Availability Zone A", graph_attr=CLUSTER):
                web_a = Fargate("Laravel Web Tasks A")
                worker_a = Fargate("Queue Worker Tasks A")
                aurora_a = Aurora("Aurora Writer A")
                redis_a = ElastiCache("Redis Primary A")

            with Cluster("Availability Zone B", graph_attr=CLUSTER):
                web_b = Fargate("Laravel Web Tasks B")
                worker_b = Fargate("Queue Worker Tasks B")
                aurora_b = Aurora("Aurora Reader B")
                redis_b = ElastiCache("Redis Replica B")

            primary_web = [web_a, web_b]
            primary_workers = [worker_a, worker_b]
            primary_aurora = [aurora_a, aurora_b]
            primary_redis = [redis_a, redis_b]

            with Cluster("Regional Managed Services", graph_attr=CLUSTER):
                web_service = Fargate("ECS Web Service\nDesired Count・Auto Scaling")
                worker_service = Fargate(
                    "ECS Worker Service\nDesired Count・Auto Scaling"
                )
                proxy = RDS("RDS Proxy\n接続プール・Failover")
                aurora_endpoint = Aurora("Aurora Cluster Endpoint\nPITR")
                redis_endpoint = ElastiCache(
                    "Redis Cluster Endpoint\nAuto Failover"
                )
                queue = SQS("SQS + DLQ\n非同期処理")
                scheduler = EventbridgeScheduler("EventBridge Scheduler\n定期処理")

            with Cluster("Object and Integration Services", graph_attr=CLUSTER):
                files = S3("S3 Files\nVersioning・暗号化\nCross-Region Replication")
                events = StepFunctions("Step Functions\n業務ワークフロー")
                mail = SES("SES\nトランザクションメール")

            primary_waf >> Edge(style="invis") >> primary_alb
            primary_alb >> Edge(style="invis") >> web_service
            web_service >> Edge(style="invis") >> proxy
            proxy >> Edge(style="invis") >> aurora_endpoint
            worker_service >> Edge(style="invis") >> queue
            queue >> Edge(style="invis") >> events

        with Cluster("DR Region  ap-southeast-1  Warm Standby", graph_attr=CLUSTER):
            dr_waf = WAF("DR Regional WAF\nManaged Rules")
            dr_alb = ALB("DR ALB\n2 AZのPublic Subnet")
            dr_service = Fargate("DR ECS Web Service\n最小Desired Count")
            dr_aurora_endpoint = Aurora("Global DB Secondary\nCluster Endpoint")
            with Cluster("DR Availability Zone A", graph_attr=CLUSTER):
                dr_web_a = Fargate("DR Web Tasks A\n最小構成")
                dr_aurora_a = Aurora("Global DB Secondary A")
            with Cluster("DR Availability Zone B", graph_attr=CLUSTER):
                dr_web_b = Fargate("DR Web Tasks B\n最小構成")
                dr_aurora_b = Aurora("Global DB Secondary B")
            dr_web = [dr_web_a, dr_web_b]
            dr_aurora = [dr_aurora_a, dr_aurora_b]
            dr_files = S3("S3 Replica\nDR Files")

            dr_alb >> Edge(style="invis") >> dr_service
            dr_service >> Edge(style="invis") >> dr_aurora_endpoint
            dr_aurora_endpoint >> Edge(style="invis") >> dr_files

        with Cluster("Delivery and Runtime Configuration", graph_attr=CLUSTER):
            github = Github("GitHub Actions\nOIDC")
            ecr = ECR("ECR\n署名済みイメージ")
            secrets = SecretsManager("Secrets Manager\n自動ローテーション")
            appconfig = SystemsManagerAppConfig("AppConfig\nFeature Flags")

            github >> Edge(style="invis") >> ecr
            ecr >> Edge(style="invis") >> secrets
            secrets >> Edge(style="invis") >> appconfig

        with Cluster("Observability", graph_attr=CLUSTER):
            cloudwatch = Cloudwatch("CloudWatch\nLogs・Metrics・Alarms")
            xray = XRay("X-Ray\n分散トレーシング")
            opensearch = AmazonOpensearchService("OpenSearch\n集中検索・分析")
            alerts = SNS("SNS / Incident連携\nPager・ChatOps")

            cloudwatch >> Edge(style="invis") >> xray
            xray >> Edge(style="invis") >> opensearch
            opensearch >> Edge(style="invis") >> alerts

        # Dynamic request path and static delivery.
        users >> Edge(label="app.example.com") >> dns >> accelerator
        accelerator >> Edge(label="Primary") >> primary_alb >> web_service
        accelerator >> Edge(label="Failover") >> dr_alb >> dr_service
        users >> Edge(label="assets.example.com") >> cloudfront >> files

        # Primary application data path.
        web_service >> proxy >> aurora_endpoint
        web_service >> redis_endpoint
        web_service >> queue >> worker_service
        scheduler >> queue
        worker_service >> events
        worker_service >> mail
        web_service >> Edge(label="署名URL") >> files

        # Dashed edges describe the service capacity placed in each AZ.
        web_service >> Edge(label="タスク配置", **SUPPORT) >> primary_web
        worker_service >> Edge(label="タスク配置", **SUPPORT) >> primary_workers
        aurora_endpoint >> Edge(label="DBインスタンス", **SUPPORT) >> primary_aurora
        redis_endpoint >> Edge(label="ノード", **SUPPORT) >> primary_redis

        # Cross-region replication and DR.
        aurora_endpoint >> Edge(
            label="Global DB replication", **SUPPORT
        ) >> dr_aurora_endpoint
        dr_service >> Edge(label="タスク配置", **SUPPORT) >> dr_web
        dr_aurora_endpoint >> Edge(label="DBインスタンス", **SUPPORT) >> dr_aurora
        files >> Edge(label="CRR", **SUPPORT) >> dr_files
        # Security associations.
        shield >> Edge(**SUPPORT) >> accelerator
        shield >> Edge(**SUPPORT) >> cloudfront
        edge_waf >> Edge(**SUPPORT) >> cloudfront
        primary_waf >> Edge(**SUPPORT) >> primary_alb
        dr_waf >> Edge(**SUPPORT) >> dr_alb

        # Delivery and configuration.
        github >> Edge(label="OIDC / Blue-Green", **SUPPORT) >> ecr
        ecr >> Edge(**SUPPORT) >> web_service
        ecr >> Edge(**SUPPORT) >> dr_service
        secrets >> Edge(**SUPPORT) >> web_service
        appconfig >> Edge(**SUPPORT) >> web_service

        # Observability.
        web_service >> Edge(**SUPPORT) >> cloudwatch
        aurora_endpoint >> Edge(**SUPPORT) >> cloudwatch
        cloudwatch >> Edge(**SUPPORT) >> alerts
        web_service >> Edge(**SUPPORT) >> xray


def platform_diagram() -> None:
    with Diagram(
        "Guide Enterprise Platform - Multi-Account Governance",
        filename="/Users/kai/guide/docs/aws_architecture_enterprise_platform",
        outformat="png",
        show=False,
        direction="LR",
        curvestyle="ortho",
        graph_attr=GRAPH,
        node_attr=NODE,
        edge_attr=EDGE,
    ):
        engineers = AwsUser("開発・運用・監査担当")

        with Cluster("Organization Governance", graph_attr=CLUSTER):
            organizations = Organizations("AWS Organizations\nSCP・Tag Policy")
            control_tower = ControlTower("Control Tower\nLanding Zone・Guardrails")

        with Cluster("Security OU", graph_attr=CLUSTER):
            audit_account = OrganizationsAccount("Audit Account")
            log_archive = OrganizationsAccount("Log Archive Account")
            security_hub = SecurityHub("Security Hub\n集約・自動対応")
            guardduty = Guardduty("GuardDuty\n全アカウント")
            inspector = Inspector("Inspector\n脆弱性管理")
            macie = Macie("Macie\nS3機密データ検出")
            config = Config("AWS Config\nConformance Packs")
            org_trail = Cloudtrail("Organization CloudTrail")
            immutable_logs = S3("Central Log Archive\nObject Lock・KMS")

            audit_account >> Edge(style="invis") >> security_hub
            security_hub >> Edge(style="invis") >> immutable_logs

        with Cluster("Network OU", graph_attr=CLUSTER):
            network_account = OrganizationsAccount("Network Account")
            transit_gateway = TransitGateway("Transit Gateway\nHub and Spoke")
            firewall = NetworkFirewall("Inspection VPC\nAWS Network Firewall")
            direct_connect = DirectConnect("Direct Connect / VPN")

            direct_connect >> Edge(style="invis") >> transit_gateway
            transit_gateway >> Edge(style="invis") >> firewall

        with Cluster("Infrastructure OU", graph_attr=CLUSTER):
            shared_account = OrganizationsAccount("Shared Services Account")
            ecr = ECR("Central ECR\nImage Scan・署名")
            artifacts = Codeartifact("CodeArtifact\n依存パッケージ")
            kms = KMS("Central KMS Keys")
            backup = Backup("AWS Backup\nCross-Account Vault Lock")

            shared_account >> Edge(style="invis") >> ecr
            ecr >> Edge(style="invis") >> artifacts
            artifacts >> Edge(style="invis") >> backup

        with Cluster("Workloads OU", graph_attr=CLUSTER):
            prod_account = OrganizationsAccount("Production Account")
            nonprod_account = OrganizationsAccount("Non-Production Account")
            dr_account = OrganizationsAccount("DR Account")

            nonprod_account >> Edge(style="invis") >> prod_account
            prod_account >> Edge(style="invis") >> dr_account

        with Cluster("Engineering Delivery", graph_attr=CLUSTER):
            github = Github("GitHub Enterprise\nBranch Protection")
            pipeline = StepFunctions("Deployment Orchestration\nApprovals・Rollback")
            cloudwatch = Cloudwatch("Central Observability\nSLO・Dashboard・Alarms")
            notifications = SNS("Incident Management\nPager・ChatOps")

            github >> Edge(style="invis") >> pipeline
            pipeline >> Edge(style="invis") >> cloudwatch
            cloudwatch >> Edge(style="invis") >> notifications

        engineers >> control_tower
        organizations >> control_tower

        # Account vending and guardrails.
        control_tower >> Edge(**SUPPORT) >> prod_account
        control_tower >> Edge(**SUPPORT) >> nonprod_account
        control_tower >> Edge(**SUPPORT) >> dr_account

        # Centralized security and immutable logs.
        guardduty >> security_hub
        config >> security_hub
        org_trail >> immutable_logs
        security_hub >> Edge(**SUPPORT) >> audit_account
        immutable_logs >> Edge(**SUPPORT) >> log_archive

        # Centralized network.
        prod_account >> transit_gateway
        dr_account >> transit_gateway
        transit_gateway >> firewall
        direct_connect >> transit_gateway
        network_account >> Edge(**SUPPORT) >> transit_gateway

        # Shared platform services.
        shared_account >> Edge(**SUPPORT) >> ecr
        kms >> Edge(**SUPPORT) >> immutable_logs
        backup >> Edge(**SUPPORT) >> prod_account
        backup >> Edge(**SUPPORT) >> dr_account

        # Delivery and operations.
        engineers >> github >> pipeline
        pipeline >> Edge(label="OIDC・承認付きDeploy", **SUPPORT) >> prod_account
        pipeline >> Edge(**SUPPORT) >> dr_account
        prod_account >> Edge(**SUPPORT) >> cloudwatch
        dr_account >> Edge(**SUPPORT) >> cloudwatch
        security_hub >> Edge(**SUPPORT) >> notifications
        cloudwatch >> Edge(**SUPPORT) >> notifications


if __name__ == "__main__":
    workload_diagram()
    platform_diagram()
