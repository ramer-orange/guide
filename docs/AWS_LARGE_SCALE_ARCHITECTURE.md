# AWS Large-Scale Data Architecture

## 目的

データ量、書き込み量、検索量、分析量が大幅に増加しても、オンライン処理のレイテンシと可用性を維持する。
単一データベースに、トランザクション、検索、イベント、分析の全責務を持たせない。

## 重要な設計原則

- 各サービスは負荷試験とアクセスパターン分析の結果に基づいて採用し、最初から全サービスへ分割しない。
- OLTP、キーアクセス、全文検索、イベント処理、分析を用途別サービスへ分離する。
- オンラインAPIから分析クエリを実行しない。
- 大容量ファイルをDBへ保存しない。S3へ保存し、DBにはメタデータのみ保持する。
- 同期処理を最小化し、イベント処理と再実行可能な非同期処理へ移す。
- すべてのデータフローを冪等にし、少なくとも1回配信を前提にする。
- データ量だけでなく、テナント数、書き込み偏り、アクセスパターン、保持期間で分割方法を決定する。

## オンライン処理

- 基幹トランザクションはAurora PostgreSQL Limitless Databaseを使用する。
- 構成図ではAZ A/Bごとに、ECS API Task、Worker Task、MSK Broker、Aurora Router/Shard容量、Redisノードを分けて示す。
- ECS Service、MSK Cluster、Aurora Limitless Endpointなどの論理エンドポイントと、各AZの実体を分離して示す。
- 分散キーは、テナントIDまたは所有者IDなど、結合とアクセス境界に合わせて決定する。
- Aurora Limitlessで対応できない強いクロスシャードトランザクション要件は、サービス境界の見直し対象とする。
- 超高頻度のキーアクセス、状態管理、重複排除、カウンタはDynamoDBへ分離する。
- DynamoDBのPartition Keyは高カーディナリティと均等分散を必須とし、ホットパーティションを監視する。
- 全文検索、複雑な絞り込み、集約検索はOpenSearchへ投影する。
- キャッシュ、Rate Limit、一時状態はElastiCache Redisへ保存する。
- イベント基盤はAmazon MSKを2AZ、Replication Factor 2で構成する。
- Kafka Topicはドメイン単位で設計し、Partition Key、保持期間、再処理方針、Schema互換性を管理する。
- Glue Schema RegistryでAvroまたはJSON Schemaを管理し、後方互換性違反をCIで拒否する。
- Consumer Lag、Partition数、Broker CPU、Disk、ISRを継続監視する。
- 失敗イベントはSQS Retry QueueとDLQへ隔離し、再処理Runbookを用意する。

## CDCと整合性

- DB更新とイベント発行の二重書き込みを避けるため、Transactional Outboxを採用する。
- Auroraからの変更はDMS CDCまたはOutbox PublisherでMSKへ配信する。
- DynamoDBはStreamsからイベントを配信する。
- 検索Indexと分析データは再構築可能な派生データとして扱う。
- イベントには一意ID、Schema Version、発生時刻、テナントID、追跡IDを含める。

## データレイクと分析

- S3 Data LakeをRaw、Curated、Serving Zoneへ分離する。
- データ基盤図ではMSK BrokerとRedshift Multi-AZのコンピュートを2AZに分けて示す。
- S3、Glue、Athenaなどリージョナルまたはサーバーレスなサービスは、特定AZへ配置されると誤解させないためAZ内に重複表示しない。
- テーブル形式はApache Icebergを基本とし、Schema Evolution、Partition Evolution、Time Travelを利用する。
- 小さいファイルが大量発生しないよう、定期Compactionを実行する。
- Glue Data Catalogで技術メタデータを管理する。
- Lake FormationのLF-Tagsと行・列・セルレベル権限でアクセスを制御する。
- 大規模変換はEMR ServerlessまたはGlue ETL、Ad-hoc分析はAthena、BIはRedshiftを利用する。
- RedshiftにはBI用に整形済みデータのみをロードし、OLTP DBを直接参照しない。
- データ品質、鮮度、件数差分、Schema Drift、処理遅延をSLOとして監視する。

## セキュリティとガバナンス

- 各データプロダクトに、業務Owner、Technical Owner、品質SLO、Schema、保持期間、利用規約を定義する。
- データドメインごとにKMSキー、S3 Prefix、Lake Formation権限を分離する。
- MacieでPIIを検出し、分類結果に基づいてアクセスと保持期間を制御する。
- 個人情報の削除要求に対応できるよう、データ系統と削除伝播処理を管理する。
- Raw Zoneは原則不変とするが、法的削除要件と保持ポリシーを優先する。
- 監査・セキュリティイベントはSecurity Lakeへ集約する。

## 容量計画と運用

- Aurora PostgreSQL Limitlessは、DDL、制約、拡張機能、バックアップ、復旧手順の制限を事前検証し、互換性を満たす場合のみ採用する。
- Limitlessが要件に合わない場合は、通常Auroraのパーティショニング、ドメイン分割、またはサービス単位のDB分離を選択する。
- ピーク時の書き込み件数、イベントサイズ、保持期間、再処理時間からMSK Partition数を設計する。
- DynamoDBのアクセスパターンとPartition Key分布を負荷試験で検証する。
- Aurora LimitlessのShard Keyとクロスシャードクエリを継続分析する。
- S3 Prefix、Iceberg Partition、Redshift Distribution/Sort Keyを実データ分布で見直す。
- 破壊的Schema変更は禁止し、後方互換を維持した段階的移行を行う。
- 定期的にバックフィル、全量再構築、DLQ再処理、Data Lake復旧を訓練する。

## 図

- `aws_architecture_large_scale_online.png`: 大量トランザクション、イベント、検索向けオンライン処理。
- `aws_architecture_large_scale_data_platform.png`: Data Lake、ETL、DWH、ガバナンス、分析基盤。
