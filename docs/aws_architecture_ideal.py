"""
Guide アプリ 理想的な AWS アーキテクチャ図（フルプロダクショングレード）

含むサービス:
- CloudFront + WAF（CDN・DDoS対策）
- Route 53（DNS）
- ACM（SSL証明書）
- ALB（ロードバランサー）
- Auto Scaling EC2（アプリ層、プライベートサブネット）
- RDS PostgreSQL Multi-AZ（DB層、プライベートサブネット）
- ElastiCache Redis（セッション・キャッシュ）
- S3（ファイル・バックアップ）
- Secrets Manager（シークレット管理）
- CloudWatch（監視・アラート）
- GitHub Actions + SSM（CI/CD）
"""
from diagrams import Diagram, Cluster, Edge
from diagrams.aws.compute import EC2, EC2AutoScaling
from diagrams.aws.network import (
    Route53, ALB, InternetGateway, NATGateway, CloudFront
)
from diagrams.aws.database import RDSPostgresqlInstance, ElastiCache
from diagrams.aws.storage import S3
from diagrams.aws.security import ACM, IAMRole, SecretsManager, WAF
from diagrams.aws.management import (
    SystemsManagerParameterStore, Cloudwatch
)
from diagrams.aws.general import User as AwsUser
from diagrams.onprem.vcs import Github

with Diagram(
    "Guide App - Full Production AWS Architecture",
    filename="/Users/kai/guide/docs/aws_architecture_ideal",
    outformat="png",
    show=False,
    direction="TB",
    graph_attr={
        "fontsize": "12",
        "bgcolor": "white",
        "pad": "1.0",
        "splines": "ortho",
        "nodesep": "0.6",
        "ranksep": "1.0",
    },
):
    user = AwsUser("ユーザー")
    github = Github("GitHub Actions\n(CI/CD)")

    with Cluster("AWS Cloud (ap-northeast-1)"):

        # エッジ層
        with Cluster("Edge"):
            waf = WAF("WAF\n(SQLi/XSS防御\nレートリミット)")
            cf = CloudFront("CloudFront\n(CDN・DDoS軽減)")
            route53 = Route53("Route 53\nexample.com")
            acm = ACM("ACM\nSSL証明書")

        # 監視
        cloudwatch = Cloudwatch("CloudWatch\n(メトリクス・アラート\nログ集約)")

        with Cluster("VPC (10.0.0.0/16)"):

            igw = InternetGateway("Internet Gateway")

            # パブリックサブネット
            with Cluster("Public Subnets  AZ-a / AZ-c"):
                alb = ALB("ALB\n(HTTPS:443)")
                nat_a = NATGateway("NAT GW AZ-a")
                nat_c = NATGateway("NAT GW AZ-c")

            # プライベートサブネット - App層
            with Cluster("Private Subnets (App)  AZ-a / AZ-c"):
                asg = EC2AutoScaling("Auto Scaling Group\n(CPU > 70% でスケールアウト)")
                ec2_a = EC2("EC2\nnginx + Laravel\nAZ-a")
                ec2_c = EC2("EC2\nnginx + Laravel\nAZ-c")

            # プライベートサブネット - DB層
            with Cluster("Private Subnets (DB)  AZ-a / AZ-c"):
                rds_primary = RDSPostgresqlInstance("RDS PostgreSQL\nPrimary AZ-a\n自動バックアップ")
                rds_standby = RDSPostgresqlInstance("RDS PostgreSQL\nStandby AZ-c\nMulti-AZ")
                redis = ElastiCache("ElastiCache Redis\n(セッション・キャッシュ)")

        # S3
        with Cluster("S3 Buckets"):
            files_bucket = S3("guide-production-files\n添付ファイル（非公開）")
            backups_bucket = S3("guide-production-backups\n7日で自動削除")

        # セキュリティ・設定
        secrets = SecretsManager("Secrets Manager\nDBパスワード\nAPP_KEY等")
        ssm_param = SystemsManagerParameterStore("SSM Parameter Store\nアプリ設定")
        iam_role = IAMRole("EC2 IAM Role\nS3 / SSM / Secrets\nCloudWatch")

    # ユーザー → Route53 → CloudFront → WAF → ALB
    user >> Edge(label="HTTPS") >> route53
    route53 >> cf
    acm >> Edge(style="dashed", label="SSL") >> cf
    cf >> waf
    waf >> alb
    igw >> alb

    # ALB → EC2（Auto Scaling）
    alb >> ec2_a
    alb >> ec2_c
    asg >> Edge(style="dashed") >> ec2_a
    asg >> Edge(style="dashed") >> ec2_c

    # NAT Gateway（プライベートからの外向き通信）
    ec2_a >> nat_a
    ec2_c >> nat_c

    # EC2 → RDS
    ec2_a >> Edge(label=":5432") >> rds_primary
    ec2_c >> Edge(label=":5432") >> rds_primary
    rds_primary >> Edge(label="レプリケーション", style="dashed") >> rds_standby

    # EC2 → Redis
    ec2_a >> Edge(label="セッション/キャッシュ") >> redis
    ec2_c >> redis

    # EC2 → S3
    ec2_a >> Edge(label="ファイル保存") >> files_bucket
    rds_primary >> Edge(label="自動バックアップ", style="dashed") >> backups_bucket

    # 監視
    ec2_a >> Edge(style="dotted", label="メトリクス") >> cloudwatch
    ec2_c >> Edge(style="dotted") >> cloudwatch
    rds_primary >> Edge(style="dotted") >> cloudwatch
    alb >> Edge(style="dotted") >> cloudwatch

    # シークレット・設定
    secrets >> Edge(style="dotted") >> ec2_a
    ssm_param >> Edge(style="dotted") >> ec2_a
    iam_role >> Edge(style="dashed") >> ec2_a
    iam_role >> Edge(style="dashed") >> ec2_c

    # GitHub Actions → SSM → EC2（SSH不要）
    github >> Edge(label="SSM Session Manager\n(ポート22不要)", style="dashed") >> ssm_param
