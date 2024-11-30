<div>
    <form wire:submit.prevent="submit">
        <div class="overview">
            <div>
                <label for="title">タイトル</label>
                <input type="text" id="title" wire:model.defer="title">
                @error('title') <span class="text-red-500">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="overviewText">旅行概要</label>
                <textarea id="overviewText" wire:model.defer="overviewText"></textarea>
                @error('overviewText') <span class="text-red-500">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="plans">
            <h2>プラン</h2>

            @foreach($plans as $index => $plan)
                <div class="plan">
                    <div>
                        <label for="plans.{{ $index }}.date">日付</label>
                        <input type="date" id="plans.{{ $index }}.date" wire:model.defer="plans.{{ $index }}.date">
                        @error("plans.$index.date") <span class="text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="plans.{{ $index }}.time">時間</label>
                        <input type="time" id="plans.{{ $index }}.time" wire:model.defer="plans.{{ $index }}.time">
                        @error("plans.$index.time") <span class="text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="plans.{{ $index }}.plans_title">タイトル</label>
                        <input type="text" id="plans.{{ $index }}.plans_title"
                               wire:model.defer="plans.{{ $index }}.plans_title">
                        @error("plans.$index.plans_title") <span class="text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="plans.{{ $index }}.content">内容</label>
                        <textarea id="plans.{{ $index }}.content"
                                  wire:model.defer="plans.{{ $index }}.content"></textarea>
                        @error("plans.$index.content") <span class="text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="plans.{{ $index }}.planFiles">ファイルアップロード</label>
                        <input type="file" wire:model="plans.{{ $index }}.planFiles">
                        @error("plans.$index.planFiles") <span class="text-red-500">{{ $message }}</span> @enderror
                    </div>
                    {{--                    @foreach($plans[$index]['planFiles'] as $fileIndex => $planFile)--}}
                    {{--                        <div>--}}
                    {{--                            <input type="file" wire:model="planFiles.{{ $fileIndex }}">--}}
                    {{--                            @error("planFiles.$fileIndex") <span class="error">{{ $message }}</span> @enderror--}}
                    {{--                        </div>--}}
                    {{--                    @endforeach--}}
                    <button type="button" wire:click="removePlan({{ $index }})">削除</button>
                    {{--                    <livewire:upload-planFiles />--}}
                </div>
            @endforeach
        </div>

        <button type="button" wire:click="addPlan">プランを追加</button>
        <button type="submit">作成する</button>
    </form>
</div>
