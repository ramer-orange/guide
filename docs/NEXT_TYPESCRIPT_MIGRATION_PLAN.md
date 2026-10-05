# Next.js + TypeScript フロントエンド移行プラン

作成日: 2026-10-05。初版は実装前の設計案。実装状況は末尾の「現時点の実装と検証」を参照。本番の稼働状態は未確認。

## 1. 方針と現状

Laravel をバックエンドとして維持し、同じリポジトリの `frontend/` に独立した Next.js アプリを追加する。Next.js App Router と TypeScript の strict モードを採用する。

現在は Laravel 11、Blade、Livewire 3、Tailwind CSS、Vite の構成。`routes/api.php` はなく、作成・編集の保存処理は `app/Livewire/PlansForm.php` と `EditPlansForm.php` にある。フロントの移行には JSON API の追加と Livewire からの保存処理の切り出しが必要。

Laravel は DB、Eloquent、Google 認証、セッション、Policy、入力検証、ファイル保存を担当する。Next.js はページ、フォーム、並び替え、表示状態、API 呼び出しを担当する。既存 DB を利用し、フロント移行のためのテーブル再作成は行わない。

Google ログインは Socialite を継続する。作成者と編集メンバーの共同編集、パスワードによる閲覧共有、ユーザーごとの持ち物リストを維持する。現在の共同編集は複数ユーザーに編集権限を与える仕組みで、リアルタイム同期を移行範囲に追加しない。

## 2. ディレクトリ構造

既存 Laravel のルート構造を保つ。バックエンドの移動とフロントの書き換えを同時に行わず、Next.js の依存とビルドを `frontend/` 内に閉じる。

```text
guide/
├── app/                              # 既存 Laravel
│   ├── Actions/Itineraries/           # 新設: 作成・更新・共有の保存処理
│   ├── Http/
│   │   ├── Controllers/Api/V1/        # 新設: JSON API
│   │   ├── Requests/Api/V1/           # 新設: API 入力検証
│   │   └── Resources/                 # 新設: JSON 出力と公開する項目
│   ├── Models/
│   ├── Policies/
│   └── Livewire/                      # 移行中は維持
├── routes/
│   ├── web.php                        # OAuth・ログアウト・移行前の画面
│   └── api.php                        # 新設: /api/v1/*
├── database/
├── resources/                        # 移行中の Blade/Vite
├── frontend/
│   ├── src/
│   │   ├── app/
│   │   │   ├── layout.tsx
│   │   │   ├── providers.tsx          # QueryClientProvider 等
│   │   │   ├── globals.css
│   │   │   ├── error.tsx
│   │   │   ├── not-found.tsx
│   │   │   ├── (public)/
│   │   │   │   ├── page.tsx           # /
│   │   │   │   ├── policy/page.tsx
│   │   │   │   └── terms/page.tsx
│   │   │   └── itineraries/
│   │   │       ├── index/page.tsx     # /itineraries/index
│   │   │       ├── create/page.tsx
│   │   │       └── [id]/
│   │   │           ├── edit/page.tsx  # 権限に応じ編集または閲覧
│   │   │           └── shared-access/page.tsx
│   │   ├── features/
│   │   │   ├── auth/                 # ログイン状態・ログアウト
│   │   │   └── itineraries/
│   │   │       ├── api/              # HTTP 関数・query keys/options
│   │   │       ├── hooks/            # 取得・更新・フォームの hooks
│   │   │       ├── components/       # 一覧・編集・閲覧 UI
│   │   │       │   └── editor/       # 日程・持ち物・お土産・メモ
│   │   │       ├── shared/           # 旅行機能内で共有する部品
│   │   │       ├── schemas/          # Zod: フォーム検証
│   │   │       └── utils/            # DTO 変換・multipart 組み立て
│   │   ├── components/
│   │   │   ├── ui/                   # shadcn/ui の部品
│   │   │   └── layout/               # ヘッダー・フッター
│   │   ├── lib/
│   │   │   ├── api/                  # HTTP client・共通エラー処理
│   │   │   └── utils.ts
│   │   └── types/api.generated.ts    # OpenAPI から生成
│   ├── public/                       # 画像・favicon 等
│   ├── e2e/
│   ├── next.config.ts
│   ├── tsconfig.json
│   ├── package.json
│   ├── package-lock.json
│   └── Dockerfile
├── docs/
│   ├── api/openapi.yaml              # 新設: API 契約
│   └── NEXT_TYPESCRIPT_MIGRATION_PLAN.md
├── tests/Feature/                    # Laravel/Pest の API・権限テスト
├── nginx/
└── infra/                            # 既存インフラ
```

