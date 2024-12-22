<!-- ヘッダー -->
<header>
    <div class="fixed w-full z-50">
        <div class="flex shadow-md py-4 px-4 sm:px-10 bg-white font-sans min-h-[70px] tracking-wide relative">
            <div class="flex flex-wrap items-center justify-between gap-5 w-full">
                <!-- ロゴ（大画面） -->
                <a href="{{ route('home') }}" class="hidden sm:block">
                    <img src="https://readymadeui.com/readymadeui.svg" alt="logo" class="w-36"/>
                </a>
                <!-- ロゴ（小画面） -->
                <a href="{{ route('home') }}" class="sm:hidden">
                    <img src="https://readymadeui.com/readymadeui-short.svg" alt="logo" class="w-9"/>
                </a>

                <!-- メニュー -->
                <div id="collapseMenu"
                     class="fixed inset-y-0 left-0 transform -translate-x-full transition-transform duration-300 ease-in-out bg-white dark:bg-neutral-900 w-1/2 max-w-sm z-50 shadow-lg">
                    <!-- 閉じるボタン -->
                    <button id="toggleClose"
                            class="absolute top-4 right-4 z-60 rounded-full bg-white dark:bg-neutral-900 w-9 h-9 flex items-center justify-center border dark:border-neutral-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 fill-black dark:fill-white"
                             viewBox="0 0 320.591 320.591">
                            <path
                                d="M30.391 318.583a30.37 30.37 0 0 1-21.56-7.288c-11.774-11.844-11.774-30.973 0-42.817L266.643 10.665c12.246-11.459 31.462-10.822 42.921 1.424 10.362 11.074 10.966 28.095 1.414 39.875L51.647 311.295a30.366 30.366 0 0 1-21.256 7.288z"
                                data-original="#000000"></path>
                            <path
                                d="M287.9 318.583a30.37 30.37 0 0 1-21.257-8.806L8.83 51.963C-2.078 39.225-.595 20.055 12.143 9.146c11.369-9.736 28.136-9.736 39.504 0l259.331 257.813c12.243 11.462 12.876 30.679 1.414 42.922-.456.487-.927.958-1.414 1.414a30.368 30.368 0 0 1-23.078 7.288z"
                                data-original="#000000"></path>
                        </svg>
                    </button>

                    <!-- メニュー項目 -->
                    <ul class="flex flex-col mt-16 space-y-4 px-6">
                        <li>
                            <a href="{{ route('home') }}"
                               class="text-blue-600 hover:text-[#007bff] font-semibold text-[15px]">Home</a>
                        </li>
                        <li>
                            <a href="{{ route('home') }}"
                               class="text-gray-500 hover:text-[#007bff] font-semibold text-[15px]">Make</a>
                        </li>
                        <li>
                            <a href="{{ route('home') }}"
                               class="text-gray-500 hover:text-[#007bff] font-semibold text-[15px]">Contact</a>
                        </li>
                    </ul>
                    <ul class="flex flex-col mt-16 space-y-4 px-6">
                        @auth
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit"
                                            class="text-gray-500 hover:text-[#007bff] font-semibold text-[15px]">
                                        Logout
                                    </button>
                                </form>
                            </li>
                        @else
                            <li>
                                <a href="{{ route('register') }}"
                                   class="text-gray-500 hover:text-[#007bff] font-semibold text-[15px]">Register</a>
                            </li>
                            <li>
                                <a href="{{ route('login') }}"
                                   class="text-gray-500 hover:text-[#007bff] font-semibold text-[15px]">Login</a>
                            </li>
                        @endauth
                    </ul>

                </div>

                <!-- オーバーレイ -->
                <div id="overlay"
                     class="fixed inset-0 bg-black opacity-0 pointer-events-none transition-opacity duration-300 ease-in-out z-40"></div>

                <!-- ナビゲーション -->
                <div class="flex items-center space-x-8">
                    <!-- ナビゲーションリンク -->
                    <div class="space-x-4 hidden sm:flex">
                        <div>
                            <a href="{{ route('itineraries.create') }}"
                               class="text-black hover:text-gray-700 font-semibold">Make</a>
                        </div>
                        <div>
                            <a href="{{ route('itineraries.create') }}"
                               class="text-black hover:text-gray-700 font-semibold">About</a>
                        </div>
                        <div>
                            <a href="{{ route('itineraries.create') }}"
                               class="text-black hover:text-gray-700 font-semibold">Contact</a>
                        </div>
                    </div>

                    <!-- ログイン・サインアップボタン -->
                    <div class="flex space-x-4">
                        @auth
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                        class="px-4 py-2 text-sm rounded-full font-bold text-gray-500 border-2 bg-transparent hover:bg-gray-50 transition-all ease-in-out duration-300 dark:border-neutral-700 dark:hover:bg-neutral-800">
                                    Logout
                                </button>
                            </form>
                            <a href="{{ route('itineraries.index') }}"
                               class="px-4 py-2 text-sm rounded-full font-bold text-white border-2 border-[#007bff] bg-[#007bff] transition-all ease-in-out duration-300 hover:bg-transparent hover:text-[#007bff] hidden sm:block">
                                MyPage
                            </a>
                        @else
                            <a href="{{ route('login') }}"
                               class="px-4 py-2 text-sm rounded-full font-bold text-gray-500 border-2 bg-transparent hover:bg-gray-50 transition-all ease-in-out duration-300 dark:border-neutral-700 dark:hover:bg-neutral-800">
                                Login
                            </a>
                            <a href="{{ route('register') }}"
                               class="px-4 py-2 text-sm rounded-full font-bold text-white border-2 border-[#007bff] bg-[#007bff] transition-all ease-in-out duration-300 hover:bg-transparent hover:text-[#007bff] hidden sm:block">
                                Sign up
                            </a>
                        @endauth
                    </div>


                    <!-- ハンバーガーメニューボタン（小画面のみ表示） -->
                    <button id="toggleOpen" class="sm:hidden" aria-label="メニューを開く">
                        <svg class="w-7 h-7" fill="#000" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd"
                                  d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
                                  clip-rule="evenodd"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</header>
