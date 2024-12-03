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
                        @if(!empty($plan['existing_planFiles']))
                            @foreach($plan['existing_planFiles'] as $existingFileIndex => $existingFile)
                                <div>
                                    <a href="{{ Storage::url($existingFile['path']) }}" target="_blank">{{ $existingFile['file_name'] }}</a>
                                    @error("plans.{{ $index }}.planFiles.{{ $existingFileIndex }}") <span
                                        class="text-red-500">{{ $message }}</span> @enderror
                                </div>
                                <button type="button" wire:click="removeExistingPlanFile({{ $index }}, {{ $existingFileIndex }})">
                                    ファイルを削除
                                </button>
                            @endforeach
                        @endif
                    </div>
                    <div>
                        @foreach($plan['planFiles'] as $fileIndex => $planFile)
                            <div>
                                <label for="plans.{{ $index }}.planFiles.{{ $fileIndex }}">ファイルアップロード</label>
                                <input type="file" wire:model="plans.{{ $index }}.planFiles.{{ $fileIndex }}">
                                @error("plans.{{ $index }}.planFiles.{{ $fileIndex }}") <span
                                    class="text-red-500">{{ $message }}</span> @enderror
                            </div>
                            <button type="button" wire:click="addPlanFiles({{ $index }})">ファイルを追加</button>
                            <button type="button" wire:click="removePlanFiles({{ $index }}, {{ $fileIndex }})">
                                ファイルを削除
                            </button>
                        @endforeach
                    </div>
                    <button type="button" wire:click="addPlan">プランを追加</button>
                    <button type="button" wire:click="removePlan({{ $index }})">プランを削除</button>
                </div>
            @endforeach
        </div>

        <div class="packing_lists">
            <h2>持ち物リスト</h2>

            @foreach($packingItems as $packingIndex => $packingItem)
                <div class="packingItem">
                    <div>
                        <input type="checkbox" wire:model.defer="packingItems.{{ $packingIndex }}.packing_is_checked">
                    </div>
                    <div>
                        <label for="packingItems.{{ $packingIndex }}.packing_name">名前</label>
                        <input type="text" id="packingItems.{{ $packingIndex }}.packing_name" wire:model.defer="packingItems.{{ $packingIndex }}.packing_name">
                        @error("packingItems.$packingIndex.packing_name") <span class="text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <button type="button" wire:click="removePackingItems({{ $packingIndex }})">持ち物を削除</button>
                    </div>
                </div>
            @endforeach
            <div>
                <button type="button" wire:click="addPackingItem">持ち物を追加</button>
            </div>
        </div>
        <button type="submit">更新する</button>
    </form>
</div>
