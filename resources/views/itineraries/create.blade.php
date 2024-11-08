<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>しおり作成ページ</title>
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
    <h1>しおりを作成</h1>

    <form action="{{ route('itineraries.store') }}" method="post">
        @csrf
        <div class="overview">
            <div>
                <label for="title">タイトル</label>
                <input type="text" id="title" name="title" value="{{ old('title') }}">
                @error('title')
                <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>
            <div>
                <label for="overview">旅行概要</label>
                <textarea id="overview" name="overview">{{ old('overview') }}</textarea>
                @error('overview')
                <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>
        </div>
        <div class="plans">
            <div>
                <label for="date">日付</label>
                <input type="date" id="date" name="date" value="{{ old('date') }}">
                @error('date')
                <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>
            <div>
                <label for="time">時間</label>
                <input type="time" id="time" name="time" value="{{ old('time') }}">
                @error('time')
                <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>
            <div>
                <label for="plans_title">タイトル</label>
                <input type="text" id="plans_title" name="plans_title" value="{{ old('plans_title') }}">
                @error('plans_title')
                <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>
            <div>
                <label for="content">内容</label>
                <textarea id="content" name="content">{{ old('content') }}</textarea>
                @error('content')
                <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <button type="submit">作成する</button>
    </form>
    <a href="{{ route('itineraries.index') }}">一覧を見る</a>
</main>
</body>
</html>
