<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>編集ページ</title>

    @vite('resources/css/app.css')
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
    <div class="flex justify-center">
        <div class="max-w-2xl w-full">
            <h1 class="font-bold mt-4 text-2xl text-center">しおりを編集</h1>

            <livewire:edit-plans-form :overview="$overview"/>

            <a href="{{ route('itineraries.index') }}" class="mt-8 border border-black bg-slate-200 inline-block">一覧を見る</a>
        </div>
    </div>
</main>
</body>
</html>

