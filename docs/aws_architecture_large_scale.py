"""
Guide large-scale data reference architecture.

Generates:
- aws_architecture_large_scale_online.png
- aws_architecture_large_scale_data_platform.png
"""
from diagrams import Cluster, Diagram, Edge
from diagrams.aws.analytics import (
    AmazonOpensearchService,
    Athena,
    EMR,
    Glue,
    GlueDataCatalog,
    KinesisDataFirehose,
    LakeFormation,
    ManagedStreamingForKafka,
    Quicksight,
    Redshift,
)
from diagrams.aws.compute import Fargate, Lambda
from diagrams.aws.database import Aurora, DMS, Dynamodb, ElastiCache
from diagrams.aws.general import User as AwsUser
from diagrams.aws.integration import Eventbridge, SQS, StepFunctions
from diagrams.aws.management import Cloudwatch
from diagrams.aws.security import KMS, Macie, SecurityLake
from diagrams.aws.storage import S3, S3Glacier


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


def online_diagram() -> None:
    with Diagram(
        "Guide Large-Scale Online Architecture",
        filename="/Users/kai/guide/docs/aws_architecture_large_scale_online",
        outformat="png",
        show=False,
        direction="LR",
        curvestyle="ortho",
        graph_attr=GRAPH,
        node_attr=NODE,
        edge_attr=EDGE,
    ):
        users = AwsUser("大量ユーザー・外部API")

        with Cluster("VPC  ap-northeast-1", graph_attr=CLUSTER):
            with Cluster("Availability Zone A", graph_attr=CLUSTER):
                api_a = Fargate("ECS API Tasks A\n水平スケール")
                workers_a = Fargate("Worker Tasks A\nConsumer Group")
                msk_a = ManagedStreamingForKafka("MSK Brokers A\nRF=2")
                aurora_a = Aurora("Limitless Router /\nShard Group A")
                redis_a = ElastiCache("Redis Primary")

            with Cluster("Availability Zone B", graph_attr=CLUSTER):
                api_b = Fargate("ECS API Tasks B\n水平スケール")
                workers_b = Fargate("Worker Tasks B\nConsumer Group")
                msk_b = ManagedStreamingForKafka("MSK Brokers B\nRF=2")
                aurora_b = Aurora("Limitless Router /\nShard Group B")
                redis_b = ElastiCache("Redis Replica B")

        apis = [api_a, api_b]
        workers = [workers_a, workers_b]
        msk_brokers = [msk_a, msk_b]
        aurora_capacity = [aurora_a, aurora_b]
        redis_nodes = [redis_a, redis_b]

        with Cluster("Regional Service and Data Endpoints", graph_attr=CLUSTER):
            api_service = Fargate("ECS API Service\nAuto Scaling")
            worker_service = Fargate("ECS Worker Service\nConsumer Groups")
            limitless = Aurora(
                "Aurora Limitless Endpoint\n基幹トランザクション"
            )
            redis = ElastiCache("Redis Cluster Endpoint\nRate Limit・Cache")
            msk = ManagedStreamingForKafka(
                "Amazon MSK Cluster\nSchema管理・Tiered Storage"
            )
            dynamodb = Dynamodb(
                "DynamoDB\n高カーディナリティPK\n高頻度キーアクセス"
            )
            search = AmazonOpensearchService(
                "OpenSearch Multi-AZ\n全文検索・集約検索\n専用Index"
            )

        with Cluster("Regional Integration Services", graph_attr=CLUSTER):
            retry = SQS("SQS Retry + DLQ\n失敗隔離")
            eventbridge = Eventbridge("EventBridge\nドメインイベント連携")
            orchestrator = StepFunctions("Step Functions\n長時間ワークフロー")

        with Cluster("Change Data Capture", graph_attr=CLUSTER):
            dms = DMS("AWS DMS CDC\nOutbox / DB Changes")
            streams = Dynamodb("DynamoDB Streams")

        with Cluster("Data Retention", graph_attr=CLUSTER):
            hot_files = S3("S3 Hot Objects\nPartitioned Prefix")
            archive = S3Glacier("S3 Glacier\n長期保管")

        observability = Cloudwatch(
            "CloudWatch\nPartition Lag・Throttle\nConsumer Lag・SLO"
        )

        users >> api_service
        api_service >> limitless
        api_service >> dynamodb
        api_service >> redis
        api_service >> search
        api_service >> Edge(label="イベント発行") >> msk
        api_service >> Edge(label="大容量Object") >> hot_files

        msk >> worker_service
        worker_service >> orchestrator
        worker_service >> retry
        worker_service >> search
        worker_service >> dynamodb
        worker_service >> eventbridge

        limitless >> dms >> msk
        dynamodb >> streams >> msk
        hot_files >> Edge(label="Lifecycle") >> archive

        # Dashed edges describe the capacity placed across two AZs.
        api_service >> Edge(label="タスク配置", **SUPPORT) >> apis
        worker_service >> Edge(label="タスク配置", **SUPPORT) >> workers
        limitless >> Edge(label="Router / Shard", **SUPPORT) >> aurora_capacity
        redis >> Edge(label="ノード", **SUPPORT) >> redis_nodes
        msk >> Edge(label="Broker配置 RF=2", **SUPPORT) >> msk_brokers

        api_service >> Edge(**SUPPORT) >> observability
        worker_service >> Edge(**SUPPORT) >> observability
        limitless >> Edge(**SUPPORT) >> observability
        dynamodb >> Edge(**SUPPORT) >> observability
        msk >> Edge(**SUPPORT) >> observability
        search >> Edge(**SUPPORT) >> observability