`app/` は URL とページ構成に集中させ、業務ロジックや大きなフォームを `features/` に置く。機能専用コンポーネントを全体共通の `components/` に集めない。ユニットテストは対象ファイルに隣接して置く。

### Next.js 標準との関係

`src/app`、`page.tsx`、`layout.tsx`、`error.tsx`、`not-found.tsx`、`[id]`、route group は公式の規約を使う。`features/`、`hooks/`、`components/` の配置はプロジェクトの設計方針であり、Next.js が指定する唯一の標準構造ではない。公式は `app/` の外へ機能を置く構成と、機能やルート単位で分ける構成を認めている。

`/itineraries/index` は既存 URL の互換性を優先した配置。新規設計なら `/itineraries` に一覧を置く構成が自然だが、URL 整理は別の変更として扱う。

### ファイル分割と依存方向

依存方向を以下に統一する。

```text
app（ルート） → features（旅行・認証） → components / lib / types
```

- `page.tsx` は params/searchParams の解釈、metadata、機能コンポーネントの配置を担当する。フォームや multipart の組み立てを持たせない。現行 Next.js の非同期 params/searchParams は await する。
- `features/*/api/` は HTTP 関数・query keys/options を担当し、JSX を持たない。`hooks/` は API の取得・更新と UI の接続を担当する。
- `schemas/` はフォーム検証、`utils/` は DTO 変換や送信 payload の構築を担当する。これらの純粋関数に React、画面遷移、通知を混ぜない。
- `components/` は表示・利用者の操作を担当する。行コンポーネントに HTTP 通信や旅行全体の保存を入れない。
- 共通の `components/` と `lib/` から `features/` や `app/` を import しない。認証への依存はセッション hook 等の限定した入口へ集約する。
- 他のページの内部コンポーネントを直接 import しない。再利用するものは機能内の `shared/` または全体共通へ置く。
- `lib/utils.ts` は shadcn/ui の className 結合等に用途を限定する。日付、API、ファイル、フォーム変換を一つの utils ファイルへ集めない。
- ファイル名は `itinerary-editor.tsx`、`use-save-itinerary.ts`、`to-itinerary-form-values.ts` 等、役割が分かる名前にする。部品は定義ファイルから直接 import し、大きな一括再 export を作らない。

1 ファイル 1 つの主要な責務を基本とする。手書きの TS/TSX は 200 行前後を分割検討の目安とし、300 行を超える場合は責務・分岐・状態の集中をレビューする。この数値はプロジェクトの目安で、公式の制限ではない。生成された API 型・shadcn/ui の原本・静的データは別に判断する。小さくするためだけの分割はせず、意味のあるまとまりで分ける。

### 作成・編集フォームの具体的な分割

```text
features/itineraries/
├── components/
│   ├── itinerary-create.tsx              # 作成時の初期値・保存の接続
│   ├── itinerary-edit.tsx                # 詳細取得・権限・更新の接続
│   ├── itinerary-view.tsx                # 閲覧用表示
│   ├── editor/
│   │   ├── itinerary-editor.tsx          # FormProvider と section の配置
│   │   ├── overview-fields.tsx
│   │   ├── plans-section.tsx
│   │   ├── plan-row.tsx
│   │   ├── packing-section.tsx
│   │   ├── packing-row.tsx
│   │   ├── souvenirs-section.tsx
│   │   ├── souvenir-row.tsx
│   │   ├── notes-section.tsx
│   │   ├── note-row.tsx
│   │   ├── plan-attachments.tsx
│   │   └── editor-actions.tsx
│   └── sharing/                          # メンバー・閲覧共有の管理 UI
├── shared/
│   ├── sortable-list.tsx                 # 並び替えの共通処理
│   ├── sortable-item.tsx                 # 行の操作・ドラッグハンドル
│   └── itinerary-section.tsx             # 見出し・説明・操作の配置
├── hooks/
│   ├── use-itinerary.ts
│   ├── use-save-itinerary.ts
│   └── use-packing-templates.ts
├── schemas/
│   ├── itinerary-form.schema.ts
│   └── viewer-share.schema.ts
└── utils/
    ├── to-itinerary-form-values.ts
    ├── to-itinerary-form-data.ts
    └── itinerary-form-defaults.ts
```

