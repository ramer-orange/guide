<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>しおり一覧ページ</title>

    @vite('resources/css/app.css')
</head>
<body>
<main>
    <div class="bg-[#fffdfa]">
        <div class="max-w-7xl mx-auto p-4 sm:p-6 dark:bg-gray-800 min-h-screen">
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
                                    <div class="flex space-x-2">
                                        <!-- 編集アイコンボタン -->
                                        <a href="{{ route('itineraries.edit', $overview->id) }}"
                                           class="text-indigo-600 hover:text-indigo-900 transition duration-150 transform hover:scale-110"
                                           aria-label="編集"
                                           title="編集">
                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                 class="h-6 w-6"
                                                 fill="none"
                                                 viewBox="0 0 24 24"
                                                 stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                        </a>

                                        <!-- 削除アイコンボタン -->
                                        <form action="{{ route('itineraries.index.destroy', $overview->id) }}"
                                              method="post"
                                              class="inline-block">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="text-red-600 hover:text-red-900 transition duration-150 transform hover:scale-110"
                                                    onclick="return confirm('本当に削除しますか？');"
                                                    aria-label="削除"
                                                    title="削除">
                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                     class="h-6 w-6"
                                                     fill="none"
                                                     viewBox="0 0 24 24"
                                                     stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          stroke-width="2"
                                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </form>
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
</main>
</body>
</html>