def data_platform_diagram() -> None:
    with Diagram(
        "Guide Large-Scale Data Platform Architecture",
        filename="/Users/kai/guide/docs/aws_architecture_large_scale_data_platform",
        outformat="png",
        show=False,
        direction="LR",
        curvestyle="ortho",
        graph_attr=GRAPH,
        node_attr=NODE,
        edge_attr=EDGE,
    ):
        producers = Fargate("Operational Services\nDomain Events")

        with Cluster("Ingestion Layer", graph_attr=CLUSTER):
            with Cluster("Availability Zone A", graph_attr=CLUSTER):
                msk_a = ManagedStreamingForKafka("MSK Brokers A\nRF=2")
            with Cluster("Availability Zone B", graph_attr=CLUSTER):
                msk_b = ManagedStreamingForKafka("MSK Brokers B\nRF=2")
            msk_brokers = [msk_a, msk_b]
            firehose = KinesisDataFirehose("Firehose\nBuffer・圧縮")
            dms = DMS("DMS CDC\nOperational DB")
            batch = S3("Batch Landing\nPartner・File Upload")

        with Cluster("S3 Data Lake  Iceberg", graph_attr=CLUSTER):
            raw = S3("Raw Zone\nImmutable・Partitioned")
            curated = S3("Curated Zone\nValidated・Compacted")
            serving = S3("Serving Zone\nDomain Data Products")
            archive = S3Glacier("Archive\nRetention Policy")

        with Cluster("Processing and Quality", graph_attr=CLUSTER):
            glue = Glue("AWS Glue ETL\nSchema Evolution")
            emr = EMR("EMR Serverless / Spark\n大規模変換・Compaction")
            quality = StepFunctions("Data Quality Workflow\n検証・隔離・再処理")

        with Cluster("Catalog and Governance", graph_attr=CLUSTER):
            catalog = GlueDataCatalog("Glue Data Catalog\nTechnical Metadata")
            lake_formation = LakeFormation(
                "Lake Formation\nLF-Tags・行列セル権限"
            )
            kms = KMS("KMS\nDomain別Encryption Keys")
            macie = Macie("Macie\nPII検出")

        with Cluster("Consumers", graph_attr=CLUSTER):
            athena = Athena("Athena\nAd-hoc Query")
            quicksight = Quicksight("QuickSight\nDashboard")
            search = AmazonOpensearchService("OpenSearch\nOperational Analytics")
            ml = Lambda("ML / Feature Pipelines")

        with Cluster("Redshift Multi-AZ", graph_attr=CLUSTER):
            with Cluster("Availability Zone A", graph_attr=CLUSTER):
                redshift_a = Redshift("Redshift Compute A")
            with Cluster("Availability Zone B", graph_attr=CLUSTER):
                redshift_b = Redshift("Redshift Compute B")
            redshift_nodes = [redshift_a, redshift_b]

        with Cluster("Security and Operations", graph_attr=CLUSTER):
            security_lake = SecurityLake("Security Lake\n監査・検知データ")
            monitoring = Cloudwatch("CloudWatch\nFreshness・品質・コスト")

        producers >> msk_brokers >> firehose >> raw
        dms >> raw
        batch >> raw
        raw >> glue >> curated
        curated >> emr >> serving
        raw >> Edge(label="Lifecycle") >> archive
        quality >> Edge(**SUPPORT) >> glue
        quality >> Edge(**SUPPORT) >> emr

        raw >> Edge(**SUPPORT) >> catalog
        curated >> Edge(**SUPPORT) >> catalog
        serving >> Edge(**SUPPORT) >> catalog
        lake_formation >> Edge(**SUPPORT) >> catalog
        kms >> Edge(**SUPPORT) >> raw
        kms >> Edge(**SUPPORT) >> curated
        macie >> Edge(**SUPPORT) >> raw

        serving >> athena
        serving >> redshift_nodes
        redshift_nodes >> quicksight
        serving >> search
        serving >> ml
        lake_formation >> Edge(**SUPPORT) >> athena
        lake_formation >> Edge(**SUPPORT) >> redshift_nodes

        security_lake >> Edge(**SUPPORT) >> monitoring
        firehose >> Edge(**SUPPORT) >> monitoring
        glue >> Edge(**SUPPORT) >> monitoring
        emr >> Edge(**SUPPORT) >> monitoring
        redshift_nodes >> Edge(**SUPPORT) >> monitoring


if __name__ == "__main__":
    online_diagram()
    data_platform_diagram()