作成と編集は同じ `itinerary-editor`、入力スキーマ、行コンポーネント、payload 変換を使う。初期値の取得と保存先を各入口で接続し、フォーム内部へ `isCreate`、`isEdit`、`isReadonly` などの分岐を増やさない。閲覧画面は専用コンポーネントにし、共通にできる見出し・添付表示等を再利用する。

`itinerary-editor` は各 section を組み立てる役割に留め、すべての行の JSX や全フィールドの監視を持たせない。`useFormContext` を使い、`useWatch` / `useFormState` の購読を必要な section や行に絞る。一つの巨大な `useItineraryEditor` hook に全責務を移す設計も避ける。

日程・持ち物・お土産・メモはそれぞれ専用の行 UI を作り、並び替えのセンサー、ドラッグハンドル、移動の通知を共通化する。`type=plan|packing|souvenir|note` の条件分岐を持つ万能な行コンポーネントにはまとめない。セクション枠は children と操作スロットで組み合わせる。

### 共通化する範囲

| 範囲 | 共通化するもの | 配置 |
| --- | --- | --- |
| 全画面 | Button、Dialog、フォームエラー、読み込み・空状態、ヘッダー・フッター | `components/` |
| API 全体 | Axios 設定、CSRF 初期化、401/403/419/422 のエラー正規化 | `lib/api/` |
| 旅行機能内 | セクション枠、並び替え、添付の表示、作成・編集フォーム | `features/itineraries/` |
| データ契約 | API DTO は OpenAPI 生成型、フォーム型は Zod から推論 | `types/` と機能内 `schemas/` |
| バックエンド | Livewire/API の保存 Action、Policy、入力検証の共通ルール | Laravel の該当責務ごとのクラス |

実際に同じ振る舞いを持つ箇所を共通化する。業務上の意味・検証・権限が異なる部分は専用部品で表現し、変更のたびに分岐が増える抽象化を作らない。まだ用途がない共通フォルダや汎用 hook は作らない。

### 実装レビューの基準

- ルート・表示・API 通信・データ変換・検証がそれぞれの責務へ分かれている。
- 作成と編集のフォームや API 型が重複していない。
- 共通モジュールから機能モジュールへの逆向き依存、循環依存がない。import の境界を ESLint のルールで検査する。
- root layout は Server Component を維持し、QueryProvider や認証メニュー等の操作部分だけを Client Component にする。Provider のためにアプリ全体へ `use client` を付けない。
- ブラウザ取得する API の pending/error は Query の状態として表示する。Next.js の `loading.tsx` は Server Component の待機用であり、ブラウザ取得の loading 表示を代替しない。
- 別々に取得できる API を不要に直列化せず、静的画面にフォームや DnD の依存を読み込ませない。
- 型検査・lint・ビルドと、フォーム変換・並び替え・保存の必要なテストで検証する。

パッケージ管理は既存に合わせ npm を使う。移行中はルートの Vite と `frontend/` の Next.js を別々に起動し、`npm --prefix frontend run dev` 等で操作する。Turborepo は導入しない。

## 3. 採用ライブラリ

