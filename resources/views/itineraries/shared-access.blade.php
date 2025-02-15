@extends('layouts.app')

@section('title')
    共有パスワード認証 - {{ config('app.name') }}
@endsection

@section('meta')
    <!-- Primary Meta Tags -->
    <meta name="description" content="{{ config('app.name') }} - 旅の計画をもっと簡単に、もっと楽しく">
    <meta name="robots" content="noindex,nofollow">

    <!-- OGP -->
    <meta property="og:title" content="{{ config('app.name') }} - 旅の計画をもっと簡単に、もっと楽しく">
    <meta property="og:description" content="旅行プランの共有アクセスページです。共有パスワードを入力して旅行プランの詳細をご覧いただけます。">

    <!-- Twitter -->
    <meta name="twitter:title" content="{{ config('app.name') }} - 旅の計画をもっと簡単に、もっと楽しく">
    <meta name="twitter:description" content="旅行プランの共有アクセスページです。共有パスワードを入力して旅行プランの詳細をご覧いただけます。">
@endsection

@section('content')
    <div class="bg-[#fffdfa]">
        <div class="max-w-4xl mx-auto">
            <div class="min-h-screen flex items-center justify-center pl-4 pr-4">
                <div class="bg-white rounded-lg shadow-md p-8 w-full max-w-xl">
                    <h2 class="text-xl font-semibold text-gray-700 text-center">共有パスワード入力</h2>
                    <p class="text-gray-600 mt-4 text-center">
                        このページを閲覧するには、共有パスワードを入力してください。</p>
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
    </div>
@endsection
