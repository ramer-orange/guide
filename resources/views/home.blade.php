@extends('layouts.app')

@section('title')
    {{ config('app.name') }} - 旅行計画をもっと楽しく、もっと簡単に
@endsection

@section('meta')
    <!-- Primary Meta Tags -->
    <meta name="title" content="{{ config('app.name') }} - 旅の計画をもっと簡単に、もっと楽しく">
    <meta name="description"
          content="{{ config('app.name') }}は、旅行計画を簡単に作成・共有できるサービスです。スケジュール管理、持ち物リスト、メンバーとの共同編集など、旅行の準備に必要な機能が全て無料で使えます。">

    <!-- Open Graph / Facebook -->
    <meta property="og:title" content="{{ config('app.name') }} - 旅行計画をもっと楽しく、もっと簡単に">
    <meta property="og:description"
          content="{{ config('app.name') }}は、旅行計画を簡単に作成・共有できるサービスです。スケジュール管理、持ち物リスト、メンバーとの共同編集など、旅行の準備に必要な機能が全て無料で使えます。">

    <!-- Twitter -->
    <meta property="twitter:title" content="{{ config('app.name') }} - 旅行計画をもっと楽しく、もっと簡単に">
    <meta property="twitter:description"
          content="{{ config('app.name') }}は、旅行計画を簡単に作成・共有できるサービスです。スケジュール管理、持ち物リスト、メンバーとの共同編集など、旅行の準備に必要な機能が全て無料で使えます。">
@endsection