| 用途 | 採用案 | このアプリでの役割 |
| --- | --- | --- |
| フレームワーク | Next.js App Router + React | ページ・レイアウト・画面遷移 |
| 言語 | TypeScript / strict | API と UI の型検査 |
| CSS | Tailwind CSS 4 + @tailwindcss/postcss | 既存デザインを移植。現在の beta 依存をそのままコピーしない |
| UI 部品 | shadcn/ui | Dialog、AlertDialog、Button、Checkbox 等、必要な部品のみ |
| フォーム | react-hook-form | 多数の入力、動的な行、dirty 状態 |
| フォーム検証 | zod + @hookform/resolvers | 入力検証とフォームの型 |
| API データ管理 | @tanstack/react-query | 取得、更新中表示、保存後の再取得 |
| HTTP 通信 | axios | CSRF ヘッダー、multipart、添付の送信進捗 |
| 並び替え | @dnd-kit/react | 日程・持ち物・お土産・メモの並び替え |
| アイコン | lucide-react | ボタン等の統一したアイコン |
| API 型生成 | openapi-typescript | OpenAPI の request/response を TS 型へ生成 |
| 静的検査・整形 | ESLint + eslint-config-next + Prettier | 型検査とは別に lint を実行 |
| ユニット・UI テスト | Vitest + Testing Library | フォームの変換と操作の検証 |
| E2E | Playwright | 実ブラウザで認証・作成・共有を検証 |
| Laravel API 認証 | laravel/sanctum | 既存セッションを利用する Cookie 認証 |

フレームワークとライブラリは導入時点の互換性を確認した安定版を lockfile で固定する。Next.js、React、dnd-kit は組み合わせを小さな並び替えフォームで確認してから本体に適用する。dnd-kit の新 API と旧 `@dnd-kit/core` / `@dnd-kit/sortable` のコード例を混在させない。

Redux/Zustand は初期導入しない。サーバーのデータは TanStack Query、未保存の入力は React Hook Form、モーダル等の状態は useState に置く。日付の基本表示は Intl と HTML の date/time 入力を使い、date-fns は日付計算が必要になった段階で検討する。

## 4. Next.js の表示・状態管理

- ホーム・規約・ポリシーとレイアウトは Server Components を基本とする。
- 一覧・閲覧・編集のセッション依存データは、初期段階ではブラウザから API を取得する。ページ全体を一律に Client Component にせず、データを扱う機能コンポーネントを境界にする。
- 保存や共有設定の更新もブラウザから Laravel API を呼ぶ。Next.js の Server Actions に業務処理を重複実装しない。
- TanStack Query の取得結果をフォームへ初期化し、編集中の再取得で入力を上書きしない。保存成功時に基準値を更新し、一覧・詳細キャッシュを無効化する。
- query key に旅行 ID と現在の利用者を含める。ログアウト・アカウント切り替え・共有の認証変更時には該当キャッシュを破棄する。
- 行の追加・削除・移動は useFieldArray で管理し、表示用の安定した ID と DB ID を分ける。並び替えには move を使い、添付やチェック状態も行に追従させる。
- 未保存変更、二重送信、422 エラーの該当入力表示、通信失敗後の入力維持、401/419 時のセッション切れを扱う。保存の自動再送はしない。
- 個人データや閲覧共有のレスポンスは共有キャッシュに載せない。将来 SSR を追加する場合は Cookie の転送とリクエスト単位のデータ管理を別途実装する。

## 5. 認証と配信経路

利用者から見たオリジンを一つにする。

```text
ブラウザ → 共通入口（Nginx 等）
             ├── /api/v1/*           → Laravel
             ├── /auth/google*      → Laravel / Socialite
             ├── /login, /register  → Laravel
             ├── /logout            → Laravel
             ├── /sanctum/*         → Laravel
             ├── /up                → Laravel health check
             ├── 添付取得用の経路    → Laravel / Storage
             ├── /_next/*           → Next.js
             └── 移行済み画面        → Next.js
```

移行中は、移行していない画面と `/livewire/*`、旧 Vite の `/build/*` を Laravel へ送る。画面を移すたびに URL 単位で配信先を切り替え、問題時は同じ URL を旧画面へ戻せるようにする。画像等の共通アセットは同じ入口で参照できる配置にする。

Google OAuth の callback と HttpOnly セッション Cookie は Laravel が管理する。API 利用の前に `/sanctum/csrf-cookie` を取得し、更新 API に XSRF ヘッダーを付ける。Sanctum の stateful API 設定、許可する origin、Cookie の Domain/Path/Secure、入口での Host・Origin・Set-Cookie の扱いを開発・本番の両方で検証する。

