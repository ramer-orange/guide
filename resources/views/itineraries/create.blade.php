@extends('layouts.app')

@section('title')
    しおり作成 - {{ config('app.name') }}
@endsection

@section('meta')
    <!-- Primary Meta Tags -->
    <meta name="title" content="しおり作成 - config('app.name')">
    <meta name="description" content="config('app.name')のしおり作成ページです。旅行の日程、プラン、持ち物リストなどを簡単に作成できます。メンバーとの共有機能で、みんなで旅行の準備を進めることができます。">

    <!-- Open Graph / Facebook -->
    <meta property="og:title" content="しおり作成 - config('app.name')">
    <meta property="og:description" content="config('app.name')のしおり作成ページです。旅行の日程、プラン、持ち物リストなどを簡単に作成できます。メンバーとの共有機能で、みんなで旅行の準備を進めることができます。">

    <!-- Twitter -->
    <meta property="twitter:title" content="しおり作成 - config('app.name')">
    <meta property="twitter:description" content="config('app.name')のしおり作成ページです。旅行の日程、プラン、持ち物リストなどを簡単に作成できます。メンバーとの共有機能で、みんなで旅行の準備を進めることができます。">
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
                    <h1 class="text-4xl font-extrabold mb-21 text-gray-900 text-center">しおり作成</h1>
                    <livewire:plans-form/>
                </div>
            </div>
        </div>
    </div>
@endsection
