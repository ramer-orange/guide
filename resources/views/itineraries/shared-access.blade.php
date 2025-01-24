<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>しおり作成ページ</title>
    @livewireStyles

    @vite(['resources/js/app.js', 'resources/js/hamburger.js', 'resources/css/app.css'])

    @livewireScripts
</head>
<body class="bg-[#fffdfa]">
<!-- ヘッダー -->
<x-header></x-header>
<main>
    <div class="max-w-4xl mx-auto">
        <div class="min-h-screen flex items-center justify-center pl-4 pr-4">
            <div class="bg-white rounded-lg shadow-md p-8 w-full max-w-xl">
                <h2 class="text-xl font-semibold text-gray-700 text-center">共有パスワード入力</h2>
                <p class="text-gray-600 mt-4 text-center">このページを閲覧するには、共有パスワードを入力してください。</p>
                <a class="text-blue-600 decoration-2 hover:underline focus:outline-hidden focus:underline font-medium dark:text-blue-500 mt-4 block text-right"
                   href="{{ route('login') }}">
                    ログインへ
                </a>

                <!-- パスワード入力フォーム -->
                <form method="POST" action="{{ route('shared-access.verify', $travelOverview->id) }}">
                    @csrf
                    <div class="mt-4">
                        <label for="shared_password"
                               class="block text-sm font-medium text-gray-700">共有パスワード</label>
                        <input type="password" id="shared_password" name="shared_password"
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        @error('shared_password')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="mt-6 flex justify-center">
                        <x-button.button2>
                            送信する
                        </x-button.button2>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
