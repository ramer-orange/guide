<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>しおり作成ページ</title>

    @vite('resources/css/app.css')
</head>
<body class="bg-[#fffdfa]">
<header>
    <form method="POST" action="{{ route('logout') }}">
        @csrf

        <x-dropdown-link :href="route('logout')"
                         onclick="event.preventDefault();
                                                this.closest('form').submit();">
            {{ __('Log Out') }}
        </x-dropdown-link>
    </form>
</header>
<main>
    <div class="max-w-7xl mx-auto p-4 pb-8 sm:p-6 sm:pb-12 dark:bg-gray-800 min-h-screen">
        <div class="flex justify-center">
            <div class="max-w-3xl w-full">
                <h1 class="text-3xl sm:text-4xl font-extrabold mb-6 text-gray-900 text-center">しおり作成</h1>
                <livewire:plans-form/>

                <!-- 一覧を見るボタン -->
                <div class="mt-6 flex justify-end">
                    <a href="{{ route('itineraries.index') }}" class="relative inline-block px-4 py-2 font-medium group"
                       aria-label="一覧を見る" title="一覧を見る">
                        <span
                            class="absolute inset-0 w-full h-full transition duration-200 ease-out transform translate-x-1 translate-y-1 bg-black group-hover:-translate-x-0 group-hover:-translate-y-0"></span>
                        <span
                            class="absolute inset-0 w-full h-full bg-white border-2 border-black group-hover:bg-black"></span>

                        <span class="relative flex items-center text-black group-hover:text-white">
                            しおり一覧を見る
                            <!-- 右矢印アイコン -->
                            <svg xmlns="http://www.w3.org/2000/svg"
                                 class="h-5 w-5 mr-2"
                                 viewBox="0 0 20 20"
                                 fill="currentColor"
                                 aria-hidden="true">
                                <path fill-rule="evenodd"
                                      d="M10.293 15.707a1 1 0 010-1.414L13.586 10 10.293 6.707a1 1 0 011.414-1.414l5 5a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0z"
                                      clip-rule="evenodd"/>
                            </svg>
                        </span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
