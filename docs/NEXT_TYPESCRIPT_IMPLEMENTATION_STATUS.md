# Next.js / TypeScript の実装状況

更新日: 2026-10-14。Laravel を API、Next.js を唯一の画面として運用する最終構成を記録する。旧移行計画は [履歴](NEXT_TYPESCRIPT_MIGRATION_PLAN.md) として保管。

## 実装済み

Laravel の JSON API と OpenAPI 契約を使い、Next.js App Router がホーム、規約、ポリシー、しおり一覧/作成/編集、閲覧共有を含む全画面を提供する。Laravel は認証・セッション・Policy・DB・入力検証・添付保存を担当する。所有者と編集メンバーは権限に応じて共同編集し、持ち物リストは利用者ごとに分かれる。閲覧共有のパスワード・期限・停止、メンバー管理、添付の取得/削除も API 経由で接続済み。

Laravel の web route には認証開始・callback・logout の入口だけを残し、画面はすべて Next.js が配信する。Blade、Livewire、Volt、Vite の画面資産と依存関係は削除済み。ローカル Docker と Render は同一 origin の Nginx gateway を使い、`/api/v1/*`、Sanctum、認証 endpoint、添付 path は Laravel、画面と `/_next/*` は Next.js へ送る。gateway は session cookie と OAuth callback に必要な forwarded host と port を保持する。

## ローカル起動

Node.js 22.12.0 以上、`.env` と Composer 依存関係を用意して起動する。

```sh
composer install
npm ci
npm ci --prefix frontend
docker compose up -d laravel.test
./vendor/bin/sail artisan migrate
docker compose up --build -d next next-gateway
```

既定の UI origin は `http://localhost:8081`。別 origin を使う場合、`NEXT_GATEWAY_PORT` は数値 port、`NEXT_PUBLIC_ORIGIN` は origin URL に設定する（例: `NEXT_GATEWAY_PORT=8082` と `NEXT_PUBLIC_ORIGIN=http://localhost:8082`）。Google OAuth の callback 登録も同じ origin に合わせる。標準の compose 設定が Laravel の `APP_URL` と `SESSION_SECURE_COOKIE` を gateway に合わせる。

`NEXT_PUBLIC_ORIGIN` は Next.js の `NEXT_PUBLIC_SITE_URL` build argument/runtime environment に渡され、Open Graph metadata の base URL になります。公開 origin を変更した場合は `docker compose build next` で Next image を再ビルドする。

持ち物テンプレート名の重複を許可するため、新しい migration は既存の unique 制約を外す。migration を戻す際に同名の行があれば、行を消さずに `down()` を明示的に拒否する。

## 最終構成のレビューと検証（2026-10-14）

- Laravel: Livewire / Volt / Breeze を実際に依存から除いた状態で、SQLite / PHP 8.4 と PostgreSQL 17 / PHP 8.3 の両方で 49 tests / 293 assertions が成功。
- Next.js: Vitest 2 tests、TypeScript、ESLint、production build が成功。
- 同一 origin の API HTTP suite と Playwright 9 tests が成功。添付の単体・合計容量の境界、409 後の入力保持、共有の権限、ログアウトも含む。
- `Dockerfile.render-next` の build と隔離 PostgreSQL 上での起動が成功。ホーム・規約・ポリシー・一覧は 200、guest API は 401、CSRF は 204、旧 Livewire URL と存在しない画面は Next.js の 404 になることを確認。
- レビューで Render Blueprint の Dockerfile 指定、削除済み Unit suite の参照、HTTP local 環境の HTTPS 強制を修正。E2E は本番と同じ upload 設定を読み、単一 PHP server に合わせて直列実行する。ログアウト用 session は他の owner 検証から分離する。

## 過去の検証記録（2026-10-05）

