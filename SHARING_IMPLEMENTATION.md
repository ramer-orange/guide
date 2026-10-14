# Sharing Implementation

## 目的

しおり共有の動線を、次の 2 種類に分けて実装する。

- メールアドレスで追加されたユーザー: 共同編集者
- 閲覧用パスワードでアクセスしたユーザー: 閲覧者

これにより、編集権限を持つユーザーを `travel_members` で明確に管理しつつ、URL と閲覧用パスワードだけで見せる相手には編集させない。

## 権限設計

| 種別 | 条件 | 閲覧 | 編集 | メンバー管理 | 削除 | 閲覧用パスワード変更 |
| --- | --- | --- | --- | --- | --- | --- |
| 作成者 | `travel_overviews.user_id = auth()->id()` | 可 | 可 | 可 | 可 | 可 |
| 共同編集者 | `travel_members` に `user_id` が存在 | 可 | 可 | 不可 | 不可 | 不可 |
| 閲覧用共有の閲覧者 | `access_granted_{travel_id}` セッションあり | 可 | 不可 | 不可 | 不可 | 不可 |
| その他 | 上記以外 | 不可 | 不可 | 不可 | 不可 | 不可 |

## 主な動線

### 作成者が共同編集者を追加する

1. 作成者がしおり編集画面を開く。
2. 旅行メンバー欄に登録済みユーザーのメールアドレスを入力する。
3. `travel_members` に `role = member` として追加する。
4. 追加されたユーザーのしおり一覧にも、そのしおりが表示される。

### 共同編集者が編集する

1. 共同編集者がログインする。
2. しおり一覧に共有されたしおりが表示される。
3. 編集画面でしおり内容を編集できる。
4. ただし、メンバー管理・しおり削除・閲覧用パスワード変更はできない。

### 閲覧用パスワードで閲覧する

1. ユーザーが編集 URL に直接アクセスする。
2. 権限がなく、有効な閲覧用パスワードが設定されている場合は `/shared-access` にリダイレクトされる。
3. 閲覧用パスワードが一致すると、`access_granted_{travel_id}` セッションを保存する。
4. 編集フォームではなく閲覧専用画面に遷移する。
5. Livewire の `submit()` 側でも編集権限を再チェックし、閲覧者の保存は `403` にする。

閲覧用共有には、任意の有効期限と無効化操作を用意している。

## 実装ファイル

### `app/Policies/TravelOverviewPolicy.php`

- `view`, `update`, `manageMembers`, `manageViewerShare`, `delete` を定義。
- 作成者と共同編集者は編集可能。
- `access_granted_{travel_id}` セッションを持つユーザーは閲覧のみ可能。
- 認可ロジックをコントローラや Livewire から切り離す。

### `app/Http/Controllers/ItinerariesController.php`

- 一覧取得を、作成者または `travel_members` に含まれるしおりに変更。
- 編集画面アクセス時に `TravelOverviewPolicy` で閲覧・編集可否を判定。
- 権限がなく、有効な閲覧用パスワードが設定されている場合は `shared-access.show` へリダイレクト。
- 閲覧のみの場合は `itineraries.show` を返す。
- 作成者のみ、メンバー追加・削除・しおり削除を許可。

### `app/Livewire/EditPlansForm.php`

- `isOwner` と `canEdit` を持たせた。
- `mount()` で `TravelOverviewPolicy` による閲覧可否を確認。
- `submit()` では `update` 権限を再確認し、閲覧者の保存を拒否。
- 閲覧用パスワード変更は、作成者かつパスワード入力欄を開いた場合だけ更新。
- 閲覧用共有の有効期限設定と無効化に対応。
- 持ち物リストはログインユーザーごとに分離。

### `app/Models/SharedPassword.php`

- `expires_at`, `disabled_at` を持つ。
- `isActive()` で、パスワードあり・無効化されていない・期限切れでない状態を判定。

### `resources/views/itineraries/index.blade.php`

- 共同編集者にも共有されたしおりを表示。
- 共同編集者には削除ボタンを表示しない。
- 共同編集者のしおりには「共有されたしおり」ラベルを表示。

### `resources/views/itineraries/edit.blade.php`

- 作成者だけに旅行メンバー管理 UI を表示。

### `resources/views/itineraries/show.blade.php`

- 閲覧用共有向けの専用画面。
- 編集フォームを表示せず、タイトル・概要・プラン・添付ファイル・お土産・メモを閲覧専用で表示。

### `resources/views/livewire/edit-plans-form.blade.php`

- 閲覧用共有 UI は作成者だけに表示。
- 閲覧用パスワードの変更、有効期限設定、無効化ボタンを提供。

### `routes/web.php`

- `itineraries.edit` から `auth` middleware を外した。
- 認可は `ItinerariesController::edit()` と `EditPlansForm::mount()` で行う。
- これにより、未ログインでも閲覧用パスワード認証済みなら閲覧画面に入れる。

### `database/migrations/2026_05_16_000001_create_travel_members_table.php`

- `travel_members` を追加。
- `travel_id`, `user_id`, `role` を管理。
- `travel_id` と `user_id` の組み合わせは unique。
- 既存の `travel_overviews.user_id` は `owner` として backfill。

### `database/migrations/2026_05_16_000002_add_viewer_share_controls_to_shared_passwords_table.php`

- `shared_passwords.expires_at` を追加。
- `shared_passwords.disabled_at` を追加。
- 閲覧用共有の期限切れと無効化を表現する。

### `tests/Feature/TravelMemberAccessTest.php`

以下をテストしている。

- 共同編集者のしおり一覧に共有しおりが表示される。
- 共同編集者には削除導線が出ない。
- 共同編集者は閲覧用パスワードを変更できない。
- 閲覧用共有の閲覧者は閲覧できるが編集できない。
- 編集 URL 直アクセス時、有効な閲覧用パスワードがある場合は入力画面へリダイレクトされる。

## 確認コマンド

```bash
docker compose exec -T laravel.test php artisan migrate
docker compose exec -T laravel.test php artisan optimize:clear
docker compose exec -T laravel.test php artisan test tests/Feature/TravelMemberAccessTest.php
```

期待結果:

```text
4 passed
```

## 現時点の方針

閲覧用パスワードは「共同編集」ではなく「閲覧共有」として扱う。

編集したいユーザーは必ず登録済みアカウントとしてメールアドレスで追加する。これにより、誰が編集できるかを `travel_members` で追跡でき、閲覧用パスワードを知っているだけのユーザーが編集できる状態を避ける。

## 今後の注意点

- `itineraries.edit` は `auth` middleware では守られていないため、コントローラと Livewire 側の認可チェックを消さない。
- UI を非表示・disabled にするだけでは不十分なので、保存処理側の `canEdit` チェックを必ず維持する。
- 閲覧用共有の閲覧者にチェックリスト操作など一部編集を許可したい場合は、`viewer` 用の権限を別途定義する。
- ゲスト閲覧をさらに強める場合は、共有リンク用トークン、アクセスログ、閲覧者別の取り消しを追加する。
