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

- Laravel: 添付ストレージ変更後の Pest 57 tests / 291 assertions が隔離 SQLite と PostgreSQL 17 の両方で成功。
- Frontend: Vitest 1 file / 2 tests、TypeScript typecheck、ESLint、Prettier format check、Next production build が成功。サポートするフロントエンド runtime は `frontend/package.json` の engine 要件に従い Node 22.12 以上。runtime dependency audit は 0 findings。development dependency audit は `eslint-config-next` の古い transitive dependency に限り 5 high findings が残る。
- 実 API + ブラウザー: `npm run e2e:next` が隔離 SQLite を作成し、同一 origin で API HTTP suite と Playwright 3 tests を成功させた。確認した操作は CSRF 419/422、旅行作成/編集、空の日付、ポインターとキーボードでの日程並び替え、添付 upload/download/delete、保存後の再読込、本人別の持ち物と全削除、入力エラー、メンバー追加/削除、共有パスワード誤り/成功、読み取り専用、共有停止、モバイルヘッダーとログアウト。
- 2026-10-05 の追加ブラウザー確認: `/api/v1/session` の初回 HTTP 500 では失敗表示と再試行を提示し、API復旧後の再試行で一覧へ回復した。未保存状態のブラウザー戻る/進むと「一覧へ戻る」は、確認をキャンセルすると画面と入力値を保持し、承認すると移動する。保存後の戻る操作は確認なしで移動した。共有期限だけを更新してパスワード欄を空のまま保存した後も、既存パスワードで共有画面を開けた。これらの手動ブラウザー確認では意図的なHTTP 500以外にconsole error/pageerrorはなかった。
- 最終 `npm run e2e:next`: production build、API HTTP suite、Playwright 3 tests が成功した。ドラッグ並び替えは auto-scroll で固定 target が移動するため、E2E のみ pointer 移動中に対象行の bounding box を再取得するよう調整し、順序変更と保存後の再読込 assertion を維持した。owner の drag・save・reload ケースも追加で2回連続成功した。
- Docker: Next standalone image と Laravel を含む combined image の build/smoke test が成功した。PostgreSQL 17 を使う isolated container で migration、Next/Laravel の HTTP、session、CSRF、共有アクセスを確認。Next standalone は UID 1000 で動作し、combined image でも Next process は非 root で実行する。主要ページ、mockup、11件の JS/CSS asset が HTTP 200。R2 の実アカウント接続は別途未確認。
- Render 公開確認: [公開 URL](https://guide-2s9j.onrender.com/) のホーム、`/policy`、`/terms` は HTTP 200、ページ asset 67件はすべて HTTP 200。session endpoint は guest を返し、CSRF cookie を発行、guest の itinerary GET は 401。空DBでランダムな共有 URL は 404（記録不存在）となるため、本番で CSRF 419 が確認できたとは扱わない。419/422 は隔離 local E2E で検証済み。mobile menu、login の通常 document navigation、ページ遷移を確認し、login に Next RSC fetch/CORS はなく、対象 smoke で console error/pageerror は 0 件だった。
- Render OAuth: `/auth/google` は Google へ redirect するが、Google client ID が未設定のため `Missing required parameter: client_id` / `Error 400: invalid_request` で停止した。秘密値や stack trace の露出はなかった。認証後の callback、ログイン済み画面、本番での作成・共有・添付は未確認で、本番DBに記録を作成・変更していない。
- 添付ストレージの追加検証: `npm run e2e:next` の API HTTP suite と Playwright 3/3 が成功した。`Storage::fake('r2')` による private disk upload/download/remove と SDK request capture を確認し、request は R2 bucket hostname、`auto` region、ACL と任意の SDK checksum algorithm header なしで生成された。実 HTTP では旧 `disk = NULL` 添付を owner/member が取得でき、未認証は 403、共有パスワードを通した guest は取得でき、共有 revoke 後は 403、owner による itinerary 削除後に旧ファイルも消えることを確認した。
- 添付の故障時確認: 一時的な DB insert 失敗を fake R2 upload 後に発生させ、DB transaction が戻り新しい object も削除されることを確認した。削除側の失敗は best-effort として記録して保存済み変更を成功させる一方、object 自体は残るため後続 cleanup が必要。Cloudflare 実バケットへの接続確認ではない。
- Render の最新稼働 commit は `e1e5023fb38634a19c40a44ae5654317c82a80e8`。公開 HTTP smoke で `/` と Laravel health endpoint `/up` は 200、session API は `authenticated: false`、guest の itinerary collection は 401 を確認した。新規の空 PostgreSQL 17 free database（Oregon、2026-11-04 expiration）を接続し、旧 database の内容は移行していない。画面キャプチャは [`desktop-home.png`](/tmp/guide-render-next/desktop-home.png)、[`mobile-home.png`](/tmp/guide-render-next/mobile-home.png)、[`guest-protected.png`](/tmp/guide-render-next/guest-protected.png)、[`login-oauth-error.png`](/tmp/guide-render-next/login-oauth-error.png)。

ローカル画面キャプチャは [`desktop-edit.png`](/tmp/guide-next-e2e/desktop-edit.png) と [`mobile-home.png`](/tmp/guide-next-e2e/mobile-home.png) に保存した。E2E 用 session は CLI fixture が隔離 SQLite 上に作るもので、ログインを迂回する HTTP endpoint はない。テストは `.env` の DB を使わず、終了時に起動した PHP/Next/gateway を停止する。

## 添付ストレージ

添付は `FILESYSTEM_UPLOADS_DISK` で選んだ disk に保存し、各 `plan_files` 行へ disk 名を記録する。Cloudflare R2 は private `r2` disk として設定し、API/旧 Blade UI のダウンロードも同一オリジンの itinerary Policy 認可 endpoint を通る。R2 の接続情報はサーバー側の保存処理で使用し、Next.js クライアントコードやブラウザーには公開しない。`R2_*` を `NEXT_PUBLIC_*` 変数へ設定しない。AWS S3 disk は独立している。R2 fake disk と SDK request capture は検証済みだが、実アカウントへの接続は未検証。

旧データで `plan_files.disk` が null の場合は `FILESYSTEM_LEGACY_UPLOADS_DISK`（既定 `public`）を使う。環境により既存ファイルの保存先が異なる場合は、R2 切替前にこの変数を従来の disk 名へ合わせる。既存ファイルのコピー・backfill は行わず、DB migration は nullable disk 列の追加のみ。

## 運用上の未確認事項

移行の変更は `codex/next-typescript-migration` ブランチに保存した。private bucket `guide-attachments` は作成済みだが、R2 credentials はまだ発行・Render 登録されておらず、`FILESYSTEM_UPLOADS_DISK` は `public` のまま。実アカウントへの接続、添付の live upload/download は未確認で、R2 は有効化待ち。現在 Render コンテナ上で作成した public 添付は再作成後に残らない。Google 資格情報が未設定のため、実 Google OAuth callback とログイン済み公開 UI は未確認。新しい空DBに旧DBのデータはなく、旧DBは変更していない。AWS 設定は変更していない。
