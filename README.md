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

本番では既存の `docker-compose.prod.yml` のままです。Next を稼働させる場合は、TLS を終端する共通入口から同じセッション Cookie を保ったまま Laravel と Next に振り分け、Next を standalone Node サービスとして実行する構成を別途用意します。実際の Render サービス構成は未確認のため、このリポジトリからデプロイを開始しません。

## デプロイ計画

- [AWS構築計画](docs/AWS_DEPLOYMENT_PLAN.md)

## ER図
![Editor _ Mermaid Chart-2025-04-13-023724](https://github.com/user-attachments/assets/c763dea6-b6b7-4a25-8620-c0f4d08aaa12)
