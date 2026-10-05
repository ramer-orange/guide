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

- **フレームワーク:** Laravel 11.x
- **フロントエンド:** Livewire（既存 UI）, Next.js App Router + TypeScript（移行 UI）, Tailwind CSS
- **データベース:** PostgreSQL
- **開発環境:** Docker

## Next.js 移行 UI をローカルで起動する

Next.js は移行中のオプトイン構成です。既存の Laravel/Vite 開発環境は引き続き `docker-compose.yml` を使います。`.env` と Laravel の開発依存関係を準備し、Laravel のマイグレーションと従来の Vite assets を先に用意してから、同じオリジンのゲートウェイを追加で起動します。

フロントエンドの作業には Node.js 22.12.0 以上が必要です。Node.js 22.12 以降または 24 を使ってください。Laravel の依存関係を `composer install`、root と Next の npm 依存関係を `npm ci` と `npm ci --prefix frontend` で準備します。

```sh
docker compose up -d laravel.test
./vendor/bin/sail artisan migrate
npm run build
docker compose -f docker-compose.yml -f docker-compose.next.yml up --build -d next next-gateway
```

移行 UI は `http://localhost:8081`（`NEXT_GATEWAY_PORT` は port 番号）で開きます。別の port/domain を使う場合、`NEXT_GATEWAY_PORT` には数値、`NEXT_PUBLIC_ORIGIN` には公開 origin URL を設定し、両者を一致させます。たとえば `.env` に `NEXT_GATEWAY_PORT=8082`、`NEXT_PUBLIC_ORIGIN=http://localhost:8082` を設定します。`NEXT_PUBLIC_ORIGIN` は Next の Open Graph metadata origin と standalone image の build argument にも使われるため、値を変更した後は `docker compose -f docker-compose.yml -f docker-compose.next.yml build next` を再実行してください。overlay は Laravel 側の `APP_ENV` を `next-local`、`APP_URL` を gateway origin、`SESSION_SECURE_COOKIE` を `false` に上書きし、既定の Laravel compose 環境には影響しません。`/api/v1/*`、`/sanctum/*`、`/auth/*`、ログイン/ログアウト、`/up`、Livewire、Vite assets、`/storage/*` は Laravel へ届き、設定済みの Next ページと `/_next/*` は Next.js へ届きます。その他の画面は Laravel に残ります。この同一オリジン経路によりブラウザは Laravel のセッション Cookie と CSRF Cookie を同じホストへ送れます。Google OAuth の callback URL もゲートウェイの公開 URL に合わせて設定してください。

Next の画面を一時的に切り戻すには、Next と gateway を止め、base compose だけで Laravel を再作成して overlay の環境変数を戻します。

```sh
docker compose -f docker-compose.yml -f docker-compose.next.yml stop next-gateway next
docker compose -f docker-compose.yml up -d --force-recreate laravel.test
```

その後、既存の Laravel URL（通常 `http://localhost`）を使います。gateway は DB や Laravel の保存データを変更しません。

実ブラウザーによる移行フローの検証には、Composer/npm 依存関係を用意した後に一度 Chromium を入れ、`npm run e2e:next` を実行します。このコマンドは専用の `database/e2e.sqlite` を作り、owner/member/guest のテスト記録と暗号化セッションを CLI で準備します。通常の `.env` に書かれた DB 接続先は使わず、PHP・Next.js・同一オリジン gateway をローカルで起動して検証後に停止します。OAuth callback を偽装する HTTP ルートは追加しません。

```sh
npm --prefix frontend run e2e:install
npm run e2e:next
```

API の HTTP E2E と Playwright のブラウザ E2E は別々に出力されます。スクリーンショットは `/tmp/guide-next-e2e/` に保存されます。

## Render の既存 Web Service で移行 UI を動かす

Render 用の `Dockerfile.render-next` は Laravel と Next.js standalone を1つのコンテナに含め、Nginx が同じ origin のまま両者へ振り分けます。既存 Render Web Service は branch `codex/next-typescript-migration`、Dockerfile path `Dockerfile.render-next` で `https://guide-2s9j.onrender.com/` に稼働しています（commit `50c50c2096c6ee25859dfcf4ccb642df108f304b`）。Render が渡す数値の `PORT` を Nginx が listen し、`/`、移行済み itinerary ページ、`/_next/*`、`/images/*` は Next へ、API・Sanctum・認証・添付・既存 Laravel 画面は Laravel へ送ります。

既存 Service の環境変数と秘密情報を引き継ぎ、`APP_KEY`、`APP_URL=https://guide-2s9j.onrender.com`、セッション設定を維持してください。PostgreSQL 接続先は新しい空の PostgreSQL 17 free database（Oregon）に設定済みです。旧 database のデータはコピーしていません。free database は 2026-11-04 に期限切れになります。Google OAuth を使う場合は `GOOGLE_CLIENT_ID`、`GOOGLE_CLIENT_SECRET`、`GOOGLE_REDIRECT_URI=https://guide-2s9j.onrender.com/auth/google/callback` と Google 側 callback 登録が必要です。現在の OAuth redirect はGoogleへ到達しますが、`GOOGLE_CLIENT_ID` 未設定により Google が `Missing required parameter: client_id` を返します。Next の metadata origin は build arg `NEXT_PUBLIC_SITE_URL` で設定し、既定値は `https://guide-2s9j.onrender.com` です。

コンテナ起動時に `php artisan migrate --force` を実行します。現在の公開 URL はホーム・規約ページと asset 配信、session/CSRF、guest access を確認済みです。ログインは Google へ通常遷移しますが、Google credentials 未設定のため認証完了は未確認です。空DBのため公開画面からしおりを作成しておらず、ログイン後の保存・共有・添付は未検証です。seeder やテスト用ログインルートはありません。新規 migration をデプロイする前に、DB backup と migration の内容を確認してください。

添付を Cloudflare R2 に保存する場合、Render の環境変数に `FILESYSTEM_UPLOADS_DISK=r2`、`R2_ENDPOINT`、`R2_BUCKET`、`R2_ACCESS_KEY_ID`、`R2_SECRET_ACCESS_KEY` を設定します。R2 disk は非公開で、ブラウザーは同一オリジンの認可済み Laravel download endpoint 経由で取得します。AWS S3 の設定とは別です。新しい添付には disk 名が保存され、既存行で disk が null の添付は `FILESYSTEM_LEGACY_UPLOADS_DISK`（既定 `public`）から引き続き読み書き・削除します。既存ファイルの移動や backfill は自動実行しません。R2 を有効にする前に、既存の null disk ファイルが置かれた disk 名を確認して legacy disk を設定してください。公開動作の詳細は [実装状況](docs/NEXT_TYPESCRIPT_IMPLEMENTATION_STATUS.md) に記録しています。

## デプロイ計画

- [AWS構築計画](docs/AWS_DEPLOYMENT_PLAN.md)

## ER図
![Editor _ Mermaid Chart-2025-04-13-023724](https://github.com/user-attachments/assets/c763dea6-b6b7-4a25-8620-c0f4d08aaa12)
