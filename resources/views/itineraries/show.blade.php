@extends('layouts.app')

@section('title')
    しおり閲覧 - {{ config('app.name') }}
@endsection

@section('meta')
    <meta name="title" content="しおり閲覧 - {{ config('app.name') }}">
    <meta name="description" content="{{ config('app.name') }}のしおり閲覧ページです。">
    <meta name="robots" content="noindex,nofollow">
@endsection

@section('content')
    <div class="bg-[#fffdfa]">
        <div class="max-w-4xl mx-auto p-4 pb-8 sm:p-6 sm:pb-12 dark:bg-gray-800 min-h-screen">
            <div class="mt-28">
                <div class="rounded-md bg-blue-50 p-4 text-sm text-blue-700">
                    閲覧用共有リンクで表示中です。この画面からは編集できません。
                </div>

                <section class="mt-6 bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-4 sm:p-8">
                    <p class="text-sm text-gray-500">しおり</p>
                    <h1 class="mt-2 text-3xl sm:text-4xl font-extrabold text-gray-900">
                        {{ $overview->title }}
                    </h1>

                    @if ($overview->overviewText)
                        <p class="mt-4 whitespace-pre-line text-gray-700">{{ $overview->overviewText }}</p>
                    @endif
                </section>

                <section class="mt-8 bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-4 sm:p-8">
                    <h2 class="text-2xl font-semibold text-gray-800">プラン</h2>

                    <div class="mt-6 space-y-5">
                        @forelse ($overview->plans->sortBy('order') as $plan)
                            <article class="rounded-lg border border-gray-200 p-4">
                                <div class="flex flex-wrap gap-3 text-sm text-gray-500">
                                    @if ($plan->date)
                                        <span>{{ \Illuminate\Support\Carbon::parse($plan->date)->format('Y-m-d') }}</span>
                                    @endif
                                    @if ($plan->time)
                                        <span>{{ substr($plan->time, 0, 5) }}</span>
                                    @endif
                                </div>

                                @if ($plan->plans_title)
                                    <h3 class="mt-2 text-lg font-semibold text-gray-900">{{ $plan->plans_title }}</h3>
                                @endif

                                @if ($plan->content)
                                    <p class="mt-2 whitespace-pre-line text-gray-700">{{ $plan->content }}</p>
                                @endif

                                @if ($plan->planFiles->isNotEmpty())
                                    <div class="mt-4 space-y-2">
                                        @foreach ($plan->planFiles as $planFile)
                                            <a href="{{ Storage::url($planFile->path) }}"
                                               target="_blank"
                                               class="block text-sm text-blue-600 hover:underline">
                                                {{ $planFile->file_name }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </article>
                        @empty
                            <p class="text-gray-500">プランはまだありません。</p>
                        @endforelse
                    </div>
                </section>

                <section class="mt-8 grid gap-8 md:grid-cols-2">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-4 sm:p-8">
                        <h2 class="text-2xl font-semibold text-gray-800">お土産</h2>
                        <div class="mt-4 space-y-2">
                            @forelse ($overview->souvenirs->sortBy('order') as $souvenir)
                                <div class="flex items-center gap-2 text-gray-700">
                                    <span class="h-2 w-2 rounded-full bg-indigo-500"></span>
                                    <span>{{ $souvenir->souvenir_name }}</span>
                                </div>
                            @empty
                                <p class="text-gray-500">お土産リストはまだありません。</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-4 sm:p-8">
                        <h2 class="text-2xl font-semibold text-gray-800">メモ</h2>
                        <div class="mt-4 space-y-4">
                            @forelse ($overview->additionalComments->sortBy('order') as $comment)
                                <article>
                                    @if ($comment->additionalComment_title)
                                        <h3 class="font-semibold text-gray-900">{{ $comment->additionalComment_title }}</h3>
                                    @endif
                                    @if ($comment->additionalComment_text)
                                        <p class="mt-1 whitespace-pre-line text-gray-700">{{ $comment->additionalComment_text }}</p>
                                    @endif
                                </article>
                            @empty
                                <p class="text-gray-500">メモはまだありません。</p>
                            @endforelse
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection
