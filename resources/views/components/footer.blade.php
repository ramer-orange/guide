<!--Footer-->
<footer class="bg-white border-t">
    <div class="max-w-7xl mx-auto px-4 sm:px-10 py-12">
        <div class="flex flex-col md:flex-row justify-between items-start mb-12">
            <div class="mb-8 md:mb-0">
                <a href="{{ route('home') }}" class="flex items-center space-x-2">
                    <span class="text-2xl font-bold bg-gradient-to-r from-blue-400 to-teal-400 bg-clip-text text-transparent">{{ config('app.name') }}</span>
                </a>
                <p class="mt-4 text-gray-600 max-w-sm">旅の計画をもっと簡単に、もっと楽しく。<br>あなたの思い出作りをサポートします。</p>
            </div>
        </div>

        <!-- リンクセクション -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
            <div>
                <h3 class="text-sm font-semibold text-gray-900 tracking-wider uppercase mb-4">サービス</h3>
                <ul class="space-y-3">
                    <li><a href="{{ route('itineraries.create') }}" class="text-gray-600 hover:text-gray-900 transition-colors">プラン作成</a></li>
                    <li><a href="{{ route('register') }}" class="text-gray-600 hover:text-gray-900 transition-colors">会員登録</a></li>
                    <li><a href="{{ route('login') }}" class="text-gray-600 hover:text-gray-900 transition-colors">ログイン</a></li>
                </ul>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-gray-900 tracking-wider uppercase mb-4">サポート</h3>
                <ul class="space-y-3">
                    <li><a href="#" class="text-gray-600 hover:text-gray-900 transition-colors">お問い合わせ</a></li>
                    <li><a href="{{ url('/#question') }}" class="text-gray-600 hover:text-gray-900 transition-colors">よくある質問</a></li>
                </ul>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-gray-900 tracking-wider uppercase mb-4">法的情報</h3>
                <ul class="space-y-3">
                    <li><a href="{{ url('/terms') }}" class="text-gray-600 hover:text-gray-900 transition-colors">利用規約</a></li>
                    <li><a href="{{ url('/policy')}}" class="text-gray-600 hover:text-gray-900 transition-colors">プライバシーポリシー</a></li>
                    {{-- <li><a href="#" class="text-gray-600 hover:text-gray-900 transition-colors">特定商取引法に基づく表記</a></li> --}}
                </ul>
            </div>
        </div>

        <!-- コピーライト -->
        <div class="mt-12 pt-8 border-t border-gray-200">
            <p class="text-gray-400 text-sm text-center">&copy; {{ date('Y') }} config('app.name'). All rights reserved.</p>
        </div>
    </div>
</footer>
