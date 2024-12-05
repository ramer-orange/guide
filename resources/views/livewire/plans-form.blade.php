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
                        @foreach($plan['planFiles'] as $fileIndex => $planFile)
                            <div>
                                <label for="plans.{{ $index }}.planFiles.{{ $fileIndex }}">ファイルアップロード</label>
                                <input type="file" wire:model="plans.{{ $index }}.planFiles.{{ $fileIndex }}">
                                @error("plans.{{ $index }}.planFiles.{{ $fileIndex }}") <span
                                    class="text-red-500">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <button type="button" wire:click="addPlanFiles({{ $index }})">ファイルを追加</button>
                                <button type="button" wire:click="removePlanFiles({{ $index }}, {{ $fileIndex }})">
                                    ファイルを削除
                                </button>
                            </div>
                        @endforeach
                    </div>
                    <div>
                        <button type="button" wire:click="addPlan">プランを追加</button>
                        <button type="button" wire:click="removePlan({{ $index }})">プランを削除</button>
                    </div>
                </div>
            @endforeach
        </div>


        <div class="packing_lists_wrap">
            <h2>持ち物リスト</h2>

            <div class="packing_lists">
                <div>
                    <div>
                        <button type="button" wire:click="useTemplatePackingItems('domestic')">テンプレートを使う(国内版)
                        </button>
                        <button type="button" wire:click="useTemplatePackingItems('overseas')">テンプレートを使う(海外版)
                        </button>
                    </div>
                </div>
                <div class="packing_template">
                    <div class="packing_template_body">
                        @foreach($packingItems as $packingIndex => $packingItem)
                            <div class="packingItem">
                                <div>
                                    <input type="checkbox"
                                           wire:model.defer="packingItems.{{ $packingIndex }}.packing_is_checked">
                                </div>
                                <div>
                                    <label for="packingItems.{{ $packingIndex }}.packing_name">名前</label>
                                    <input type="text" id="packingItems.{{ $packingIndex }}.packing_name"
                                           wire:model.defer="packingItems.{{ $packingIndex }}.packing_name">
                                    @error("packingItems.$packingIndex.packing_name") <span
                                        class="text-red-500">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <button type="button" wire:click="removePackingItem({{ $packingIndex }})">
                                        持ち物を削除
                                    </button>
                                </div>
                            </div>
                        @endforeach
                        <div>
                            <button type="button" wire:click="addPackingItem">持ち物を追加</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <button type="submit">作成する</button>
    </form>
</div>
