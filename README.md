# 旅行しおりアプリ

## 概要

旅行のしおりをWeb上で簡単に作成・共有できるアプリケーションです。  
旅行の計画、持ち物リスト、観光スポット情報など、旅行に必要な情報を一元管理し、友人や家族と共有できます。

## 主な機能

- **旅行プラン作成**  
  日程ごとの旅行スケジュールを簡単に作成できます。

- **持ち物リスト管理**  
  チェックリスト形式で持ち物を管理可能です。（テンプレート機能あり）

- **お土産リスト**  
  お土産の候補や購入状況を記録できます。

- **メモ機能**      
  自由記述形式でメモを追加可能です。

- **ファイル添付**  
  予約確認書やパンフレットなどのファイルを添付できます。

- **共有機能**：パスワード保護付きで旅行計画を友人や家族と共有できます。

- **ドラッグアンドドロップ**：プランや持ち物等の並び順を変更できます。

## 技術スタック

- **フレームワーク:** Laravel 11.x
- **フロントエンド:** Next.js App Router + TypeScript, Tailwind CSS
- **データベース:** PostgreSQL
- **開発環境:** Docker

## ローカルでNext.jsアプリを起動する

画面はNext.js、API・認証・セッションはLaravelが担当します。`docker-compose.yml` にLaravel、Next.js、Nginx gatewayの各サービスがあります。gatewayを入口にすると、ブラウザーからは同一originのまま、画面をNext.jsへ、APIなどをLaravelへ振り分けます。

事前に `.env` を用意し、`composer install` でLaravelの依存関係を準備してください。E2Eを実行する場合はNode.js 22.12.0以上とフロントエンドの依存関係も必要です。Next.jsのDocker image build時にフロントエンドの依存関係とproduction buildを作成します。

```sh
docker compose up -d laravel.test
./vendor/bin/sail artisan migrate
docker compose up --build -d next next-gateway
```

アプリは `http://localhost:8081`（`NEXT_GATEWAY_PORT` で変更可能）で開きます。別のportを使う場合は `.env` の `NEXT_GATEWAY_PORT`（数値）と `NEXT_PUBLIC_ORIGIN`（origin URL）を一致させます。たとえば `NEXT_GATEWAY_PORT=8082`、`NEXT_PUBLIC_ORIGIN=http://localhost:8082` とします。`NEXT_PUBLIC_ORIGIN` はNext.jsのmetadata用URLとしてbuild時に使われるため、変更後は `docker compose up --build -d next next-gateway` でNext.js imageを再buildしてください。Google OAuthを使う場合はcallback URLもgatewayの公開URLに合わせます。

gatewayは `/api/`、`/sanctum/`、`/auth/`、`/login`、`/logout`、`/register`、`/up`、`/storage/` をLaravelへ送り、`/_next/`、`/images/` とそれ以外の画面URLをNext.jsへ送ります。Livewire/Blade画面やVite画面へフォールバックする構成ではありません。この同一origin経路では、ブラウザーがLaravelのセッションCookieとCSRF Cookieを同じoriginへ送れます。

Next.jsアプリを停止する場合はNext.jsとgatewayを停止します。停止後、gateway経由の画面は利用できません（Laravel画面へ自動で切り替わる構成ではありません）。

```sh
docker compose stop next-gateway next
```

LaravelのAPIやDBは別サービスとして動作します。gatewayの停止自体はDBやLaravelの保存データを変更しません。

実ブラウザーでのE2Eには、Composer依存関係とフロントエンドのnpm依存関係を用意し、Chromiumをインストールした後に `npm run e2e:next` を実行します。このコマンドは専用の `database/e2e.sqlite` を作り、owner/member/guestのテスト記録とセッションfixtureをCLIで準備します。通常の `.env` に書かれたDB接続先は使わず、PHP・Next.js・同一origin gatewayをローカルで起動して、検証後に停止します。OAuth callbackを偽装するHTTPルートは追加しません。

```sh
npm --prefix frontend run e2e:install
npm run e2e:next
```

API の HTTP E2E と Playwright のブラウザ E2E は別々に出力されます。スクリーンショットは `/tmp/guide-next-e2e/` に保存されます。

## Renderの構成

Renderでは `Dockerfile.render-next` がLaravelとNext.js standaloneを1つのコンテナにまとめ、Nginxが単一originでリクエストを振り分けます。`/api/`、`/sanctum/`、`/auth/`、`/login`、`/logout`、`/register`、`/up` はLaravelへ、`/_next/`、`/images/` とそれ以外の画面URLはNext.jsへ届きます。つまり、Renderでもユーザー向けの画面はNext.jsが担当し、Laravel画面へフォールバックしません。

起動時に `php artisan migrate --force` を実行します。デプロイ前に新しいmigrationとDB backupを確認してください。Renderのbranch・環境変数・DBの期限・OAuth設定などの運用状況や、確認済み/未確認の本番操作は変わるため、[実装状況](docs/NEXT_TYPESCRIPT_IMPLEMENTATION_STATUS.md)を参照してください。秘密情報はRenderのruntime環境変数で管理し、ソースやNext.js buildへ含めないでください。

添付の保存先は `FILESYSTEM_UPLOADS_DISK` で選びます。Renderではprivate R2 bucketを使い、ブラウザーからは認可済みのLaravel endpointを通して取得します。R2資格情報はサーバー側のruntime環境変数で管理し、Next.jsクライアントコードやブラウザーへ公開しません。AWS S3の設定とは別です。添付レコードには保存先disk名を記録し、disk名がない既存レコードは `FILESYSTEM_LEGACY_UPLOADS_DISK` で指定した従来diskから読み書き・削除します。既存ファイルの移動やbackfillは自動実行しません。

## AWSへのデプロイ計画

現在のRender構成とは別の、将来のAWS構築計画です。詳細は[AWS構築計画](docs/AWS_DEPLOYMENT_PLAN.md)を参照してください。

## ER図
![Editor _ Mermaid Chart-2025-04-13-023724](https://github.com/user-attachments/assets/c763dea6-b6b7-4a25-8620-c0f4d08aaa12)