- Laravel: 添付ストレージ変更後の Pest 57 tests / 291 assertions が隔離 SQLite と PostgreSQL 17 の両方で成功。
- Frontend: Vitest 1 file / 2 tests、TypeScript typecheck、ESLint、Prettier format check、Next production build が成功。サポートするフロントエンド runtime は `frontend/package.json` の engine 要件に従い Node 22.12 以上。runtime dependency audit は 0 findings。development dependency audit は `eslint-config-next` の古い transitive dependency に限り 5 high findings が残る。
- 実 API + ブラウザー: `npm run e2e:next` が隔離 SQLite を作成し、同一 origin で API HTTP suite と Playwright 3 tests を成功させた。確認した操作は CSRF 419/422、旅行作成/編集、空の日付、ポインターとキーボードでの日程並び替え、添付 upload/download/delete、保存後の再読込、本人別の持ち物と全削除、入力エラー、メンバー追加/削除、共有パスワード誤り/成功、読み取り専用、共有停止、モバイルヘッダーとログアウト。
- 2026-10-05 の追加ブラウザー確認: `/api/v1/session` の初回 HTTP 500 では失敗表示と再試行を提示し、API復旧後の再試行で一覧へ回復した。未保存状態のブラウザー戻る/進むと「一覧へ戻る」は、確認をキャンセルすると画面と入力値を保持し、承認すると移動する。保存後の戻る操作は確認なしで移動した。共有期限だけを更新してパスワード欄を空のまま保存した後も、既存パスワードで共有画面を開けた。これらの手動ブラウザー確認では意図的なHTTP 500以外にconsole error/pageerrorはなかった。
- 最終 `npm run e2e:next`: production build、API HTTP suite、Playwright 3 tests が成功した。ドラッグ並び替えは auto-scroll で固定 target が移動するため、E2E のみ pointer 移動中に対象行の bounding box を再取得するよう調整し、順序変更と保存後の再読込 assertion を維持した。owner の drag・save・reload ケースも追加で2回連続成功した。
- Docker: Next standalone image と Laravel を含む combined image の build/smoke test が成功した。PostgreSQL 17 を使う isolated container で migration、Next/Laravel の HTTP、session、CSRF、共有アクセスを確認。Next standalone は UID 1000 で動作し、combined image でも Next process は非 root で実行する。主要ページ、mockup、11件の JS/CSS asset が HTTP 200。実 R2 の確認は下記の隔離検証で実施。
- Render 公開確認: [公開 URL](https://guide-2s9j.onrender.com/) のホーム、`/policy`、`/terms` は HTTP 200、ページ asset 67件はすべて HTTP 200。session endpoint は guest を返し、CSRF cookie を発行、guest の itinerary GET は 401。空DBでランダムな共有 URL は 404（記録不存在）となるため、本番で CSRF 419 が確認できたとは扱わない。419/422 は隔離 local E2E で検証済み。mobile menu、login の通常 document navigation、ページ遷移を確認し、login に Next RSC fetch/CORS はなく、対象 smoke で console error/pageerror は 0 件だった。
- Render 最新 deploy `948d4e6bc7d37b64ab2c679d843679f22db76486` の読み取り専用 smoke: `/` と `/up` は 200、session API は `authenticated: false`、guest itinerary collection は 401。ブラウザーの login CTA は通常の document navigation で Google sign-in へ到達し、OAuth client ID と callback URL の一致、`openid profile email` scope を確認した。`missing client_id` や redirect mismatch はなく、対象 smoke の console error/pageerror は 0 件。Google account への sign-in 前に停止しており、本番の認証 session や itinerary は作成していない。
- Google OAuth 設定: Google Cloud project `food-recipe-493211`、OAuth client `PLAGINE Render`、consent screen `food-recipe`。audience は External testing のままで、branding と publication status は変更していない。Render に `GOOGLE_CLIENT_ID`、`GOOGLE_CLIENT_SECRET`、`GOOGLE_REDIRECT_URI=https://guide-2s9j.onrender.com/auth/google/callback` を登録済み。公開 smoke では `/auth/google` の redirect とブラウザー遷移を確認したが、Google account での sign-in、consent、OAuth callback、ログイン済み本番 UI および添付操作は未確認。Google の consent を承認せず、認証情報・state・code・cookie は記録していない。
- 添付ストレージの追加検証: `npm run e2e:next` の API HTTP suite と Playwright 3/3 が成功した。`Storage::fake('r2')` による private disk upload/download/remove と SDK request capture を確認し、request は R2 bucket hostname、`auto` region、ACL と任意の SDK checksum algorithm header なしで生成された。実 HTTP では旧 `disk = NULL` 添付を owner/member が取得でき、未認証は 403、共有パスワードを通した guest は取得でき、共有 revoke 後は 403、owner による itinerary 削除後に旧ファイルも消えることを確認した。
- 添付の故障時確認: 一時的な DB insert 失敗を fake R2 upload 後に発生させ、DB transaction が戻り新しい object も削除されることを確認した。削除側の失敗は best-effort として記録して保存済み変更を成功させる一方、object 自体は残るため後続 cleanup が必要。
- 実 R2 の隔離検証: 一時 credential を使い isolated SQLite app から private bucket へ保存し、`exists=true`、Policy 認可付き download 200 / 53 byte 完全一致を確認した。別の新しい Laravel process からも同じ 53 byte を取得でき、process restart 後の永続性を確認した後、object を削除し `exists=false` を確認。guest の共有アクセス保護も隔離環境で確認し、credential ファイル・test object・DB/session/fixture/log を cleanup 済み。隔離 fixture 用 object は実際の private bucket に作成・削除したが、本番アプリや本番DBの利用者添付は作成・変更していない。
- Render の最新稼働 commit は `948d4e6bc7d37b64ab2c679d843679f22db76486`。現在 `FILESYSTEM_UPLOADS_DISK=r2`、legacy disk は `public`、bucket は `guide-attachments`。公開 HTTP smoke は `/` と `/up` が 200、session API は未認証 guest、guest itinerary collection は 401。新規の空 PostgreSQL 17 free database（Oregon、2026-11-04 expiration）を接続し、旧 database の内容は移行していない。画面キャプチャは [`desktop-home.png`](/tmp/guide-render-next/desktop-home.png)、[`mobile-home.png`](/tmp/guide-render-next/mobile-home.png)、[`guest-protected.png`](/tmp/guide-render-next/guest-protected.png)、[`login-oauth-error.png`](/tmp/guide-render-next/login-oauth-error.png)。

ローカル画面キャプチャは [`desktop-edit.png`](/tmp/guide-next-e2e/desktop-edit.png) と [`mobile-home.png`](/tmp/guide-next-e2e/mobile-home.png) に保存した。E2E 用 session は CLI fixture が隔離 SQLite 上に作るもので、ログインを迂回する HTTP endpoint はない。テストは `.env` の DB を使わず、終了時に起動した PHP/Next/gateway を停止する。

## 添付ストレージ

添付は `FILESYSTEM_UPLOADS_DISK` で選んだ disk に保存し、各 `plan_files` 行へ disk 名を記録する。Render は private bucket `guide-attachments` を使う `r2` disk を新規添付の保存先に設定した。ダウンロードは同一オリジンの itinerary Policy 認可 API endpoint を通る。R2 の接続情報はサーバー側の保存処理で使用し、Next.js クライアントコードやブラウザーには公開しない。`R2_*` を `NEXT_PUBLIC_*` 変数へ設定しない。AWS S3 disk は独立している。Fake disk、SDK request capture と隔離した実 Cloudflare R2 read/write/delete 検証が成功しているが、本番アプリからの実 upload/download は行っていない。

旧データで `plan_files.disk` が null の場合は `FILESYSTEM_LEGACY_UPLOADS_DISK`（既定 `public`）を使う。環境により既存ファイルの保存先が異なる場合は、R2 切替前にこの変数を従来の disk 名へ合わせる。既存ファイルのコピー・backfill は行わず、DB migration は nullable disk 列の追加のみ。

## 運用上の未確認事項

移行の変更は `codex/next-typescript-migration` ブランチに保存した。Google OAuth client と callback は設定済みで、公開確認では Google sign-in 画面まで到達した。実 Google sign-in/consent/callback、ログイン済み公開 UI、本番アプリからの R2 添付操作は未確認。実 R2 に対する isolated upload/download/restart/delete は成功し、本番の `FILESYSTEM_UPLOADS_DISK=r2` 設定後も公開 smoke は read-only で実施した。新しい空DBに旧DBのデータはなく、旧DBは変更していない。AWS 設定は変更していない。
