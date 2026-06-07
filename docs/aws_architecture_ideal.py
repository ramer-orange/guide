"""
Guide アプリ 理想的な AWS アーキテクチャ図（本番グレード）
- Route 53, ACM, ALB, Auto Scaling, RDS Multi-AZ, S3, Secrets Manager
- GitHub Actions による CI/CD デプロイ
"""
from diagrams import Diagram, Cluster, Edge
from diagrams.aws.compute import EC2, EC2AutoScaling
from diagrams.aws.network import (
    Route53, ALB, InternetGateway, NATGateway,
    PublicSubnet, PrivateSubnet
)
from diagrams.aws.database import RDSPostgresqlInstance
from diagrams.aws.storage import S3
from diagrams.aws.security import ACM, IAMRole, SecretsManager
from diagrams.aws.management import SystemsManagerParameterStore
from diagrams.aws.general import User as AwsUser
from diagrams.onprem.vcs import Github

with Diagram(
    "Guide App - Ideal AWS Architecture",
    filename="/Users/kai/guide/docs/aws_architecture_ideal",
    outformat="png",
    show=False,
    direction="TB",
    graph_attr={
        "fontsize": "13",
        "bgcolor": "white",
        "pad": "0.8",
        "splines": "ortho",
        "nodesep": "0.6",
        "ranksep": "0.8",
    },
):
    user = AwsUser("ユーザー")
    github = Github("GitHub Actions\n(CI/CD)")

    with Cluster("AWS Cloud (ap-northeast-1)"):

        # DNS + SSL
        route53 = Route53("Route 53\nexample.com")
        acm = ACM("ACM\nSSL証明書")

        with Cluster("VPC (10.0.0.0/16)"):

            igw = InternetGateway("Internet Gateway")

            # パブリックサブネット (2AZ)
            with Cluster("Public Subnets\nAZ-a / AZ-c"):
                alb = ALB("ALB\n(HTTPS:443)")
                nat_a = NATGateway("NAT Gateway\nAZ-a")
                nat_c = NATGateway("NAT Gateway\nAZ-c")

            # プライベートサブネット - App層
            with Cluster("Private Subnets (App)\nAZ-a / AZ-c"):
                asg = EC2AutoScaling("Auto Scaling Group")
                ec2_a = EC2("EC2 (nginx + Laravel)\nAZ-a")
                ec2_c = EC2("EC2 (nginx + Laravel)\nAZ-c")

            # プライベートサブネット - DB層
            with Cluster("Private Subnets (DB)\nAZ-a / AZ-c"):
                rds_primary = RDSPostgresqlInstance("RDS PostgreSQL\n(Primary) AZ-a")
                rds_standby = RDSPostgresqlInstance("RDS PostgreSQL\n(Standby) AZ-c\nMulti-AZ")

        # S3
        with Cluster("S3 Buckets"):
            files_bucket = S3("guide-production-files\n添付ファイル")
            backups_bucket = S3("guide-production-backups\n自動削除: 7日")

        # セキュリティ・設定
        secrets = SecretsManager("Secrets Manager\nDB パスワード等")
        iam_role = IAMRole("EC2 IAM Role\nS3 / SSM / Secrets")
        ssm = SystemsManagerParameterStore("SSM Parameter Store\nアプリ設定")

    # ユーザー → Route53 → ALB
    user >> Edge(label="HTTPS") >> route53
    route53 >> alb
    acm >> Edge(style="dashed", label="SSL証明書") >> alb

    # Internet Gateway
    igw >> alb

    # ALB → EC2
    alb >> ec2_a
    alb >> ec2_c
    asg >> Edge(style="dashed") >> ec2_a
    asg >> Edge(style="dashed") >> ec2_c

    # NAT Gateway（プライベートサブネットからの外向き通信）
    ec2_a >> nat_a
    ec2_c >> nat_c

    # EC2 → RDS
    ec2_a >> Edge(label="PostgreSQL\n:5432") >> rds_primary
    ec2_c >> Edge(label="PostgreSQL\n:5432") >> rds_primary
    rds_primary >> Edge(label="レプリケーション", style="dashed") >> rds_standby

    # EC2 → S3
    ec2_a >> Edge(label="ファイル保存") >> files_bucket
    ec2_a >> Edge(label="バックアップ") >> backups_bucket

    # Secrets / SSM
    secrets >> Edge(style="dotted") >> ec2_a
    ssm >> Edge(style="dotted") >> ec2_a
    iam_role >> Edge(style="dashed") >> ec2_a
    iam_role >> Edge(style="dashed") >> ec2_c

    # GitHub Actions → デプロイ（SSM Session Manager 経由）
    github >> Edge(label="SSM Session Manager\n(SSH不要)", style="dashed") >> ssm
