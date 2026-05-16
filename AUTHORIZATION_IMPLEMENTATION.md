# Authentication and Authorization Implementation

## 概要

しおり共有機能では、認証と認可を分けて設計している。

- 認証: ユーザーが誰かを確認する
- 認可: そのユーザーが、対象のしおりに対して何をできるかを判定する

この実装では、ログインユーザーだけでなく、閲覧用パスワードでアクセスした未ログインユーザーも存在する。そのため、単純に `auth` middleware だけで守るのではなく、しおり単位の権限を `TravelOverviewPolicy` で判定している。

## 登場するユーザー種別

| 種別 | 判定条件 | できること |
| --- | --- | --- |
| 作成者 | `travel_overviews.user_id = auth()->id()` | 閲覧、編集、削除、メンバー管理、閲覧用共有の管理 |
| 共同編集者 | `travel_members.user_id = auth()->id()` | 閲覧、編集 |
| 閲覧者 | `access_granted_{travel_id}` セッションあり | 閲覧のみ |
| その他 | 上記以外 | アクセス不可 |

## 認証

### 通常ログイン

作成者と共同編集者は Laravel の通常ログインを使う。

ログイン済みユーザーは `auth()->id()` によって識別される。

```php
auth()->id()
```

### 閲覧用パスワード認証

閲覧用共有では、ログインではなく、しおりごとの閲覧用パスワードを使う。

`SharedPasswordController::verify()` で入力されたパスワードを検証する。

```php
Hash::check($request->input('shared_password'), $overview->sharedPasswords->shared_password)
```

一致した場合、セッションに閲覧許可を保存する。

```php
session()->put("access_granted_$id", true);
```

このセッションがある間だけ、そのしおりを閲覧できる。

## 認可

認可は `app/Policies/TravelOverviewPolicy.php` に集約している。

### `view`

しおりを閲覧できるかを判定する。

閲覧できるのは次のいずれか。

- 作成者
- 共同編集者
- 閲覧用パスワード認証済みの閲覧者

```php
public function view(?User $user, TravelOverview $overview): bool
{
    return $this->update($user, $overview)
        || session()->has("access_granted_{$overview->id}");
}
```

### `update`

しおりを編集できるかを判定する。

編集できるのは次のいずれか。

- 作成者
- 共同編集者

閲覧用パスワードでアクセスしたユーザーは編集できない。

```php
public function update(?User $user, TravelOverview $overview): bool
{
    return $this->own($user, $overview)
        || $this->collaborate($user, $overview);
}
```

### `manageMembers`

旅行メンバーを追加・削除できるかを判定する。

作成者だけが許可される。

```php
public function manageMembers(?User $user, TravelOverview $overview): bool
{
    return $this->own($user, $overview);
}
```

### `manageViewerShare`

閲覧用パスワードを設定・変更・無効化できるかを判定する。

作成者だけが許可される。

```php
public function manageViewerShare(?User $user, TravelOverview $overview): bool
{
    return $this->own($user, $overview);
}
```

### `delete`

しおりを削除できるかを判定する。

作成者だけが許可される。

```php
public function delete(?User $user, TravelOverview $overview): bool
{
    return $this->own($user, $overview);
}
```

## アクセス時の流れ

### 1. しおり編集 URL にアクセス

対象ルート:

```php
GET /itineraries/{overview}/edit
```

このルートには `auth` middleware を付けていない。

理由は、閲覧用パスワードでアクセスする未ログインユーザーも存在するため。

ただし、認可チェックは `ItinerariesController::edit()` で必ず行う。

### 2. 閲覧権限を確認

```php
if (! Gate::allows('view', $overview)) {
    if ($this->hasSharedPassword($overview)) {
        return redirect()->route('shared-access.show', ['id' => $overview->id]);
    }

    abort(403);
}
```

処理は次の通り。

- 閲覧権限がある場合: 次へ進む
- 閲覧権限がなく、閲覧用パスワードが有効な場合: パスワード入力画面へリダイレクト
- 閲覧権限がなく、閲覧用パスワードもない場合: `403 Forbidden`

### 3. 編集権限を確認

閲覧できても、編集できるとは限らない。

```php
if (! Gate::allows('update', $overview)) {
    return view('itineraries.show', [
        'overview' => $overview,
    ]);
}
```

編集権限がない場合は、編集フォームではなく閲覧専用画面を表示する。

## Livewire 側の防御

UI 側で編集フォームを隠すだけでは不十分。

そのため、Livewire の保存処理でも `Gate::authorize('update', $overview)` を実行している。

```php
public function submit()
{
    Gate::authorize('update', $this->overview);

    $this->validate();

    // 保存処理
}
```

これにより、閲覧者がブラウザ側から Livewire リクエストを直接送っても保存できない。

## 閲覧用共有の有効状態

閲覧用共有は `shared_passwords` テーブルで管理する。

追加したカラム:

- `expires_at`: 有効期限
- `disabled_at`: 無効化日時

`SharedPassword::isActive()` で有効状態を判定する。

```php
public function isActive(): bool
{
    return $this->shared_password !== null
        && $this->disabled_at === null
        && ($this->expires_at === null || $this->expires_at->isFuture());
}
```

有効な条件:

- パスワードが設定されている
- 無効化されていない
- 有効期限が未設定、または未来日時

## なぜこの設計にしたか

### `auth` middleware だけでは足りない

今回の機能には、未ログインの閲覧者が存在する。

そのため、`auth` middleware を付けると、閲覧用パスワードで共有されたユーザーがアクセスできなくなる。

代わりに、ルートではなくしおり単位で `view` 権限を判定している。

### UI 非表示だけでは危険

ボタンを非表示にしても、直接リクエストを送れば保存処理を呼べる可能性がある。

そのため、以下の両方で守っている。

- 画面上は編集 UI を出さない
- サーバ側の保存処理でも `Gate::authorize()` を行う

### 共同編集と閲覧共有を分離した

共同編集者は `travel_members` に保存する。

閲覧者は `access_granted_{travel_id}` セッションで一時的に閲覧を許可する。

この分離により、次の違いを明確にできる。

- 誰が編集できるか
- 誰が閲覧だけできるか
- 誰をメンバーから外せるか
- 閲覧用共有をいつ無効化できるか

## まとめ

この実装では、認証済みユーザーと閲覧用パスワードユーザーを同じルートで扱いながら、`TravelOverviewPolicy` によって権限を分離している。

重要なポイントは次の 3 つ。

- 編集権限は `travel_members` を正本にする
- 閲覧用パスワードは閲覧だけに限定する
- UI とサーバ側の両方で認可を強制する
