# AWS 復元手順（バックアップからの復旧）

> **この手順はまだ実際の AWS 環境で通していません。** コマンドは AWS CLI の仕様に沿って書いていますが、所要時間と細部は未確認です。本番公開の前に、末尾の「復元訓練」を staging で一度実施し、気づいた点をこの文書へ反映してください。

## 目標

| 項目 | 目標 | 根拠となる設定 |
|---|---|---|
| 目標復旧時点（どこまでのデータを失ってよいか） | 5 分 | RDS 自動バックアップ。トランザクションログが約 5 分ごとに保存され、保持期間（7 日）内の任意の時点へ戻せる |
| 目標復旧時間（どれだけ止まってよいか） | 2 時間 | 下の手順 A を人が実行する時間。AZ 障害だけなら Multi-AZ の自動フェイルオーバーで 1〜2 分 |
| 障害の検知 | 約 10 分以内 | 外形監視が 5 分間隔・2 回連続の失敗で通知。タスクや ALB の異常は 1 分周期のアラームが別に見ている |

この数値は仮置きです。利用規約や契約で約束する内容が決まったら、ここと `infra/lib/environments.ts` を合わせて見直してください。

## 何がどこまで戻せるか

| 対象 | 仕組み | 戻せる範囲 |
|---|---|---|
| DB | RDS 自動バックアップ（PITR） | 直近 7 日の任意の時点 |
| DB | AWS Backup（Vault `guide-production`） | 日次を 35 日、月次を 1 年 |
| 添付ファイル | S3 バージョニング | 上書き・削除から 90 日以内の旧バージョン |
| インフラ | CDK（このリポジトリ） | スタックを作り直せる。データは上の 3 つから戻す |

守れていないもの: リージョン全体の障害（バックアップは同じリージョンにある）、バックアップを消せる権限を持つ人による削除（Vault Lock は掛けていない）。

## 手順 A: DB を過去の時点へ戻す

誤った一括更新・削除、マイグレーションの失敗、データ破損のときに使います。
RDS は「既存のインスタンスを巻き戻す」ことができません。別のインスタンスとして復元し、中身を確かめてから入れ替えます。

### A-1. 止める

これ以上データが変わらないよう、Web タスクを 0 台にします（利用者には 503 が返ります）。

```bash
aws ecs update-service --cluster guide-production --service guide-production-web --desired-count 0
```

GitHub Actions の自動デプロイも止めます（リポジトリ変数 `AWS_DEPLOY_ENABLED` を外す）。

### A-2. 念のため現状を残す

```bash
DB=$(aws cloudformation describe-stack-resources --stack-name GuideDataStack \
  --query "StackResources[?ResourceType=='AWS::RDS::DBInstance'].PhysicalResourceId" --output text)
```

```bash
aws rds create-db-snapshot --db-instance-identifier "$DB" \
  --db-snapshot-identifier "guide-before-restore-$(date +%Y%m%d%H%M)"
```

### A-3. 別インスタンスとして復元する

戻したい時刻は UTC で指定します（日本時間から 9 時間引く）。サブネットグループと Security Group は元のインスタンスと同じものを指定します。

```bash
aws rds describe-db-instances --db-instance-identifier "$DB" \
  --query "DBInstances[0].{subnetGroup: DBSubnetGroup.DBSubnetGroupName, securityGroups: VpcSecurityGroups[].VpcSecurityGroupId, class: DBInstanceClass}"
```

```bash
aws rds restore-db-instance-to-point-in-time \
  --source-db-instance-identifier "$DB" \
  --target-db-instance-identifier "${DB}-restored" \
  --restore-time 2026-01-01T03:00:00Z \
  --db-subnet-group-name <subnetGroup> \
  --vpc-security-group-ids <securityGroups> \
  --db-instance-class <class> \
  --multi-az --no-publicly-accessible --deletion-protection
```

```bash
aws rds wait db-instance-available --db-instance-identifier "${DB}-restored"
```

