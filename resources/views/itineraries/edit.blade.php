@extends('layouts.app')

@section('title')
    しおり編集 - {{ config('app.name') }}
@endsection

@section('meta')
    <!-- Primary Meta Tags -->
    <meta name="title" content="しおり編集 - config('app.name')">
    <meta name="description" content="config('app.name')のしおり編集ページです。旅行の日程、プラン、持ち物リスト、お土産リストなどを簡単に編集できます。ドラッグ&ドロップで順序の変更も可能です。">

    <!-- Open Graph / Facebook -->
    <meta property="og:title" content="しおり編集 - config('app.name')">
    <meta property="og:description" content="config('app.name')のしおり編集ページです。旅行の日程、プラン、持ち物リスト、お土産リストなどを簡単に編集できます。ドラッグ&ドロップで順序の変更も可能です。">

    <!-- Twitter -->
    <meta property="twitter:title" content="しおり編集 - config('app.name')">
    <meta property="twitter:description" content="config('app.name')のしおり編集ページです。旅行の日程、プラン、持ち物リスト、お土産リストなどを簡単に編集できます。ドラッグ&ドロップで順序の変更も可能です。">
@endsection

@section('content')
    <div class="bg-[#fffdfa]">
        <div class="max-w-7xl mx-auto p-4 pb-8 sm:p-6 sm:pb-12 dark:bg-gray-800 min-h-screen relative">
            @auth
                <!-- 一覧を見るボタン -->
                <x-button.toIndex-button>しおり一覧へ</x-button.toIndex-button>
            @endauth
            <div class="flex justify-center mt-28">
                <div class="max-w-3xl w-full">
                    <h1 class="text-4xl font-extrabold mb-6 text-gray-900 text-center">しおり編集</h1>
                    <!-- 共有アイコンボタン -->
                    <div
                        class="webShareButton text-indigo-600 hover:text-indigo-900 transition duration-150 transform hover:scale-110 cursor-pointer h-6 w-6 ml-5"
                        aria-label="共有"
                        title="共有">
                        <x-button.share-button></x-button.share-button>
                    </div>

                    <livewire:edit-plans-form :overview="$overview"/>
                </div>
            </div>
        </div>
    </div>
@endsection

