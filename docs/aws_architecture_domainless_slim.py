"""
Guide app domainless slim AWS architecture.

This diagram keeps the runtime path and the explicitly requested
security/operations services, while removing custom-domain and extra
notification/configuration components from the ideal diagram.
"""
from diagrams import Cluster, Diagram, Edge
from diagrams.aws.compute import EC2AutoScaling, ECR
from diagrams.aws.database import RDSPostgresqlInstance
from diagrams.aws.general import User as AwsUser
from diagrams.aws.management import (
    Cloudformation,
    Cloudtrail,
    Cloudwatch,
    Config,
    SystemsManagerRunCommand,
)
from diagrams.aws.network import ALB, CloudFront, InternetGateway, NATGateway
from diagrams.aws.security import Guardduty, IAMRole, SecretsManager, WAF
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
    "Guide App - Domainless Slim AWS Architecture",
    filename="/Users/kai/guide/docs/aws_architecture_domainless_slim",
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

    with Cluster("CI/CD  /  GitHub OIDC", graph_attr=cluster_attr):
        github = Github("GitHub Actions\nmain push")
        github_role = IAMRole("GitHub OIDC Role\nmainブランチのみ")
        ecr = ECR("ECR\nDocker image")
        cloudformation = Cloudformation("CDK Deploy\nCloudFormation")
        ssm = SystemsManagerRunCommand("SSM Run Command\nASGへデプロイ")

        github >> Edge(label="OIDC AssumeRole") >> github_role
        github_role >> Edge(label="build/push image") >> ecr
        github_role >> Edge(label="infra変更時") >> cloudformation
        github_role >> Edge(label="send-command") >> ssm

    with Cluster("Global Edge  /  No Custom Domain", graph_attr=cluster_attr):
        cloudfront = CloudFront("CloudFront\n標準ドメイン\nHTTPS")
        waf = WAF("WAF Web ACL\nSQLi/XSS・レート制限")

    with Cluster("VPC  ap-northeast-1  /  2 AZ", graph_attr=cluster_attr):
        internet_gateway = InternetGateway("Internet Gateway")
        alb = ALB("ALB\nPublic Subnets\nHTTP origin")

        with Cluster("Availability Zone A", graph_attr=cluster_attr):
            nat_a = NATGateway("NAT Gateway A")
            app_a = EC2AutoScaling("Laravel EC2 A\nASG・自動復旧")
            db_a = RDSPostgresqlInstance("PostgreSQL Writer")

        with Cluster("Availability Zone B", graph_attr=cluster_attr):
            nat_b = NATGateway("NAT Gateway B")
            app_b = EC2AutoScaling("Laravel EC2 B\nASG・自動復旧")
            db_b = RDSPostgresqlInstance("PostgreSQL Standby\n自動Failover")

        apps = [app_a, app_b]
        nat_gateways = [nat_a, nat_b]

    with Cluster("Platform Services", graph_attr=cluster_attr):
        files_bucket = S3("Files Bucket\n添付ファイル・非公開")
        audit_bucket = S3("Audit Logs Bucket\nCloudTrail・ALBログ")
        secrets = SecretsManager("Secrets Manager\nDB認証・APP_KEY")
        ec2_role = IAMRole("EC2 IAM Role\nS3・Secrets最小権限")

        ec2_role >> Edge(style="invis") >> secrets
        secrets >> Edge(style="invis") >> files_bucket
        files_bucket >> Edge(style="invis") >> audit_bucket

    with Cluster("Operations and Security", graph_attr=cluster_attr):
        cloudwatch = Cloudwatch("CloudWatch\nLogs・Metrics")
        cloudtrail = Cloudtrail("CloudTrail\n監査ログ")
        guardduty = Guardduty("GuardDuty\n脅威検知")
        config = Config("AWS Config\n構成・準拠確認")

        cloudwatch >> Edge(style="invis") >> cloudtrail
        cloudtrail >> Edge(style="invis") >> guardduty
        guardduty >> Edge(style="invis") >> config

    # Main request and data path.
    user >> Edge(label="HTTPS") >> cloudfront
    cloudfront >> Edge(label="HTTP origin") >> internet_gateway >> alb
    alb >> Edge(label="HTTP :80") >> apps
    apps >> Edge(label="PostgreSQL :5432") >> db_a
    apps >> Edge(label="S3 API") >> files_bucket
    db_a >> Edge(label="同期") >> db_b
    ssm >> Edge(label="Docker Compose pull/up") >> apps
    ecr >> Edge(label="docker pull") >> apps

    # Supporting relationships.
    waf >> Edge(**support_edge) >> cloudfront
    for app, nat_gateway in zip(apps, nat_gateways):
        app >> Edge(**support_edge) >> nat_gateway
        nat_gateway >> Edge(**support_edge) >> internet_gateway

    ec2_role >> Edge(**support_edge) >> apps
    apps >> Edge(**support_edge) >> secrets
    apps >> Edge(**support_edge) >> cloudwatch
    alb >> Edge(**support_edge) >> audit_bucket
    cloudtrail >> Edge(**support_edge) >> audit_bucket
    guardduty >> Edge(**support_edge) >> cloudwatch
    config >> Edge(**support_edge) >> cloudwatch