閲覧共有の API は、未ログインの利用者にもセッションを開始できる構成にする。全 API に `auth:sanctum` を一律適用せず、共有パスワード検証と Policy による閲覧を別グループにする。閲覧許可は既存 `SharedAccess` の共有レコード ID・バージョン・期限・失効判定を利用する。

認証を Auth.js に移し替える必要はない。Laravel が最終的な認証・認可を判断し、API の `permissions` は UI の表示判断に使う。

## 6. API の切り出し

初期 API 案。詳細な項目・エラーコード・null と空文字の扱いは `docs/api/openapi.yaml` に定義する。

| Method | URL | 役割 |
| --- | --- | --- |
| GET | /api/v1/session | 利用者・OAuth 失敗通知等の状態。ゲストも利用可能 |
| GET | /api/v1/itineraries | 作成者またはメンバーとして参加する旅行一覧 |
| POST | /api/v1/itineraries | 旅行の作成 |
| GET | /api/v1/itineraries/{id} | Policy による編集者または共有閲覧者向けデータ |
| PUT | /api/v1/itineraries/{id} | 概要・日程・本人の持ち物・お土産・メモの保存 |
| DELETE | /api/v1/itineraries/{id} | 作成者による削除 |
| GET | /api/v1/packing-templates | 既存の持ち物テンプレート |
| POST | /api/v1/itineraries/{id}/members | 作成者による登録済み利用者の追加 |
| DELETE | /api/v1/itineraries/{id}/members/{memberId} | 作成者によるメンバー削除 |
| GET | /api/v1/itineraries/{id}/shared-access | 共有入力画面に必要な最小情報 |
| POST | /api/v1/itineraries/{id}/shared-access | 閲覧パスワード検証 |
| PUT | /api/v1/itineraries/{id}/viewer-share | 作成者による閲覧パスワード・期限の設定 |
| DELETE | /api/v1/itineraries/{id}/viewer-share | 作成者による閲覧共有停止 |
| GET | /api/v1/itineraries/{id}/files/{fileId} | 認可後のファイル取得または期限付き URL 発行 |

保存は既存の「保存ボタンでまとめて更新する」操作を維持する。最初から日程の各入力を個別 API へ分割しない。共有設定・メンバー操作は権限が異なるため別 API にする。

添付を含む作成は multipart POST、更新は multipart POST に `_method=PUT` を付ける。既存添付 ID、新規 File、削除対象 ID を区別する。初期段階では Livewire の一時アップロードを置き換え、Laravel へ保存時に送信する。現在の許可形式・1 ファイル 10 MB 制限を維持し、全体の送信サイズも入口と PHP の設定に合わせて扱う。

Livewire の保存処理を Action へ抽出し、旧画面と新 API から共通利用する。新 API は入力 ID が対象旅行に属することを検証し、本人の持ち物だけを取得・更新・削除する。DB 更新にはトランザクションを用い、ファイル保存失敗時の新規ファイル清掃と DB 確定後の旧ファイル削除を設計する。

レスポンスには `can_edit`、`can_delete`、`can_manage_members`、`can_manage_viewer_share` 等を含め、共有パスワードのハッシュや内部ストレージパスを公開しない。ユーザーごとの持ち物と共有される日程・お土産・メモを明示的に区別する。共有閲覧者へ本人以外の持ち物を返さない。

FormRequest と API Resource を契約に合わせて作り、Zod は操作時の早いフィードバックに用いる。Laravel の検証を最終判断とする。現在の SubmitFormRequest とフォームで項目名が一致していないお土産の項目等は、API 契約の作成時に整える。

## 7. 移行手順と完了条件

### Phase 1: API 契約と共通保存処理

権限と既存仕様を一覧化し、OpenAPI、Action、FormRequest、API Resource を整備する。旧 Livewire から Action を呼び、まず既存画面の挙動が保たれることを確認する。

完了条件: 作成者・メンバー・ゲストの API 契約が定まり、既存 Laravel/Pest の認証・共有・持ち物テストが通る。

### Phase 2: Next.js 基盤と認証の疎通

`frontend/` を作成し、共通 UI、画像、レイアウト、エラー表示、HTTP client、TanStack Query を整える。開発用の共通入口を追加し、Google ログイン・セッション取得・ログアウト・ゲスト共有・CSRF を接続する。