@section('content')
    <!-- ヒーローセクション -->
    <div class="relative min-h-screen overflow-hidden">
        <!-- 背景画像 -->
        <div class="absolute inset-0">
            <img src="{{ asset('images/mv/MV.jpeg') }}" alt="背景" class="w-full h-full object-cover">
            <!-- 画像の上に暗いオーバーレイを追加 -->
            <div class="absolute inset-0 bg-gradient-to-b from-black/50 to-transparent"></div>
        </div>

        <!-- メインコンテンツ -->
        <div class="relative container mx-0 sm:mx-auto px-6 pt-32 pb-16">
            <div class="max-w-3xl">
                <h1 class="text-4xl sm:text-5xl md:text-6xl font-bold text-white leading-tight mb-6">
                    旅の計画を<br>もっと簡単に、<br>もっと楽しく。
                </h1>
                <p class="text-base sm:text-xl text-white/90 leading-relaxed mb-8">
                    思いのままに、あなただけの旅をデザイン。<br>
                    思い出作りが、これまで以上に楽しくなる。
                </p>
                <div class="flex gap-4">
                    <a href="{{ route('register')}}"
                       class="group relative inline-flex items-center justify-center px-8 py-4
                              text-base font-medium text-white transition-all duration-300
                              bg-gradient-to-r from-blue-500 to-teal-400
                              hover:from-blue-600 hover:to-teal-500
                              rounded-full shadow-lg hover:shadow-xl
                              transform hover:scale-[1.02]
                              hover:shadow-[0_0_25px_rgba(59,130,246,0.5)]">
                        無料で始める
                        <svg class="w-5 h-5 ml-2 transition-transform duration-300
                                  group-hover:translate-x-1 group-hover:scale-110"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        <!-- 波形の区切り -->
        <div class="absolute bottom-0 left-0 w-full">
            <svg class="waves" xmlns="http://www.w3.org/2000/svg" viewBox="0 24 150 28" preserveAspectRatio="none">
                <defs>
                    <path id="wave" d="M-160 44c30 0 58-18 88-18s 58 18 88 18 58-18 88-18 58 18 88 18 v44h-352z"/>
                </defs>
                <g class="parallax">
                    <use href="#wave" x="48" y="0" fill="rgba(255,255,255,0.7)"/>
                    <use href="#wave" x="48" y="3" fill="rgba(255,255,255,0.5)"/>
                    <use href="#wave" x="48" y="5" fill="rgba(255,255,255,0.3)"/>
                    <use href="#wave" x="48" y="7" fill="#ffffff"/>
                </g>
            </svg>
        </div>
    </div>

    <!-- 機能紹介セクション -->
    <section id="features" class="py-24 bg-white">
        <div class="container mx-auto px-6">
            <h2 class="text-3xl sm:text-4xl font-bold text-center mb-16 text-gray-900">
                主な機能
            </h2>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-12">
                <!-- 機能カード1: プラン作成 -->
                <div
                    class="group relative bg-white p-8 rounded-2xl shadow-xl hover:shadow-2xl transition-all duration-300 border border-gray-100">
                    <div
                        class="absolute -top-4 -right-4 w-24 h-24 bg-gradient-to-br from-blue-400 to-teal-400 rounded-full opacity-50 blur-2xl group-hover:opacity-100 transition-opacity"></div>
                    <div class="relative">
                        <div
                            class="w-14 h-14 mb-6 bg-gradient-to-br from-blue-400 to-teal-400 rounded-xl flex items-center justify-center">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold mb-4">詳細なプラン作成</h3>
                        <p class="text-gray-600">
                            日時、写真、メモを自由に組み合わせて、理想の旅程を作成できます。
                        </p>
                    </div>
                </div>

                <!-- 機能カード2: リスト管理 -->
                <div
                    class="group relative bg-white p-8 rounded-2xl shadow-xl hover:shadow-2xl transition-all duration-300 border border-gray-100">
                    <div
                        class="absolute -top-4 -right-4 w-24 h-24 bg-gradient-to-br from-blue-400 to-teal-400 rounded-full opacity-50 blur-2xl group-hover:opacity-100 transition-opacity"></div>
                    <div class="relative">
                        <div
                            class="w-14 h-14 mb-6 bg-gradient-to-br from-blue-400 to-teal-400 rounded-xl flex items-center justify-center">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold mb-4">持ち物・お土産リスト</h3>
                        <p class="text-gray-600">
                            テンプレート付きの便利なチェックリストで、準備も買い物も抜かりなく。
                        </p>
                    </div>
                </div>

                <!-- 機能カード3: 共有機能 -->
                <div
                    class="group relative bg-white p-8 rounded-2xl shadow-xl hover:shadow-2xl transition-all duration-300 border border-gray-100">
                    <div
                        class="absolute -top-4 -right-4 w-24 h-24 bg-gradient-to-br from-blue-400 to-teal-400 rounded-full opacity-50 blur-2xl group-hover:opacity-100 transition-opacity"></div>
                    <div class="relative">
                        <div
                            class="w-14 h-14 mb-6 bg-gradient-to-br from-blue-400 to-teal-400 rounded-xl flex items-center justify-center">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold mb-4">簡単共有・編集</h3>
                        <p class="text-gray-600">
                            仲間と共同編集できる。旅の準備も皆で楽しく。
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 使い方セクション -->
    <section class="py-24 bg-gray-50">
        <div class="container mx-auto px-6">
            <h2 class="text-3xl sm:text-4xl font-bold text-center mb-16 text-gray-900">
                3ステップで簡単作成
            </h2>
            <div class="grid lg:grid-cols-3 gap-12">
                <!-- ステップ1 -->
                <div class="relative group">
                    <div
                        class="absolute -inset-1 bg-gradient-to-r from-blue-400 to-teal-400 rounded-lg blur opacity-25 group-hover:opacity-100 transition duration-1000 group-hover:duration-200"></div>
                    <div class="relative bg-white p-8 rounded-lg">
                        <div class="text-6xl font-bold text-blue-400 mb-4">01</div>
                        <h3 class="text-2xl font-bold mb-4">プランを作成</h3>
                        <p class="text-gray-600">
                            旅行のタイトルと日程を設定するだけで、すぐにプラン作成を始められます。
                        </p>
                    </div>
                </div>

                <!-- ステップ2 -->
                <div class="relative group">
                    <div
                        class="absolute -inset-1 bg-gradient-to-r from-blue-400 to-teal-400 rounded-lg blur opacity-25 group-hover:opacity-100 transition duration-1000 group-hover:duration-200"></div>
                    <div class="relative bg-white p-8 rounded-lg">
                        <div class="text-6xl font-bold text-teal-400 mb-4">02</div>
                        <h3 class="text-2xl font-bold mb-4">スケジュールを追加</h3>
                        <p class="text-gray-600">
                            時間、場所、写真を追加。ドラッグ&ドロップで簡単に予定を調整できます。
                        </p>
                    </div>
                </div>

                <!-- ステップ3 -->
                <div class="relative group">
                    <div
                        class="absolute -inset-1 bg-gradient-to-r from-blue-400 to-teal-400 rounded-lg blur opacity-25 group-hover:opacity-100 transition duration-1000 group-hover:duration-200"></div>
                    <div class="relative bg-white p-8 rounded-lg">
                        <div class="text-6xl font-bold text-blue-400 mb-4">03</div>
                        <h3 class="text-2xl font-bold mb-4">仲間と共有</h3>
                        <p class="text-gray-600">
                            URLを共有するだけで、みんなで同時に編集できます。
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- デモセクション -->
    <section class="py-24 bg-white">
        <div class="container mx-auto px-6">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div class="space-y-8">
                    <h2 class="text-3xl sm:text-4xl font-bold leading-relaxed">
                        <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-teal-400">
                            直感的な操作で、
                        </span>
                        <br>
                        しおり作成が驚くほど簡単に
                    </h2>
                    <div class="space-y-6">
                        <div class="flex items-start gap-4">
                            <div
                                class="flex-shrink-0 w-12 h-12 bg-gradient-to-br from-blue-400 to-teal-400 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold mb-2">ドラッグ&ドロップで予定を調整</h3>
                                <p class="text-gray-600">予定の順番変更も、時間の調整も、マウス操作だけで完結。</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4">
                            <div
                                class="flex-shrink-0 w-12 h-12 bg-gradient-to-br from-blue-400 to-teal-400 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold mb-2">写真とファイルを自由に追加</h3>
                                <p class="text-gray-600">チケットの画像や、参考にしたいWebサイトなども一緒に保存。</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="relative">
                    <div class="relative rounded-xl overflow-hidden shadow-2xl max-h-[500px] mx-auto max-w-4xl">
                        <img src="{{ asset('images/mockup/mockup.png') }}" alt="デモ画面"
                             class="w-full h-full object-contain">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- お客様の声セクション -->
    <section class="py-24 bg-gray-50" id="voice">
        <div class="container mx-auto px-6">
            <h2 class="text-3xl sm:text-4xl font-bold text-center mb-16 text-gray-900">
                ユーザーからの声
            </h2>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- レビュー1 -->
                <div class="bg-white p-8 rounded-xl shadow-lg">
                    <div class="flex items-center mb-4">
                        <div class="flex-shrink-0">
                            <img class="h-12 w-12 rounded-full object-cover"
                                 src="{{ asset('images/voice/voice1.png') }}" alt="ユーザー1">
                        </div>
                        <div class="ml-4">
                            <h4 class="text-lg font-bold">20代前半・女性</h4>
                            <div class="flex text-yellow-400">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <p class="text-gray-600">
                        友達との旅行計画が格段に楽になりました。みんなで同時に編集できるのが便利です！
                    </p>
                </div>

                <!-- レビュー2 -->
                <div class="bg-white p-8 rounded-xl shadow-lg">
                    <div class="flex items-center mb-4">
                        <div class="flex-shrink-0">
                            <img class="h-12 w-12 rounded-full object-cover"
                                 src="{{ asset('images/voice/voice2.png') }}" alt="ユーザー1">
                        </div>
                        <div class="ml-4">
                            <h4 class="text-lg font-bold">20代前半・男性</h4>
                            <div class="flex text-yellow-400">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <p class="text-gray-600">
                        旅行に必要な情報をこれ一つで一元管理できるので、とっても便利です！！！
                    </p>
                </div>

                <!-- レビュー3 -->
                <div class="bg-white p-8 rounded-xl shadow-lg">
                    <div class="flex items-center mb-4">
                        <div class="flex-shrink-0">
                            <img class="h-12 w-12 rounded-full object-cover"
                                 src="{{ asset('images/voice/voice3.png') }}" alt="ユーザー1">
                        </div>
                        <div class="ml-4">
                            <h4 class="text-lg font-bold">20代後半・女性</h4>
                            <div class="flex text-yellow-400">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <p class="text-gray-600">
                        持ち物リストにチェックマークをつけれるので、忘れ物を防ぐことができます！！
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Q&A セクション -->
    <section class="py-24 bg-white" id="question">
        <div class="container mx-auto px-6">
            <h2 class="text-3xl sm:text-4xl font-bold text-center mb-16 text-gray-900">
                よくある質問
            </h2>
            <div class="w-full max-w-3xl mx-auto">
                <div class="divide-y divide-slate-200 px-6 border-gray-200 border rounded-lg mb-4 cursor-pointer">
                    <div x-data="{ expanded: false }" class="py-2">
                        <div>
                            <div
                                id="faqs-title-01"
                                type="button"
                                class="flex items-center justify-between w-full text-left font-semibold py-2 text-lg"
                                @click="expanded = !expanded"
                                :aria-expanded="expanded"
                                aria-controls="faqs-text-01"
                            >
                                <span>無料で使えますか？</span>
                                <svg class="fill-indigo-500 shrink-0 ml-8" width="16" height="16"
                                     xmlns="http://www.w3.org/2000/svg">
                                    <rect y="7" width="16" height="2" rx="1"
                                          class="transform origin-center transition duration-200 ease-out"
                                          :class="{'!rotate-180': expanded}"/>
                                    <rect y="7" width="16" height="2" rx="1"
                                          class="transform origin-center rotate-90 transition duration-200 ease-out"
                                          :class="{'!rotate-180': expanded}"/>
                                </svg>
                            </div>
                        </div>
                        <div
                            id="faqs-text-01"
                            role="region"
                            aria-labelledby="faqs-title-01"
                            class="grid text-sm text-slate-600 overflow-hidden transition-all duration-300 ease-in-out"
                            :class="expanded ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'"
                        >
                            <div class="overflow-hidden">
                                <p class="pb-3 text-base">
                                    全ての機能を無料で使用することができます。<br>
                                    (会員登録が必要です。会員登録ももちろん無料です。)
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="divide-y divide-slate-200 px-6 border-gray-200 border rounded-lg mb-4 cursor-pointer">
                    <div x-data="{ expanded: false }" class="py-2">
                        <div>
                            <div
                                id="faqs-title-01"
                                type="button"
                                class="flex items-center justify-between w-full text-left font-semibold py-2 text-lg"
                                @click="expanded = !expanded"
                                :aria-expanded="expanded"
                                aria-controls="faqs-text-01"
                            >
                                <span>共有したプランは誰でも編集できますか？</span>
                                <svg class="fill-indigo-500 shrink-0 ml-8" width="16" height="16"
                                     xmlns="http://www.w3.org/2000/svg">
                                    <rect y="7" width="16" height="2" rx="1"
                                          class="transform origin-center transition duration-200 ease-out"
                                          :class="{'!rotate-180': expanded}"/>
                                    <rect y="7" width="16" height="2" rx="1"
                                          class="transform origin-center rotate-90 transition duration-200 ease-out"
                                          :class="{'!rotate-180': expanded}"/>
                                </svg>
                            </div>
                        </div>
                        <div
                            id="faqs-text-01"
                            role="region"
                            aria-labelledby="faqs-title-01"
                            class="grid text-sm text-slate-600 overflow-hidden transition-all duration-300 ease-in-out"
                            :class="expanded ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'"
                        >
                            <div class="overflow-hidden">
                                <p class="pb-3 text-base">
                                    プラン作成者が共有パスワード設定していただければ、誰でも編集することが可能です。
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="divide-y divide-slate-200 px-6 border-gray-200 border rounded-lg mb-4 cursor-pointer">
                    <div x-data="{ expanded: false }" class="py-2">
                        <div>
                            <div
                                id="faqs-title-01"
                                type="button"
                                class="flex items-center justify-between w-full text-left font-semibold py-2 text-lg"
                                @click="expanded = !expanded"
                                :aria-expanded="expanded"
                                aria-controls="faqs-text-01"
                            >
                                <span>共有パスワードを忘れてしまいました。</span>
                                <svg class="fill-indigo-500 shrink-0 ml-8" width="16" height="16"
                                     xmlns="http://www.w3.org/2000/svg">
                                    <rect y="7" width="16" height="2" rx="1"
                                          class="transform origin-center transition duration-200 ease-out"
                                          :class="{'!rotate-180': expanded}"/>
                                    <rect y="7" width="16" height="2" rx="1"
                                          class="transform origin-center rotate-90 transition duration-200 ease-out"
                                          :class="{'!rotate-180': expanded}"/>
                                </svg>
                            </div>
                        </div>
                        <div
                            id="faqs-text-01"
                            role="region"
                            aria-labelledby="faqs-title-01"
                            class="grid text-sm text-slate-600 overflow-hidden transition-all duration-300 ease-in-out"
                            :class="expanded ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'"
                        >
                            <div class="overflow-hidden">
                                <p class="pb-3 text-base">
                                    プラン作成者が、しおり編集ページにて共有パスワードの変更が可能となっております。
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="divide-y divide-slate-200 px-6 border-gray-200 border rounded-lg mb-4 cursor-pointer">
                    <div x-data="{ expanded: false }" class="py-2">
                        <div>
                            <div
                                id="faqs-title-01"
                                type="button"
                                class="flex items-center justify-between w-full text-left font-semibold py-2 text-lg"
                                @click="expanded = !expanded"
                                :aria-expanded="expanded"
                                aria-controls="faqs-text-01"
                            >
                                <span>スマートフォンでも使えますか？</span>
                                <svg class="fill-indigo-500 shrink-0 ml-8" width="16" height="16"
                                     xmlns="http://www.w3.org/2000/svg">
                                    <rect y="7" width="16" height="2" rx="1"
                                          class="transform origin-center transition duration-200 ease-out"
                                          :class="{'!rotate-180': expanded}"/>
                                    <rect y="7" width="16" height="2" rx="1"
                                          class="transform origin-center rotate-90 transition duration-200 ease-out"
                                          :class="{'!rotate-180': expanded}"/>
                                </svg>
                            </div>
                        </div>
                        <div
                            id="faqs-text-01"
                            role="region"
                            aria-labelledby="faqs-title-01"
                            class="grid text-sm text-slate-600 overflow-hidden transition-all duration-300 ease-in-out"
                            :class="expanded ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'"
                        >
                            <div class="overflow-hidden">
                                <p class="pb-3 text-base">
                                    はい、スマートフォンやタブレットなど、様々なデバイスに対応しています。専用アプリのインストールは不要で、ブラウザから快適にご利用いただけます。
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="divide-y divide-slate-200 px-6 border-gray-200 border rounded-lg mb-4 cursor-pointer">
                    <div x-data="{ expanded: false }" class="py-2">
                        <div>
                            <div
                                id="faqs-title-01"
                                type="button"
                                class="flex items-center justify-between w-full text-left font-semibold py-2 text-lg"
                                @click="expanded = !expanded"
                                :aria-expanded="expanded"
                                aria-controls="faqs-text-01"
                            >
                                <span>過去のプランは保存されますか？</span>
                                <svg class="fill-indigo-500 shrink-0 ml-8" width="16" height="16"
                                     xmlns="http://www.w3.org/2000/svg">
                                    <rect y="7" width="16" height="2" rx="1"
                                          class="transform origin-center transition duration-200 ease-out"
                                          :class="{'!rotate-180': expanded}"/>
                                    <rect y="7" width="16" height="2" rx="1"
                                          class="transform origin-center rotate-90 transition duration-200 ease-out"
                                          :class="{'!rotate-180': expanded}"/>
                                </svg>
                            </div>
                        </div>
                        <div
                            id="faqs-text-01"
                            role="region"
                            aria-labelledby="faqs-title-01"
                            class="grid text-sm text-slate-600 overflow-hidden transition-all duration-300 ease-in-out"
                            :class="expanded ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'"
                        >
                            <div class="overflow-hidden">
                                <p class="pb-3 text-base">
                                    はい、作成した全てのプランは、いつでも確認・編集が可能です。
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 追加の質問がある場合のCTA -->
            {{--            <div class="text-center mt-12">--}}
            {{--                <p class="text-gray-600 mb-4">その他のご質問がございましたら、お気軽にお問い合わせください。</p>--}}
            {{--                <a href="#contact"--}}
            {{--                   class="inline-flex items-center justify-center px-6 py-3 text-base font-medium text-white bg-gradient-to-r from-blue-400 to-teal-400 rounded-full hover:from-blue-500 hover:to-teal-500 transition-all duration-200">--}}
            {{--                    お問い合わせ--}}
            {{--                    <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">--}}
            {{--                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"--}}
            {{--                              d="M14 5l7 7m0 0l-7 7m7-7H3"/>--}}
            {{--                    </svg>--}}
            {{--                </a>--}}
            {{--            </div>--}}
        </div>
    </section>

    <!-- CTA セクション -->
    <section class="relative py-24">
        <!-- 背景画像とオーバーレイ -->
        <div class="absolute inset-0">
            <img src="{{ asset('images/cta/cta.jpeg') }}" alt="背景" class="w-full h-full object-cover">
            <!-- 白色オーバーレイ -->
            <div class="absolute inset-0 bg-white/70"></div>
        </div>

        <!-- コンテンツ -->
        <div class="relative container mx-auto px-6 text-center">
            <h2 class="text-4xl font-bold text-gray-900 mb-12 text-center">旅の思い出作りを、<br class = "block sm:hidden">もっとスマートに</h2>
            <a href="{{ route('register') }}"
               class="group inline-flex items-center justify-center px-8 py-4
                   text-base font-medium text-white transition-all duration-300
                   bg-gradient-to-r from-blue-500 to-teal-400
                   hover:from-blue-600 hover:to-teal-500
                   rounded-full shadow-lg hover:shadow-xl
                   transform hover:scale-[1.02]
                   hover:shadow-[0_0_25px_rgba(59,130,246,0.5)]">
                無料で始める
                <svg class="w-5 h-5 ml-2 transition-transform duration-300
                       group-hover:translate-x-1 group-hover:scale-110"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                </svg>
            </a>
        </div>
    </section>
@endsection

<style>
    /* 波形アニメーション */
    .waves {
        position: relative;
        width: 100%;
        height: 15vh;
        margin-bottom: -7px;
        min-height: 100px;
        max-height: 150px;
    }

    .parallax > use {
        animation: move-forever 25s cubic-bezier(.55, .5, .45, .5) infinite;
    }

    .parallax > use:nth-child(1) {
        animation-delay: -2s;
        animation-duration: 7s;
    }

    .parallax > use:nth-child(2) {
        animation-delay: -3s;
        animation-duration: 10s;
    }

    .parallax > use:nth-child(3) {
        animation-delay: -4s;
        animation-duration: 13s;
    }

    .parallax > use:nth-child(4) {
        animation-delay: -5s;
        animation-duration: 20s;
    }

    @keyframes move-forever {
        0% {
            transform: translate3d(-90px, 0, 0);
        }
        100% {
            transform: translate3d(85px, 0, 0);
        }
    }
</style>

