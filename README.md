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

- **共有機能**  
  パスワード保護付きで旅行計画を友人や家族と共有できます。

- **ドラックアンドドロップ**  
  ドラックアンドドロップでプランや持ち物等の並び順を簡単に変更できます。

## 技術スタック

- **バックエンド:** Laravel 11.x（JSON API、認証、認可、データ永続化）
- **フロントエンド:** Next.js App Router + TypeScript（唯一の UI）, Tailwind CSS
- **データベース:** PostgreSQL
- **開発環境:** Docker

## ローカル開発環境を起動する

画面は Next.js、API・認証・認可・DB・添付保存は Laravel が担当します。ブラウザー向けの入口には同一オリジン gateway を使い、Laravel の session/CSRF cookie を API 呼び出しに利用します。

Node.js 22.12.0 以上を使います。Laravel と Next.js の依存関係をインストールします。

```sh
composer install
npm ci
npm ci --prefix frontend
docker compose up -d laravel.test
./vendor/bin/sail artisan migrate
docker compose up --build -d next next-gateway
```

画面は `http://localhost:8081` で開きます。port を変える場合は `.env` の `NEXT_GATEWAY_PORT` と `NEXT_PUBLIC_ORIGIN` を同じ origin に設定します。Google OAuth callback URL もこの origin に合わせます。標準の `docker-compose.yml` に Next.js と gateway が含まれています。gateway は `/api/v1/*`、`/sanctum/*`、`/auth/*`、`/login`、`/logout`、`/register`、`/up`、`/storage/*` を Laravel へ送り、画面と `/_next/*` を Next.js へ送ります。

実ブラウザーによる E2E には、依存関係を用意した後に一度 Chromium を入れ、`npm run e2e:next` を実行します。このコマンドは専用の `database/e2e.sqlite` を作り、owner/member/guest のテスト記録と暗号化セッションを CLI で準備します。通常の `.env` に書かれた DB 接続先は使わず、PHP・Next.js・同一オリジン gateway をローカルで起動して検証後に停止します。OAuth callback を偽装する HTTP ルートは追加しません。

```sh
npm --prefix frontend run e2e:install
npm run e2e:next
```

API の HTTP E2E と Playwright のブラウザ E2E は別々に出力されます。スクリーンショットは `/tmp/guide-next-e2e/` に保存されます。

## Render の Web Service

Render 用の `Dockerfile.render-next` は Laravel API と Next.js standalone を1つのコンテナに含め、Nginx が同じ origin で振り分けます。Render が渡す数値の `PORT` を Nginx が listen し、画面と `/_next/*`、`/images/*` は Next へ、API・Sanctum・認証・添付は Laravel へ送ります。

GitHub Actions は全ブランチへの push と Pull Request で検証を実行します。Render の本番 Web Service は Dashboard の Settings → Auto-Deploy を **After CI Checks Pass** に設定し、GitHub Actions のチェックが成功した後にだけデプロイしてください。既存 Service の Auto-Deploy 設定は Dashboard 側で管理されています。

既存 Service の環境変数と秘密情報を引き継ぎ、`APP_KEY`、`APP_URL=https://guide-2s9j.onrender.com`、セッション設定を維持してください。PostgreSQL 接続先は新しい空の PostgreSQL 17 free database（Oregon）に設定済みです。旧 database のデータはコピーしていません。free database は 2026-11-04 に期限切れになります。Google OAuth は Google Cloud project `food-recipe-493211` の OAuth client `PLAGINE Render` を使い、consent screen `food-recipe` は External testing のまま運用しています（branding と publication status は変更していません）。Render の `GOOGLE_CLIENT_ID`、`GOOGLE_CLIENT_SECRET`、`GOOGLE_REDIRECT_URI=https://guide-2s9j.onrender.com/auth/google/callback` は登録済みです。公開ブラウザー確認で Google への通常遷移、client ID の存在、callback URL の一致、`openid profile email` scope を確認しました。Next の metadata origin は build arg `NEXT_PUBLIC_SITE_URL` で設定し、既定値は `https://guide-2s9j.onrender.com` です。

コンテナ起動時に `php artisan migrate --force` を実行します。公開 URL はホーム・規約ページと asset 配信、session/CSRF、guest access を確認済みです。OAuth redirect は Google sign-in 画面まで確認しましたが、Google account での sign-in、consent、callback は行っていません。空DBのため公開画面からしおりを作成しておらず、ログイン後の保存・共有・添付は未検証です。seeder やテスト用ログインルートはありません。新規 migration をデプロイする前に、DB backup と migration の内容を確認してください。

現在の Render Service は private R2 bucket `guide-attachments` を新しい添付の保存先として使います（`FILESYSTEM_UPLOADS_DISK=r2`、`FILESYSTEM_LEGACY_UPLOADS_DISK=public`）。資格情報は Render runtime の環境変数として保持し、値をソースや Next.js build に含めません。ブラウザーは同一オリジンの認可済み Laravel download endpoint 経由で取得します。AWS S3 の設定とは別です。新しい添付には disk 名が保存され、既存行で disk が null の添付は `public` から引き続き読み書き・削除します。既存ファイルの移動や backfill は自動実行しません。公開動作の詳細は [実装状況](docs/NEXT_TYPESCRIPT_IMPLEMENTATION_STATUS.md) に記録しています。

## デプロイ計画

- [AWS構築計画](docs/AWS_DEPLOYMENT_PLAN.md)

## ER図
![Editor _ Mermaid Chart-2025-04-13-023724](https://github.com/user-attachments/assets/c763dea6-b6b7-4a25-8620-c0f4d08aaa12)