7 日より前へ戻すときは、AWS Backup の復旧ポイント（Vault `guide-production`）から復元します。コンソールの AWS Backup →「保護されたリソース」から対象の復旧ポイントを選び、同じサブネットグループと Security Group を指定してください。

### A-4. 中身を確かめる

DB はインターネットからも手元からも繋がりません。確認は Migration タスクと同じ経路（同じ VPC・同じ Security Group の Fargate タスク）で行います。タスク定義 `guide-production-migration` の環境変数 `DB_HOST` を復元先のエンドポイントに差し替えたリビジョンを登録し、確認用のコマンド（例: `php artisan tinker --execute='echo App\Models\User::count();'`）で実行します。

DB ユーザーは復元後も元のままです。`guide_app` とそのパスワードは DB の中に入っているので、Web タスク用の Secret はそのまま使えます。

### A-5. 入れ替える

アプリは CloudFormation が出力した DB のエンドポイントを見ています。エンドポイントはインスタンス名から決まるので、名前を入れ替えれば CDK を変更せずに切り替わります。

```bash
aws rds modify-db-instance --db-instance-identifier "$DB" \
  --new-db-instance-identifier "${DB}-broken" --apply-immediately
```

```bash
aws rds wait db-instance-available --db-instance-identifier "${DB}-broken"
```

```bash
aws rds modify-db-instance --db-instance-identifier "${DB}-restored" \
  --new-db-instance-identifier "$DB" --apply-immediately
```

DB ユーザーとパスワードは DB の中身と一緒に戻るので、マスターユーザーと `guide_app` のどちらの Secret もそのまま使えます（戻した時点より後にパスワードを替えていた場合だけ、Secret を戻した時点の値に合わせる）。

入れ替えたあと、次の設定を元のインスタンスと同じ状態に戻します。

- 自動バックアップの保持日数・Performance Insights・ログ出力: `aws rds describe-db-instances` で元のインスタンス（`${DB}-broken`）と見比べ、違いを `modify-db-instance` で揃える

### A-6. 再開する

```bash
aws ecs update-service --cluster guide-production --service guide-production-web --desired-count 2
```

`https://<ドメイン>/healthz` と、ログイン・しおりの表示・添付ファイルのダウンロードを確認します。問題が無ければ `AWS_DEPLOY_ENABLED` を戻します。
`${DB}-broken` は原因の調査が終わるまで残し、不要になったら削除保護を外して削除します。

## 手順 B: 添付ファイルを戻す

バケット名は `GuideDataStack` の Output `FilesBucketName` です。

```bash
aws s3api list-object-versions --bucket <FilesBucketName> --prefix <パス>
```

- 削除されたファイル: 一覧の `DeleteMarkers` にある削除マーカーを消すと、直前のバージョンが復活します（`aws s3api delete-object --bucket <FilesBucketName> --key <キー> --version-id <削除マーカーの VersionId>`）
- 上書きされたファイル: 戻したいバージョンを同じキーへコピーします（`aws s3api copy-object --bucket <FilesBucketName> --key <キー> --copy-source "<FilesBucketName>/<キー>?versionId=<VersionId>"`）

DB を過去へ戻した場合、その時点より後にアップロードされたファイルは S3 に残りますが、DB からは参照されなくなります。害はありません。

## 復元訓練

本番公開の前に 1 回、その後は半年に 1 回、staging で次を実施して記録を残します。

- [ ] 手順 A-2 〜 A-5 を staging（`GuideStagingDataStack`、Single-AZ なので `--multi-az` は外す）で通す
- [ ] A-1 から A-6 までの所要時間を測り、目標復旧時間（2 時間）に収まるか確かめる
- [ ] A-4 の確認用タスクの具体的なコマンドをこの文書に書き足す
- [ ] 手順 B で、削除したファイルと上書きしたファイルを 1 つずつ戻す
- [ ] 入れ替え後に Migration タスクと通常のデプロイが成功することを確かめる

| 実施日 | 環境 | 所要時間 | 気づいた点 |
|---|---|---|---|
| （未実施） | | | |
