<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>編集ページ</title>
</head>
<body>
<header>
    <form method="POST" action="{{ route('logout') }}">
        @csrf

        <x-dropdown-link :href="route('logout')"
                         onclick="event.preventDefault();
                                                this.closest('form').submit();">
            {{ __('Log Out') }}
        </x-dropdown-link>
    </form>
</header>
<main>
    <h1>しおりを編集</h1>

    <livewire:edit-plans-form :overview="$overview"/>

    <a href="{{ route('itineraries.index') }}">一覧を見る</a>
</main>
</body>
</html>

