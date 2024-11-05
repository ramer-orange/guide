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

    <form action="{{ route('itineraries.update', $overview->id) }}" method="post">
        @csrf
        @method('PUT')
        <div>
            <label for="title">タイトル</label>
            <input type="text" id="title" name="title" value="{{ old('title', $overview->title) }}">
            @error('title')
            <div class="alert alert-danger">{{ $message }}</div>
            @enderror
        </div>
        <div>
            <label for="overview">旅行概要</label>
            <textarea id="overview" name="overview">{{ old('overview', $overview->overview) }}</textarea>
            @error('overview')
            <div class="alert alert-danger">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit">更新する</button>
    </form>

    <a href="{{ route('itineraries.index') }}">一覧を見る</a>
</main>
</body>
</html>

