@extends('layouts.app')

@section('title')
    しおり編集 - {{ config('app.name') }}
@endsection

@section('meta')
    <!-- Primary Meta Tags -->
    <meta name="title" content="しおり編集 - {{ config('app.name') }}">
    <meta name="description" content="{{ config('app.name') }}のしおり編集ページです。旅行の日程、プラン、持ち物リスト、お土産リストなどを簡単に編集できます。ドラッグ&ドロップで順序の変更も可能です。">

    <!-- Open Graph / Facebook -->
    <meta property="og:title" content="しおり編集 - {{ config('app.name') }}">
    <meta property="og:description" content="{{ config('app.name') }}のしおり編集ページです。旅行の日程、プラン、持ち物リスト、お土産リストなどを簡単に編集できます。ドラッグ&ドロップで順序の変更も可能です。">

    <!-- Twitter -->
    <meta property="twitter:title" content="しおり編集 - {{ config('app.name') }}">
    <meta property="twitter:description" content="{{ config('app.name') }}のしおり編集ページです。旅行の日程、プラン、持ち物リスト、お土産リストなどを簡単に編集できます。ドラッグ&ドロップで順序の変更も可能です。">
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
                    <button
                        type="button"
                        class="webShareButton text-indigo-600 hover:text-indigo-900 transition duration-150 transform hover:scale-110 cursor-pointer h-6 w-6 ml-5"
                        aria-label="共有URLをコピー"
                        title="共有URLをコピー"
                        data-share-url="{{ route('itineraries.edit', $overview->id) }}"
                        data-share-title="しおりの編集">
                        <x-button.share-button></x-button.share-button>
                    </button>

                    @if (session('status'))
                        <div class="mt-4 rounded-md bg-green-50 p-4 text-sm text-green-700">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($isOwner)
                        <section class="mt-6 bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-4 sm:p-6">
                            <h2 class="text-xl font-semibold text-gray-800">旅行メンバー</h2>
                            <p class="mt-2 text-sm text-gray-600">
                                登録済みユーザーのメールアドレスを追加すると、このしおりを一緒に編集できます。持ち物リストは各ユーザーごとに分かれます。
                            </p>

                            <form method="POST" action="{{ route('itineraries.members.store', $overview) }}" class="mt-4 flex flex-col gap-3 sm:flex-row">
                                @csrf
                                <div class="flex-1">
                                    <label for="member_email" class="sr-only">メンバーのメールアドレス</label>
                                    <input
                                        id="member_email"
                                        name="email"
                                        type="email"
                                        value="{{ old('email') }}"
                                        placeholder="member@example.com"
                                        class="block w-full rounded-md border border-gray-300 px-3 py-2 shadow-xs focus:border-indigo-500 focus:outline-hidden focus:ring-indigo-500">
                                    @error('email')
                                    <span class="mt-1 block text-sm text-red-500">{{ $message }}</span>
                                    @enderror
                                </div>
                                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                    追加
                                </button>
                            </form>

                            <div class="mt-5 space-y-3">
                                @foreach($overview->travelMembers->sortByDesc('role') as $member)
                                    <div class="flex items-center justify-between gap-3 rounded-md bg-gray-50 p-3">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">{{ $member->user->name }}</p>
                                            <p class="text-xs text-gray-500">{{ $member->user->email }}</p>
                                        </div>
                                        @if ($member->role === 'owner')
                                            <span class="rounded-full bg-gray-200 px-3 py-1 text-xs font-medium text-gray-700">作成者</span>
                                        @else
                                            <form method="POST" action="{{ route('itineraries.members.destroy', [$overview, $member]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-800">
                                                    削除
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    <livewire:edit-plans-form :overview="$overview"/>
                </div>
            </div>
        </div>
    </div>
@endsection
