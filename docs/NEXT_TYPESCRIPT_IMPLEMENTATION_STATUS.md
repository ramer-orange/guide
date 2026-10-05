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
- 2026-10-05 の追加ブラウザー確認: `/api/v1/session` の初回 HTTP 500 では失敗表示と再試行を提示し、API復旧後の再試行で一覧へ回復した。未保存状態のブラウザー戻る/進むと「一覧へ戻る」は、確認をキャンセルすると画面と入力値を保持し、承認すると移動する。保存後の戻る操作は確認なしで移動した。共有期限だけを更新してパスワード欄を空のまま保存した後も、既存パスワードで共有画面を開けた。これらの手動ブラウザー確認では意図的なHTTP 500以外にconsole error/pageerrorはなかった。
- 最終 `npm run e2e:next`: production build、API HTTP suite、Playwright 3 tests が成功した。ドラッグ並び替えは auto-scroll で固定 target が移動するため、E2E のみ pointer 移動中に対象行の bounding box を再取得するよう調整し、順序変更と保存後の再読込 assertion を維持した。owner の drag・save・reload ケースも追加で2回連続成功した。
- Docker: Next standalone image と Laravel を含む combined image の build/smoke test が成功した。PostgreSQL 17 を使う isolated container で migration、Next/Laravel の HTTP、session、CSRF、共有アクセスを確認。Next standalone は UID 1000 で動作し、combined image でも Next process は非 root で実行する。主要ページ、mockup、11件の JS/CSS asset が HTTP 200。Pest 52 tests / 261 assertions は SQLite と PostgreSQL 17 の両方で成功した。アップロードは現在コンテナ内 local storage に保存され、container 再作成後の永続性はない。
- Render 公開確認: [公開 URL](https://guide-2s9j.onrender.com/) のホーム、`/policy`、`/terms` は HTTP 200、ページ asset 67件はすべて HTTP 200。session endpoint は guest を返し、CSRF cookie を発行、guest の itinerary GET は 401。空DBでランダムな共有 URL は 404（記録不存在）となるため、本番で CSRF 419 が確認できたとは扱わない。419/422 は隔離 local E2E で検証済み。mobile menu、login の通常 document navigation、ページ遷移を確認し、login に Next RSC fetch/CORS はなく、対象 smoke で console error/pageerror は 0 件だった。
- Render OAuth: `/auth/google` は Google へ redirect するが、Google client ID が未設定のため `Missing required parameter: client_id` / `Error 400: invalid_request` で停止した。秘密値や stack trace の露出はなかった。認証後の callback、ログイン済み画面、本番での作成・共有・添付は未確認で、本番DBに記録を作成・変更していない。
- Render は commit `50c50c2096c6ee25859dfcf4ccb642df108f304b` で稼働中。新規の空 PostgreSQL 17 free database（Oregon、2026-11-04 expiration）を接続し、旧 database の内容は移行していない。画面キャプチャは [`desktop-home.png`](/tmp/guide-render-next/desktop-home.png)、[`mobile-home.png`](/tmp/guide-render-next/mobile-home.png)、[`guest-protected.png`](/tmp/guide-render-next/guest-protected.png)、[`login-oauth-error.png`](/tmp/guide-render-next/login-oauth-error.png)。

ローカル画面キャプチャは [`desktop-edit.png`](/tmp/guide-next-e2e/desktop-edit.png) と [`mobile-home.png`](/tmp/guide-next-e2e/mobile-home.png) に保存した。E2E 用 session は CLI fixture が隔離 SQLite 上に作るもので、ログインを迂回する HTTP endpoint はない。テストは `.env` の DB を使わず、終了時に起動した PHP/Next/gateway を停止する。

## 運用上の未確認事項

移行の変更は `codex/next-typescript-migration` ブランチに保存した。Google 資格情報が未設定のため、実 Google OAuth callback とログイン済み公開 UI は未確認。新しい空DBに旧DBのデータはなく、旧DBは変更していない。添付の永続化は未構成である。AWS 設定は変更していない。
