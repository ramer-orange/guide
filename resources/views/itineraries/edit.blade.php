<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>編集ページ</title>
</head>
<body>
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
    <h1>しおりを編集</h1>

{{--    <form action="{{ route('itineraries.update', $overview->id) }}" method="post">--}}
{{--        @csrf--}}
{{--        @method('PUT')--}}
{{--        <div class="overview">--}}
{{--            <div>--}}
{{--                <label for="title">タイトル</label>--}}
{{--                <input type="text" id="title" name="title" value="{{ old('title', $overview->title) }}">--}}
{{--                @error('title')--}}
{{--                <div class="alert alert-danger">{{ $message }}</div>--}}
{{--                @enderror--}}
{{--            </div>--}}
{{--            <div>--}}
{{--                <label for="overview">旅行概要</label>--}}
{{--                <textarea id="overview" name="overview">{{ old('overview', $overview->overview) }}</textarea>--}}
{{--                @error('overview')--}}
{{--                <div class="alert alert-danger">{{ $message }}</div>--}}
{{--                @enderror--}}
{{--            </div>--}}
{{--        </div>--}}
{{--        <div class="plans">--}}
{{--            @foreach($overview->plans as $plan)--}}
{{--                <div class="plan">--}}
{{--                    <div>--}}
{{--                        <label for="plans[{{ $plan->id }}][date]">日付</label>--}}
{{--                        <input type="date" id="plans[{{ $plan->id }}][date]" name="plans[{{ $plan->id }}][date]" value="{{ old("plans.$plan->id.date", $plan->date) }}">--}}
{{--                        @error("plans.$plan->id.date")--}}
{{--                        <div class="alert alert-danger">{{ $message }}</div>--}}
{{--                        @enderror--}}
{{--                    </div>--}}
{{--                    <div>--}}
{{--                        <label for="plans[{{ $plan->id }}][time]">時間</label>--}}
{{--                        <input type="time" id="plans[{{ $plan->id }}][time]" name="plans[{{ $plan->id }}][time]" value="{{ old("plans.$plan->id.time", \Carbon\Carbon::parse($plan->time)->format('H:i'))  }}">--}}
{{--                        @error("plans.$plan->id.time")--}}
{{--                        <div class="alert alert-danger">{{ $message }}</div>--}}
{{--                        @enderror--}}
{{--                    </div>--}}
{{--                    <div>--}}
{{--                        <label for="plans[{{ $plan->id }}][plans_title]">タイトル</label>--}}
{{--                        <input type="text" id="plans[{{ $plan->id }}][plans_title]" name="plans[{{ $plan->id }}][plans_title]" value="{{ old("plans.$plan->id.plans_title", $plan->plans_title) }}">--}}
{{--                        @error("plans.$plan->id.plans_title")--}}
{{--                        <div class="alert alert-danger">{{ $message }}</div>--}}
{{--                        @enderror--}}
{{--                    </div>--}}
{{--                    <div>--}}
{{--                        <label for="plans[{{ $plan->id }}][content]">内容</label>--}}
{{--                        <textarea id="plans[{{ $plan->id }}][content]" name="plans[{{ $plan->id }}][content]">{{ old("plans.$plan->id.content", $plan->content) }}</textarea>--}}
{{--                        @error("plans.$plan->id.content")--}}
{{--                        <div class="alert alert-danger">{{ $message }}</div>--}}
{{--                        @enderror--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--            @endforeach--}}
{{--        </div>--}}
{{--        <button type="submit">更新する</button>--}}
{{--    </form>--}}
    <livewire:edit-plans-form />

    <a href="{{ route('itineraries.index') }}">一覧を見る</a>
</main>
</body>
</html>

