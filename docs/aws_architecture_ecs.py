"""
Guide アプリ ECSを使った場合の理想的な AWS アーキテクチャ図。

aws_architecture_ideal.py のEC2 Auto Scaling構成をベースに、
コンピュート層をECS(Fargate)へ置き換えた版。
独自ドメインを使い、Route 53からALBへ直接ルーティングする。
アプリの静的ファイルはコンテナイメージに含め、ユーザー添付ファイルは
非公開S3へ保存する。
小規模運用のコストを優先してNAT Gatewayは置かず、Fargateタスクに
Public IPを割り当てる。タスクの受信はALBのSecurity Groupからだけ許可する。
主要なリクエスト経路を左から右へ配置し、監視・監査・設定管理などの
補助サービスは下段へ分離して、1枚で読みやすい構成にしている。

Redisを置いていないのは、セッション・キャッシュ・キューを
いずれもdatabaseドライバで動かしているため(production.env参照)。
そのぶんの読み書きはRDSに乗るので、スケール時はRDSのサイジングで吸収する。
キュー用ワーカーのServiceも、現時点でShouldQueueなジョブが無いため置いていない。
"""
from pathlib import Path

from diagrams import Cluster, Diagram, Edge
from diagrams.aws.compute import (
    ECS,
    EC2ContainerRegistry,
    ElasticContainerServiceService,
    ElasticContainerServiceTask,
    Fargate,
)
from diagrams.aws.database import RDSPostgresqlInstance
from diagrams.aws.general import User as AwsUser
from diagrams.aws.integration import SNS
from diagrams.aws.management import (
    AutoScaling,
    Cloudtrail,
    Cloudwatch,
    Config,
    SystemsManagerParameterStore,
)
from diagrams.aws.network import (
    ALB,
    Endpoint,
    InternetGateway,
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

# 破線のうち、クラスタのランク付けに使う2本だけこちらを使う。
# 全ての破線を constraint=false にすると ECS Control Plane と Operations が
# どのランクにも属さなくなり、Graphvizが "trouble in init_rank" で落ちる。
linked_edge = {
    "style": "dashed",
    "color": "#7c8a9a",
}

with Diagram(
    "Guide App - AWS Architecture (ECS Fargate, ALB Direct)",
    filename=str(Path(__file__).with_suffix("")),
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

    with Cluster("DNS", graph_attr=cluster_attr):
        route53 = Route53("Route 53\n独自ドメイン")

    with Cluster("VPC  ap-northeast-1  /  Multi-AZ", graph_attr=cluster_attr):
        internet_gateway = InternetGateway("Internet Gateway")

        alb = ALB("ALB\n2 AZのPublic Subnet\nHTTPS:443")

        with Cluster("Availability Zone A", graph_attr=cluster_attr):
            task_a = Fargate("Fargate Task A\nPublic Subnet・Public IP")
            db_a = RDSPostgresqlInstance(
                "PostgreSQL Writer\nPrivate DB Subnet\n同期レプリケーション"
            )

        with Cluster("Availability Zone B", graph_attr=cluster_attr):
            task_b = Fargate("Fargate Task B\nPublic Subnet・Public IP")
            db_b = RDSPostgresqlInstance(
                "PostgreSQL Standby B\nPrivate DB Subnet\n自動Failover"
            )

        tasks = [task_a, task_b]

        with Cluster("VPC Endpoints", graph_attr=cluster_attr):
            s3_endpoint = Endpoint("S3 Gateway\nEndpoint")
            interface_endpoints = Endpoint(
                "Interface Endpoints\nECR api/dkr・Secrets・Logs"
            )

    with Cluster("ECS Control Plane  ap-northeast-1", graph_attr=cluster_attr):
        ecs_cluster = ECS("ECS Cluster\nFargate起動タイプ")
        ecs_service = ElasticContainerServiceService(
            "ECS Service\n望ましいタスク数を維持"
        )
        task_def = ElasticContainerServiceTask(
            "Task Definition\nLaravel + public資産"
        )
        migration_task = ElasticContainerServiceTask(
            "Migration Task\nphp artisan migrate\nrun-taskで単発実行"
        )
        service_autoscaling = AutoScaling(
            "Application Auto Scaling\nCPU/メモリ基準"
        )

    with Cluster("Platform Services  ap-northeast-1", graph_attr=cluster_attr):
        ecr = EC2ContainerRegistry("ECR\nDockerイメージ")
        files_bucket = S3("Files Bucket\n添付ファイル・非公開")
        audit_bucket = S3("Audit Logs Bucket\nCloudTrail・ALBログ")
        secrets = SecretsManager("Secrets Manager\nDB認証・APP_KEY")
        parameters = SystemsManagerParameterStore("Parameter Store\nアプリ設定")
        task_role = IAMRole("ECS Task Role\nアプリ用最小権限")
        execution_role = IAMRole("ECS Execution Role\nイメージPull・Secrets取得")
        github_role = IAMRole("GitHub OIDC Role\n一時認証・最小権限")
        regional_acm = ACM("ACM ap-northeast-1\nALB証明書")
        alb_waf = WAF("Regional WAF\nALB保護・Managed Rules")

        regional_acm >> Edge(style="invis") >> alb_waf
        alb_waf >> Edge(style="invis") >> ecr
        ecr >> Edge(style="invis") >> task_role
        task_role >> Edge(style="invis") >> execution_role
        execution_role >> Edge(style="invis") >> github_role
        github_role >> Edge(style="invis") >> secrets
        secrets >> Edge(style="invis") >> parameters
        parameters >> Edge(style="invis") >> files_bucket
        files_bucket >> Edge(style="invis") >> audit_bucket

    with Cluster("Operations  ap-northeast-1", graph_attr=cluster_attr):
        cloudwatch = Cloudwatch("CloudWatch\nContainer Insights・ログ")
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
    # DNS resolves the custom domain to the ALB; HTTPS traffic enters via the IGW.
    user >> Edge(label="DNS問い合わせ") >> route53
    route53 >> Edge(label="Alias") >> alb
    user >> Edge(label="HTTPS") >> internet_gateway >> alb
    alb >> Edge(label="HTTP Target Group\n受信元SGはALBのみ") >> tasks
    tasks >> Edge(label="PostgreSQL :5432\nセッション・キャッシュ・キューも同居") >> db_a
    tasks >> Edge(label="添付ファイル保存・取得") >> s3_endpoint >> files_bucket
    tasks >> Edge(label="期限付きURLを返す", **support_edge) >> user
    user >> Edge(label="期限付きURLで閲覧") >> files_bucket
    db_a >> Edge(label="同期") >> db_b

    # ECSがタスクを管理し、ALBへ自動登録する。
    ecs_service >> Edge(label="desired count維持\nALB自動登録") >> tasks
    ecs_cluster >> Edge(label="Service/Taskを収容", **linked_edge) >> ecs_service
    task_def >> Edge(xlabel="参照", **support_edge) >> ecs_service
    service_autoscaling >> Edge(**support_edge) >> ecs_service
    task_def >> Edge(xlabel="同一イメージ", **support_edge) >> migration_task
    migration_task >> Edge(xlabel="スキーマ更新", **support_edge) >> db_a

    # Supporting relationships. Dashed lines are associations, permissions, or operations.
    regional_acm >> Edge(**support_edge) >> alb
    alb_waf >> Edge(xlabel="リクエスト検査", **support_edge) >> alb
    tasks >> Edge(
        label="外向きHTTPS\nGoogle OAuth",
        **support_edge,
    ) >> internet_gateway
    tasks >> Edge(**support_edge) >> interface_endpoints
    interface_endpoints >> Edge(**support_edge) >> secrets
    interface_endpoints >> Edge(**support_edge) >> parameters
    interface_endpoints >> Edge(xlabel="ECR API", **support_edge) >> ecr
    s3_endpoint >> Edge(xlabel="イメージレイヤー", **support_edge) >> ecr

    task_role >> Edge(**support_edge) >> tasks
    execution_role >> Edge(**support_edge) >> tasks
    execution_role >> Edge(xlabel="イメージPull", **support_edge) >> ecr

    github >> Edge(**support_edge) >> github_role
    github_role >> Edge(xlabel="build/push", **support_edge) >> ecr
    github_role >> Edge(xlabel="run-task: migrate", **support_edge) >> migration_task
    github_role >> Edge(xlabel="update-service", **support_edge) >> ecs_service

    tasks >> Edge(label="awslogs", **linked_edge) >> cloudwatch
    cloudwatch >> Edge(**support_edge) >> sns
    guardduty >> Edge(**support_edge) >> sns
    config >> Edge(**support_edge) >> sns
    cloudtrail >> Edge(**support_edge) >> audit_bucket
    alb >> Edge(**support_edge) >> audit_bucket
