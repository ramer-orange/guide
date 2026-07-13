"""
Guide アプリ 理想的な AWS アーキテクチャ図。

主要なリクエスト経路を左から右へ配置し、監視・監査・設定管理などの
補助サービスは下段へ分離して、1枚で読みやすい構成にしている。
"""
from diagrams import Cluster, Diagram, Edge
from diagrams.aws.compute import EC2AutoScaling
from diagrams.aws.database import ElastiCache, RDSPostgresqlInstance
from diagrams.aws.general import User as AwsUser
from diagrams.aws.integration import SNS
from diagrams.aws.management import (
    Cloudtrail,
    Cloudwatch,
    Config,
    SystemsManagerParameterStore,
)
from diagrams.aws.network import (
    ALB,
    CloudFront,
    Endpoint,
    InternetGateway,
    NATGateway,
    Route53,
)
from diagrams.aws.security import ACM, Guardduty, IAMRole, SecretsManager, WAF
from diagrams.aws.storage import S3
from diagrams.onprem.vcs import Github


cluster_attr = {
    "margin": "32",
    "fontsize": "15",
    "fontname": "Helvetica",
}

support_edge = {
    "style": "dashed",
    "color": "#7c8a9a",
    "constraint": "false",
}

with Diagram(
    "Guide App - Ideal AWS Architecture",
    filename="/Users/kai/guide/docs/aws_architecture_ideal",
    outformat="png",
    show=False,
    direction="LR",
    curvestyle="ortho",
    graph_attr={
        "fontsize": "20",
        "fontname": "Helvetica",
        "bgcolor": "white",
        "pad": "1.4",
        "margin": "0.6",
        "nodesep": "0.9",
        "ranksep": "1.3",
        "dpi": "180",
    },
    node_attr={
        "fontsize": "12",
        "fontname": "Helvetica",
        "margin": "0.20,0.14",
    },
    edge_attr={
        "fontsize": "11",
        "fontname": "Helvetica",
        "penwidth": "1.5",
        "color": "#526274",
    },
):
    user = AwsUser("ユーザー")

    with Cluster("Global Edge", graph_attr=cluster_attr):
        route53 = Route53("Route 53\nDNS")
        cloudfront = CloudFront("CloudFront\nCDN・DDoS軽減")
        waf = WAF("WAF Web ACL\nSQLi/XSS・レート制限")
        edge_acm = ACM("ACM us-east-1\nCloudFront証明書")

    with Cluster("VPC  ap-northeast-1  /  Multi-AZ", graph_attr=cluster_attr):
        internet_gateway = InternetGateway("Internet Gateway")

        alb = ALB("ALB\n2 AZのPublic Subnet\nHTTPS:443")

        with Cluster("Availability Zone A", graph_attr=cluster_attr):
            nat_a = NATGateway("NAT Gateway A")
            app_a = EC2AutoScaling("Laravel EC2 A\nASG・自動復旧")
            db_a = RDSPostgresqlInstance("PostgreSQL Writer\n同期レプリケーション")
            redis_a = ElastiCache("Redis Primary")

        with Cluster("Availability Zone B", graph_attr=cluster_attr):
            nat_b = NATGateway("NAT Gateway B")
            app_b = EC2AutoScaling("Laravel EC2 B\nASG・自動復旧")
            db_b = RDSPostgresqlInstance("PostgreSQL Standby B\n自動Failover")
            redis_b = ElastiCache("Redis Replica B")

        apps = [app_a, app_b]
        nat_gateways = [nat_a, nat_b]

        with Cluster("VPC Endpoints", graph_attr=cluster_attr):
            s3_endpoint = Endpoint("S3 Gateway\nEndpoint")
            interface_endpoints = Endpoint(
                "Interface Endpoints\nSSM・Secrets・Logs"
            )

    with Cluster("Platform Services  ap-northeast-1", graph_attr=cluster_attr):
        files_bucket = S3("Files Bucket\n添付ファイル・非公開")
        audit_bucket = S3("Audit Logs Bucket\nCloudTrail・ALBログ")
        secrets = SecretsManager("Secrets Manager\nDB認証・APP_KEY")
        parameters = SystemsManagerParameterStore("Parameter Store\nアプリ設定")
        ec2_role = IAMRole("EC2 IAM Role\n最小権限")
        github_role = IAMRole("GitHub OIDC Role\n一時認証・最小権限")
        regional_acm = ACM("ACM ap-northeast-1\nALB証明書")

        regional_acm >> Edge(style="invis") >> ec2_role
        ec2_role >> Edge(style="invis") >> github_role
        github_role >> Edge(style="invis") >> secrets
        secrets >> Edge(style="invis") >> parameters
        parameters >> Edge(style="invis") >> files_bucket
        files_bucket >> Edge(style="invis") >> audit_bucket

    with Cluster("Operations  ap-northeast-1", graph_attr=cluster_attr):
        cloudwatch = Cloudwatch("CloudWatch\nログ・メトリクス")
        sns = SNS("SNS\n運用通知")
        cloudtrail = Cloudtrail("CloudTrail\n監査ログ")
        guardduty = Guardduty("GuardDuty\n脅威検知")
        config = Config("AWS Config\n構成・準拠確認")

        cloudwatch >> Edge(style="invis") >> sns
        sns >> Edge(style="invis") >> cloudtrail
        cloudtrail >> Edge(style="invis") >> guardduty
        guardduty >> Edge(style="invis") >> config

    github = Github("GitHub Actions\nCI/CD")

    # Main request and data path.
    user >> Edge(label="HTTPS") >> route53 >> cloudfront
    cloudfront >> Edge(label="HTTPS") >> internet_gateway >> alb
    alb >> Edge(label="HTTP") >> apps
    apps >> Edge(label="PostgreSQL :5432") >> db_a
    apps >> Edge(label="Redis :6379") >> redis_a
    apps >> Edge(label="S3 API") >> s3_endpoint >> files_bucket
    db_a >> Edge(label="同期") >> db_b
    redis_a >> Edge(label="非同期複製") >> redis_b

    # Supporting relationships. Dashed lines are associations, permissions, or operations.
    waf >> Edge(**support_edge) >> cloudfront
    edge_acm >> Edge(**support_edge) >> cloudfront
    regional_acm >> Edge(**support_edge) >> alb
    for app, nat_gateway in zip(apps, nat_gateways):
        app >> Edge(**support_edge) >> nat_gateway
        nat_gateway >> Edge(**support_edge) >> internet_gateway
    apps >> Edge(**support_edge) >> interface_endpoints
    interface_endpoints >> Edge(**support_edge) >> secrets
    interface_endpoints >> Edge(**support_edge) >> parameters
    ec2_role >> Edge(**support_edge) >> apps

    github >> Edge(**support_edge) >> github_role
    github_role >> Edge(**support_edge) >> apps

    apps >> Edge(**support_edge) >> cloudwatch
    cloudwatch >> Edge(**support_edge) >> sns
    guardduty >> Edge(**support_edge) >> sns
    config >> Edge(**support_edge) >> sns
    cloudtrail >> Edge(**support_edge) >> audit_bucket
    alb >> Edge(**support_edge) >> audit_bucket
