# AWS Emulator でのローカル検証

Docker、AWS CLI v2、`jq`、`curl`、`openssl` が使える環境で実行します。macOS と Linux の Arm64・x86-64 の Docker ホストに対応する構成です。

```sh
bash scripts/check-aws-emulator.sh
```

スクリプトは現在のコミットを `git archive` で取り出して AWS 用のアプリイメージをビルドします。AWS Emulator 1.0.341、PostgreSQL 17、Guide を専用ネットワークで起動し、テスト終了時にコンテナ・ネットワーク・一時ファイルを片付けます。再実行に使う Docker イメージはローカルに残ります。Web の検証用ポートは `127.0.0.1:18088` です。使用中の場合は `GUIDE_EMULATOR_WEB_PORT=18089` のように変更できます。

AWS CLI はローカルエンドポイントとエミュレーターが発行した認証情報を使います。アプリの DB パスワードと `APP_KEY` は実行ごとに生成します。セッションは CLI で検証用ユーザーに発行します。

- `guide_admin` でアプリ用 DB ユーザーを作成し、マイグレーションを実行
- `guide_app` で起動したアプリのトップページと `/healthz` を確認
- Laravel の HTTP API から PDF を S3 にアップロードし、プレビューとダウンロードの内容を照合
- 未ログインでの取得が `403`、削除後の取得が `404` となり、S3 のオブジェクトも消えることを確認

## CDK スタックの検証結果

コミット `1075a86` と AWS Emulator 1.0.341 の組み合わせで、次の挙動を確認しました。

| 構成 | 検証結果 |
| --- | --- |
| Secrets Manager と RDS | CloudFormation が `{{resolve:secretsmanager:…}}` をそのまま渡し、RDS がパスワードを長さ超過で拒否。ローカルの SSM SecureString 参照への置き換えで作成は通る。エミュレーターの PostgreSQL は Laravel が接続時に使う `SET search_path` を拒否する。 |
| ステージングのデータスタック | ローカル用テンプレートでパスワード参照を置き換え、CDK のカスタムリソース4件を外すと39リソースが作成完了。元の `Custom::S3AutoDeleteObjects` は待機タイムアウトを確認。 |
| ステージングのアプリスタック | `AWS::WAFv2::LoggingConfiguration` はテンプレートの検証で拒否される。ACM の `DomainValidationOptions.HostedZoneId` は未実装。`SecretTargetAttachment` の `Ref` が Secret ARN を返さず、IAM ポリシーの登録に失敗する。 |
| ALB・Route 53・ECS | ローカル用の証明書と Secret ARN を渡し、WAF のログ設定と Auto Scaling を外すと、ALB の HTTP・HTTPS リスナーと Route 53 のレコードを作成できる。CloudFormation で作成完了と表示された ECS サービスとタスク定義は ECS API に現れない。ECS API で直接登録した検証用サービスは取得できるが、エミュレーター内で Guide の Docker イメージは起動できない。 |
| 監査スタック | AWS Config が配信先 S3 バケットの ACL を取得できず、配信チャネルの作成が失敗。 |

上のコマンドは Docker 上のアプリと PostgreSQL を使った S3 連携の検証です。AWS のスタック構築とデプロイは [AWS 本番環境の構築・運用手順](AWS_DEPLOYMENT_DONE.md)を参照してください。

[AWS Emulator の Docker 手順](https://docs.aws.amazon.com/emulator/latest/userguide/getting-started-docker.html) · [CloudFormation の参照解決の実装](https://github.com/aws/aws-emulator/blob/7eb5bf3e83f51302e9d7bc7ffd19e2ebc91fbccc/services/service-com-amazonaws-cloudformation-cloudformation/src/state/dynamic_references.rs) · [ECS のコンテナ実行の実装](https://github.com/aws/aws-emulator/blob/7eb5bf3e83f51302e9d7bc7ffd19e2ebc91fbccc/services/service-com-amazonaws-ecs-amazonec2containerservicev20141113/src/state/container_runtime.rs)
