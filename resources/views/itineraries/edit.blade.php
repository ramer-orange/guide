<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>編集ページ</title>

    @livewireStyles

    @vite(['resources/js/app.js', 'resources/js/hamburger.js', 'resources/css/app.css'])

    @livewireScripts
</head>
<body class="bg-[#fffdfa]">
<!-- ヘッダー -->
<x-header></x-header>

<main>
    <div class="max-w-7xl mx-auto p-4 pb-8 sm:p-6 sm:pb-12 dark:bg-gray-800 min-h-screen">
        <!-- 一覧を見るボタン -->
        <x-button.toIndex-button>しおり一覧を見る</x-button.toIndex-button>
        <div class="flex justify-center mt-8">
            <div class="max-w-3xl w-full">
                <h1 class="text-3xl sm:text-4xl font-extrabold mb-6 text-gray-900 text-center">しおり編集</h1>

                <livewire:edit-plans-form :overview="$overview"/>
            </div>
        </div>
    </div>
</main>
</body>
</html>

