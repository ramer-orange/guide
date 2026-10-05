# Next.js / TypeScript 移行の実装状況

更新日: 2026-10-05。設計方針は [移行プラン](NEXT_TYPESCRIPT_MIGRATION_PLAN.md) を参照。

## 実装済み

Laravel の既存認証・セッション・Policy・DB を使う JSON API と OpenAPI 契約を追加し、Next.js App Router にホーム、規約、ポリシー、しおり一覧/作成/編集、閲覧共有画面を実装した。所有者と編集メンバーは権限に応じて共同編集し、持ち物リストは利用者ごとに分かれる。閲覧共有のパスワード・期限・停止、メンバー管理、添付の取得/削除も接続済み。

Laravel/Vite の既定配信は残し、`docker-compose.next.yml` を明示した場合だけ Next standalone と同一オリジン Nginx gateway を追加する。API、Sanctum、OAuth、ログイン/ログアウト、Livewire、旧 Vite、添付は Laravel へ送り、移行済みページと `/_next/*` を Next へ送る。gateway は `Host`/`X-Forwarded-Host` の port を保持する。overlay は Laravel の環境を HTTP 開発用に上書きし、既定 compose の値は変更しない。

## ローカル起動と切り戻し

Node.js 22.12.0 以上と、`.env`・Composer 依存関係を用意し、通常のマイグレーションと Vite assets を準備してから起動する。

```sh
docker compose up -d laravel.test
./vendor/bin/sail artisan migrate
npm run build
docker compose -f docker-compose.yml -f docker-compose.next.yml up --build -d next next-gateway
```

既定の UI origin は `http://localhost:8081`。別 origin を使う場合、`NEXT_GATEWAY_PORT` は数値 port、`NEXT_PUBLIC_ORIGIN` は origin URL に設定する（例: `NEXT_GATEWAY_PORT=8082` と `NEXT_PUBLIC_ORIGIN=http://localhost:8082`）。Google OAuth の callback 登録も同じ origin に合わせる。overlay は Laravel に `APP_ENV=next-local`、gateway の `APP_URL`、`SESSION_SECURE_COOKIE=false` を設定する。

`NEXT_PUBLIC_ORIGIN` は Next.js の `NEXT_PUBLIC_SITE_URL` build argument/runtime environment に渡され、Open Graph metadata の base URL になります。公開 origin を変更した場合は `docker compose -f docker-compose.yml -f docker-compose.next.yml build next` で Next image を再ビルドする。

切り戻すときは、gateway を止めた後に base compose だけで Laravel コンテナを再作成し、overlay の環境を外す。

```sh
docker compose -f docker-compose.yml -f docker-compose.next.yml stop next-gateway next
docker compose -f docker-compose.yml up -d --force-recreate laravel.test
```

持ち物テンプレート名の重複を許可するため、新しい migration は既存の unique 制約を外す。migration を戻す際に同名の行があれば、行を消さずに `down()` を明示的に拒否する。

## 検証結果

- Laravel: Pest 52 tests / 261 assertions が成功。
- Frontend: Vitest 1 file / 2 tests、TypeScript typecheck、ESLint、Prettier format check、Next production build が成功。サポートするフロントエンド runtime は `frontend/package.json` の engine 要件に従い Node 22.12 以上。runtime dependency audit は 0 findings。development dependency audit は `eslint-config-next` の古い transitive dependency に限り 5 high findings が残る。
- 実 API + ブラウザー: `npm run e2e:next` が隔離 SQLite を作成し、同一 origin で API HTTP suite と Playwright 3 tests を成功させた。確認した操作は CSRF 419/422、旅行作成/編集、空の日付、ポインターとキーボードでの日程並び替え、添付 upload/download/delete、保存後の再読込、本人別の持ち物と全削除、入力エラー、メンバー追加/削除、共有パスワード誤り/成功、読み取り専用、共有停止、モバイルヘッダーとログアウト。
- Docker compose: 通常の `npm ci`、`docker compose -f docker-compose.yml -f docker-compose.next.yml config --quiet`、Next standalone image build、コンテナ HTTP smoke test が成功した。smoke test はコンテナ内 UID 1000 で実行し、ホーム・作成画面・mockup asset が HTTP 200、bundle の JavaScript/CSS 11 件も HTTP 200 を返した。OG image URL は `http://localhost:8081/images/ogp.webp`。Laravel container の起動と OAuth callback は未検証。

画面キャプチャは [`desktop-edit.png`](/tmp/guide-next-e2e/desktop-edit.png) と [`mobile-home.png`](/tmp/guide-next-e2e/mobile-home.png) に保存した。E2E 用 session は CLI fixture が隔離 SQLite 上に作るもので、ログインを迂回する HTTP endpoint はない。テストは `.env` の DB を使わず、終了時に起動した PHP/Next/gateway を停止する。

## 未実施の作業

移行の変更は `codex/next-typescript-migration` ブランチに保存した。push と production deploy はしていない。実 Google OAuth callback は未検証で、Laravel の自動テストでは Socialite をモックする。Next を含む production routing/service 構成と Render の現行提供状況は未確認。AWS deploy は停止状態のままで、既存本番設定を変更していない。
