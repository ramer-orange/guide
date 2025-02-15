@extends('layouts.app')

@section('title')
    しおり一覧 - {{ config('app.name') }}
@endsection

@section('meta')
    <!-- Primary Meta Tags -->
    <meta name="title" content="しおり一覧 - {{ config('app.name') }}">
    <meta name="description" content="{{ config('app.name') }}のしおり一覧ページです。作成した旅行しおりを管理・編集できます。共有機能を使って他のメンバーと旅行プランを共有することもできます。">

    <!-- Open Graph / Facebook -->
    <meta property="og:title" content="しおり一覧 - {{ config('app.name') }}">
    <meta property="og:description" content="{{ config('app.name') }}のしおり一覧ページです。作成した旅行しおりを管理・編集できます。共有機能を使って他のメンバーと旅行プランを共有することもできます。">

    <!-- Twitter -->
    <meta property="twitter:title" content="しおり一覧 - {{ config('app.name') }}">
    <meta property="twitter:description" content="{{ config('app.name') }}のしおり一覧ページです。作成した旅行しおりを管理・編集できます。共有機能を使って他のメンバーと旅行プランを共有することもできます。">
@endsection

@section('content')
    <div class="bg-[#fffdfa]">
        <div class="max-w-7xl mx-auto p-4 pt-24 sm:p-6,pt-24 dark:bg-gray-800 min-h-screen ">
            <h1 class="text-3xl sm:text-4xl font-extrabold mb-6 text-gray-900">しおり一覧</h1>

            <div class="bg-white dark:bg-gray-700 shadow-lg rounded-lg p-4 sm:p-8">
                @if ($overviews->isEmpty())
                    <p class="text-gray-500 dark:text-gray-400 text-center">まだしおりを作成していません。</p>
                @else
                    <div class="space-y-4">
                        @foreach ($overviews as $overview)
                            <div
                                class="bg-white dark:bg-gray-700 shadow-md rounded-lg p-4 hover:shadow-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition duration-150">
                                <div class="flex justify-between items-center mb-2">
                                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $overview->title }}</h2>
                                    <div class="flex space-x-3 items-center">
                                        <!-- 共有アイコンボタン -->
                                        <div
                                            class="webShareButton text-indigo-600 hover:text-indigo-900 transition duration-150 transform hover:scale-110 cursor-pointer h-5 w-5 ml-5"
                                            aria-label="共有"
                                            title="共有"
                                            data-share-url="{{ route('itineraries.edit', $overview->id) }}"
                                            data-share-title="しおりの編集"
                                            data-share-description="しおりの編集ページです">
                                            <x-button.share-button></x-button.share-button>
                                        </div>

                                        <!-- 編集アイコンボタン -->
                                        <a href="{{ route('itineraries.edit', $overview->id) }}"
                                           class="text-indigo-600 hover:text-indigo-900 transition duration-150 transform hover:scale-110"
                                           aria-label="編集"
                                           title="編集">
                                            <x-button.create-button></x-button.create-button>
                                        </a>

                                        <!-- 削除ボタン -->
                                        <button type="button"
                                                class="openDeleteModalButton text-red-600 hover:text-red-900 transition duration-150 transform hover:scale-110 block"
                                                data-url="{{ route('itineraries.index.destroy', $overview->id) }}"
                                                data-target="#deleteModal-{{ $overview->id }}"
                                                aria-label="削除"
                                                title="削除">
                                            <x-button.trash-button></x-button.trash-button>
                                        </button>

                                        <!-- 削除確認モーダル -->
                                        <div id="deleteModal-{{ $overview->id }}"
                                             class="deleteModal fixed inset-0 flex items-center justify-center bg-gray-900/70 z-50 hidden pl-4 pr-4">
                                            <div class="modalContent bg-white rounded-lg shadow-lg p-4 sm:p-6 lg:p-8 w-full max-w-md sm:max-w-lg lg:max-w-xl">
                                                <h2 class="text-lg sm:text-xl lg:text-2xl font-semibold text-gray-800 mb-4">削除確認</h2>
                                                <p class="text-sm sm:text-base text-gray-600 mb-6">本当に削除してもよろしいですか？この操作は取り消せません。</p>
                                                <div class="flex justify-end space-x-2 sm:space-x-3">
                                                    <!-- キャンセルボタン -->
                                                    <button class="cancelButton px-3 py-2 sm:px-4 sm:py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-lg text-sm sm:text-base cursor-pointer">
                                                        キャンセル
                                                    </button>
                                                    <!-- 削除ボタン -->
                                                    <form method="post" class="deleteForm">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="px-3 py-2 sm:px-4 sm:py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm sm:text-base cursor-pointer">
                                                            削除する
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- 概要欄 -->
                                <p class="text-gray-600 dark:text-gray-300 mb-2">{{ $overview->overviewText }}</p>
                                <!-- 作成日 -->
                                <p class="text-gray-500 dark:text-gray-400 text-sm">
                                    作成日: {{ $overview->created_at->format('Y-m-d') }}</p>
                            </div>
                        @endforeach

                    </div>
                @endif

                <!-- 新規作成ボタン -->
                <div class="mt-6 flex justify-end">
                    <a href="{{ route('itineraries.create') }}"
                       class="relative inline-block px-4 py-2 font-medium group"
                       aria-label="新しくしおりを作成する" title="新しくしおりを作成する">
                <span
                    class="absolute inset-0 w-full h-full transition duration-200 ease-out transform translate-x-1 translate-y-1 bg-black group-hover:-translate-x-0 group-hover:-translate-y-0"></span>
                        <span
                            class="absolute inset-0 w-full h-full bg-white border-2 border-black group-hover:bg-black"></span>

                        <span class="relative flex items-center text-black group-hover:text-white">
                    <!-- プラスアイコン -->
                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="h-5 w-5 mr-2"
                         viewBox="0 0 20 20"
                         fill="currentColor"
                         aria-hidden="true">
                        <path
                            d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"/>
                    </svg>
                    新しくしおりを作成する
                </span>
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
