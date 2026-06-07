"""
Guide アプリ AWS アーキテクチャ図
CDK スタック (infra/lib/guide-stack.ts) と
docker-compose.prod.yml をもとに生成
"""
from diagrams import Diagram, Cluster, Edge
from diagrams.aws.compute import EC2
from diagrams.aws.network import InternetGateway
from diagrams.aws.storage import S3
from diagrams.aws.security import IAMRole
from diagrams.aws.management import SystemsManagerParameterStore
from diagrams.aws.general import InternetAlt1, User as AwsUser
from diagrams.onprem.vcs import Github
from diagrams.onprem.network import Nginx
from diagrams.onprem.database import PostgreSQL
from diagrams.programming.framework import Laravel

with Diagram(
    "Guide App - AWS Architecture",
    filename="/Users/kai/guide/docs/aws_architecture",
    outformat="png",
    show=False,
    direction="TB",
    graph_attr={
        "fontsize": "14",
        "bgcolor": "white",
        "pad": "0.5",
        "splines": "ortho",
    },
):
    user = AwsUser("ユーザー")
    github = Github("GitHub Actions\n(CI/CD)")

    with Cluster("AWS Cloud (ap-northeast-1)"):
        with Cluster("Default VPC"):
            eip = InternetGateway("Elastic IP\n(固定パブリックIP)")

            with Cluster("EC2 t4g.small\nUbuntu 24.04 ARM / EBS gp3 20GB"):
                with Cluster("Docker Compose"):
                    nginx = Nginx("nginx:alpine\n:80")
                    app = Laravel("app\n(Laravel/PHP 8.3)")
                    postgres = PostgreSQL("postgres:17\n(永続ボリューム)")

        with Cluster("S3 Buckets"):
            files_bucket = S3("guide-production-files\n添付ファイル")
            backups_bucket = S3("guide-production-backups\n自動削除: 7日")

        iam_role = IAMRole("EC2 IAM Role\nS3 Read/Write\nSSM Core")
        ssm_param = SystemsManagerParameterStore("SSM Parameter Store\nSSH秘密鍵")

    # ユーザー → Elastic IP → nginx
    user >> Edge(label="HTTP:80") >> eip >> nginx

    # GitHub Actions → SSH デプロイ
    github >> Edge(label="SSH:22\n(デプロイ)", style="dashed") >> eip

    # Docker コンテナ間
    nginx >> Edge(label="proxy") >> app
    app >> Edge(label="SQL") >> postgres

    # EC2 → S3
    app >> Edge(label="ファイル保存") >> files_bucket
    app >> Edge(label="バックアップ") >> backups_bucket

    # IAM Role（破線）
    iam_role >> Edge(style="dashed") >> files_bucket
    iam_role >> Edge(style="dashed") >> backups_bucket

    # SSM Parameter Store
    ssm_param >> Edge(label="SSH秘密鍵", style="dotted") >> eip