完了条件: 再読み込みでも利用者と共有閲覧のセッションが保たれ、セッション切れと OAuth エラーが表示できる。

### Phase 3: ホーム・一覧・閲覧

ホーム・規約・ポリシー、旅行一覧、権限に応じた閲覧画面を移植する。既存 URL と旅行 UUID を維持する。

完了条件: 作成者と参加メンバーの旅行が一覧に出て、共有閲覧者が編集ボタンを持たず、直接 URL でも権限が保たれる。

### Phase 4: 作成・編集

React Hook Form に概要・日程・持ち物・お土産・メモを移し、行の追加・削除・テンプレート・並び替えを接続する。添付を含む保存処理を実装する。

完了条件: 作成→編集→並び替え→保存→再読み込みで内容・順序・添付が一致し、別メンバーの持ち物が変更されない。

### Phase 5: 共有・メンバー管理の完成

メンバー追加・削除、閲覧共有の設定・停止・再発行、期限切れ、共有 URL のコピーとブラウザ共有を移す。共有パスワード検証は Phase 2 から疎通し、この段階で管理 UI を完成させる。

完了条件: 作成者だけが管理でき、共有の停止・パスワード変更・期限切れにより既存閲覧セッションが無効になる。既存の試行制限も保たれる。

### Phase 6: 配信切り替えと旧フロント撤去

Docker/入口設定と CI に Next.js を追加する。全画面を確認して URL ごとの配信先を切り替える。切り戻し期間を設けた後、実際に未使用になった Blade、Livewire、旧 JavaScript、Vite と依存を撤去する。

設定上は Render 向け `render.yaml` と PHP コンテナがあり、AWS のデプロイは停止中。Next.js は独立した Node サービスとして実行し、既存入口から振り分ける案とする。Render 等の実際の稼働先・サービス構成は実装開始時に確認し、フロント移行のために AWS を再開しない。

完了条件: Next.js の型検査・lint・ビルド、Laravel の既存/API テスト、主要 E2E が通り、移行前 URL と OAuth callback と既存添付が利用できる。

## 8. 検証の重点

- 作成者、編集メンバー、共有閲覧者、無関係なログイン利用者の権限。
- ユーザーごとの持ち物と、別旅行の ID を含む更新・削除の拒否。
- 行の並び替え後も入力、チェック、添付、削除対象が対応すること。
- 保存成功後の再読み込みと、422/通信失敗時の入力維持。
- 共有停止・期限切れ・パスワード変更・旧共有レコードの失効と試行制限。
- ファイルの追加・既存添付取得・削除・形式/サイズ拒否。
- Cookie/CSRF、ログアウト後のキャッシュ破棄とブラウザの戻る操作。
- スマートフォンでのフォーム、タッチ並び替え、キーボード操作。

OAuth は自動テストでは Socialite をモックし、実環境で callback URL の疎通を確認する。現行の Livewire テストは Action/API テストへ段階的に移す。

## 9. 参考資料

- [Next.js Project Structure](https://nextjs.org/docs/app/getting-started/project-structure)
- [Next.js Server and Client Components](https://nextjs.org/docs/app/getting-started/server-and-client-components)
- [Laravel 11 Sanctum](https://laravel.com/docs/11.x/sanctum)
- [Tailwind CSS / Next.js](https://tailwindcss.com/docs/installation/framework-guides/nextjs)
- [shadcn/ui / Next.js](https://ui.shadcn.com/docs/installation/next)
- [React Hook Form useFieldArray](https://react-hook-form.com/docs/usefieldarray)
- [TanStack Query / Advanced Server Rendering](https://tanstack.com/query/latest/docs/framework/react/guides/advanced-ssr)
- [dnd-kit React Quickstart](https://dndkit.com/react/quickstart/)
- [OpenAPI TypeScript](https://openapi-ts.dev/readme)

## 10. 現時点の実装と検証

この文書は移行設計と段階案を示す。実装済み範囲、起動/切り戻し手順、検証結果、未検証の本番作業は [実装状況](NEXT_TYPESCRIPT_IMPLEMENTATION_STATUS.md) を参照。
