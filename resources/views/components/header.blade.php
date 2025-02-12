<!-- ヘッダー -->
<header>
    <div class="fixed w-full z-50">
        <div class="shadow-sm py-4 px-4 sm:px-10 font-sans min-h-[70px] tracking-wide">
            <div class="flex flex-wrap items-center justify-between gap-5 w-full max-w-7xl 2xl:max-w-[96rem] mx-auto">
                <!-- ロゴ -->
                <a href="{{ route('home') }}" class="flex items-center space-x-2">
                    <span class="text-2xl font-bold bg-gradient-to-r from-blue-400 to-teal-400 bg-clip-text text-transparent">{{ config('app.name') }}</span>
                </a>

                <!-- ナビゲーション -->
                <div class="flex items-center space-x-8">
                    <!-- ナビゲーションリンク -->
                    <div class="space-x-6 hidden sm:flex items-center">
                        {{-- <a href="{{ route('itineraries.create') }}"
                           class="text-black hover:text-gray-900 font-medium transition-colors">
                            プラン作成
                        </a> --}}
                    </div>

                    <!-- ログイン・サインアップボタン -->
                    <div class="flex items-center space-x-4">
                        @auth
                            <a href="{{ route('itineraries.index') }}"
                               class="hidden sm:block px-5 py-2.5 text-teal-600 border-2 border-teal-600
                                      hover:text-white hover:bg-teal-600 rounded-full font-bold
                                      transition-all duration-200 text-sm">
                                マイページ
                            </a>
                            <form method="POST" action="{{ route('logout') }}" class="hidden sm:block">
                                @csrf
                                <button type="submit"
                                        class="px-5 py-2.5 text-rose-400 border-2 border-rose-400
                                               hover:text-white hover:bg-rose-400 rounded-full font-bold
                                               transition-all duration-200 text-sm cursor-pointer">
                                    ログアウト
                                </button>
                            </form>
                        @else
                            <a href="{{ route('login') }}"
                               class="hidden sm:block px-5 py-2.5 text-blue-600 border-2 border-blue-600
                                      hover:text-white hover:bg-blue-600 rounded-full font-bold
                                      transition-all duration-200 text-sm">
                                ログイン
                            </a>
                            <a href="{{ route('register') }}"
                               class="hidden sm:inline-flex items-center justify-center px-5 py-2.5 text-sm
                                      font-medium text-white bg-gradient-to-r from-blue-400 to-teal-400
                                      hover:from-blue-500 hover:to-teal-500 rounded-full
                                      transition-all duration-300 transform hover:scale-[1.02]
                                      hover:shadow-lg">
                                無料で始める
                            </a>
                        @endauth

                        <!-- ハンバーガーメニューボタン（小画面のみ） -->
                        <button id="toggleOpen" class="sm:hidden" aria-label="メニューを開く">
                            <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- モバイルメニュー -->
    <div id="collapseMenu"
         class="fixed inset-y-0 left-0 transform -translate-x-full transition-transform duration-300 ease-in-out bg-white w-4/5 max-w-sm z-50">
        <div class="p-6">
            <button id="toggleClose" class="absolute top-5 right-5">
                <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <div class="mt-8">
                <nav class="space-y-6">
                    <a href="{{ route('itineraries.create') }}" class="block text-lg font-medium text-gray-600 hover:text-gray-900">プラン作成</a>
                    @auth
                        <a href="{{ route('itineraries.index') }}" class="block text-lg font-medium text-gray-600 hover:text-gray-900">マイページ</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block text-lg font-medium text-gray-600 hover:text-gray-900">ログアウト</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="block text-lg font-medium text-gray-600 hover:text-gray-900">ログイン</a>
                        <a href="{{ route('register') }}" class="block text-lg font-medium text-gray-600 hover:text-gray-900">新規登録</a>
                    @endauth
                </nav>
            </div>
        </div>
    </div>

    <!-- オーバーレイ -->
    <div id="overlay" class="fixed inset-0 bg-black opacity-0 pointer-events-none transition-opacity duration-300 ease-in-out z-40"></div>
</header>
