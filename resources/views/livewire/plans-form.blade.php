<div>
    <form wire:submit.prevent="submit">
        <div class="mt-8">
            <div>
                <label for="title">タイトル</label>
                <input type="text" id="title" wire:model.defer="title">
                @error('title') <span class="text-red-500">{{ $message }}</span> @enderror
            </div>
            <div class="mt-4">
                <label for="overviewText">旅行概要</label>
                <textarea id="overviewText" wire:model.defer="overviewText"></textarea>
                @error('overviewText') <span class="text-red-500">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="mt-16">
            <h2 class="font-bold text-2xl text-center">プラン</h2>

            @foreach($plans as $index => $plan)
                <div class="mt-8">
                    <div class="mt-4">
                        <label for="plans.{{ $index }}.date">日付</label>
                        <input type="date" id="plans.{{ $index }}.date" wire:model.defer="plans.{{ $index }}.date">
                        @error("plans.$index.date") <span class="text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div class="mt-4">
                        <label for="plans.{{ $index }}.time">時間</label>
                        <input type="time" id="plans.{{ $index }}.time" wire:model.defer="plans.{{ $index }}.time">
                        @error("plans.$index.time") <span class="text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div class="mt-4">
                        <label for="plans.{{ $index }}.plans_title">タイトル</label>
                        <input type="text" id="plans.{{ $index }}.plans_title"
                               wire:model.defer="plans.{{ $index }}.plans_title">
                        @error("plans.$index.plans_title") <span class="text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div class="mt-4">
                        <label for="plans.{{ $index }}.content">内容</label>
                        <textarea id="plans.{{ $index }}.content"
                                  wire:model.defer="plans.{{ $index }}.content"></textarea>
                        @error("plans.$index.content") <span class="text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div class="mt-4">
                        @foreach($plan['planFiles'] as $fileIndex => $planFile)
                            <div>
                                <label for="plans.{{ $index }}.planFiles.{{ $fileIndex }}">ファイルアップロード</label>
                                <input type="file" wire:model="plans.{{ $index }}.planFiles.{{ $fileIndex }}">
                                @error("plans.{{ $index }}.planFiles.{{ $fileIndex }}") <span
                                    class="text-red-500">{{ $message }}</span> @enderror
                            </div>
                            <div class="mt-4">
                                <button type="button" wire:click="addPlanFiles({{ $index }})"
                                        class="border border-black bg-slate-200">ファイルを追加
                                </button>
                                <button type="button" wire:click="removePlanFiles({{ $index }}, {{ $fileIndex }})"
                                        class="border border-black bg-slate-200">
                                    ファイルを削除
                                </button>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4">
                        <button type="button" wire:click="addPlan" class="border border-black bg-slate-200">
                            プランを追加
                        </button>
                        <button type="button" wire:click="removePlan({{ $index }})"
                                class="border border-black bg-slate-200">プランを削除
                        </button>
                    </div>
                </div>
            @endforeach
        </div>


        <div class="mt-16">
            <h2 class="font-bold text-2xl text-center">持ち物リスト</h2>

            <div class="mt-8">
                <div>
                    <button type="button" wire:click="useTemplatePackingItems('domestic')"
                            class="border border-black bg-slate-200 active:bg-blue-700"
                            @if ($template_type === 'domestic') disabled @endif>テンプレートを使う(国内版)
                    </button>
                    <button type="button" wire:click="useTemplatePackingItems('overseas')"
                            class="border border-black bg-slate-200 active:bg-blue-700"
                            @if ($template_type === 'overseas') disabled @endif>テンプレートを使う(海外版)
                    </button>
                    <button type="button" wire:click="allRemovePackingItem"
                            onclick="return confirm('本当に全ての持ち物を削除しますか？')"
                            class="border border-black bg-slate-200">
                        持ち物を全て削除
                    </button>
                </div>
                @foreach($packingItems as $packingIndex => $packingItem)
                    <div class="mt-8">
                        <div>
                            <input type="checkbox"
                                   wire:model.defer="packingItems.{{ $packingIndex }}.packing_is_checked">
                            <label for="packingItems.{{ $packingIndex }}.packing_name">名前</label>
                            <input type="text" id="packingItems.{{ $packingIndex }}.packing_name"
                                   wire:model.defer="packingItems.{{ $packingIndex }}.packing_name">
                            @error("packingItems.$packingIndex.packing_name") <span
                                class="text-red-500">{{ $message }}</span> @enderror
                        </div>
                        <div class="mt-4">
                            <button type="button" wire:click="addPackingItem({{ $packingIndex }})"
                                    class="border border-black bg-slate-200">
                                持ち物を追加
                            </button>
                            <button type="button" wire:click="removePackingItem({{ $packingIndex }})"
                                    class="border border-black bg-slate-200">
                                持ち物を削除
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-16">
            <h2 class="font-bold text-2xl text-center">お土産リスト</h2>

            <div class="mt-8">
                <div>
                    <button type="button" wire:click="allRemoveSouvenir"
                            onclick="return confirm('本当に全てのお土産を削除しますか？')"
                            class="border border-black bg-slate-200">
                        お土産を全て削除
                    </button>
                </div>
                @foreach($souvenirs as $souvenirIndex => $souvenir)
                    <div class="mt-8">
                        <div>
                            <input type="checkbox"
                                   wire:model.defer="souvenirs.{{ $souvenirIndex }}.souvenir_is_checked">
                            <label for="souvenirs.{{ $souvenirIndex }}.souvenir_name">名前</label>
                            <input type="text" id="souvenirs.{{ $souvenirIndex }}.souvenir_name"
                                   wire:model.defer="souvenirs.{{ $souvenirIndex }}.souvenir_name">
                            @error("souvenirs.$souvenirIndex.souvenir_name") <span
                                class="text-red-500">{{ $message }}</span> @enderror
                        </div>
                        <div class="mt-4">
                            <button type="button" wire:click="addSouvenir({{ $souvenirIndex }})"
                                    class="border border-black bg-slate-200">
                                お土産を追加
                            </button>
                            <button type="button" wire:click="removeSouvenir({{ $souvenirIndex }})"
                                    class="border border-black bg-slate-200">
                                お土産を削除
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-16">
            <h2 class="font-bold text-2xl text-center">自由記述欄</h2>

            <div class="mt-8">
                @foreach($additionalComments as $additionalCommentIndex => $additionalComment)
                    <div class="mt-8">
                        <div>
                            <label
                                for="additionalComments.{{ $additionalCommentIndex }}.additionalComment_title">タイトル</label>
                            <input type="text"
                                   id="additionalComments.{{ $additionalCommentIndex }}.additionalComment_title"
                                   wire:model.defer="additionalComments.{{ $additionalCommentIndex }}.additionalComment_title">
                            @error("additionalComments.$additionalCommentIndex.additionalComment_title") <span
                                class="text-red-500">{{ $message }}</span> @enderror
                        </div>
                        <div class="mt-4">
                            <label
                                for="additionalComments.{{ $additionalCommentIndex }}.additionalComment_text">テキスト</label>
                            <textarea id="additionalComments.{{ $additionalCommentIndex }}.additionalComment_text"
                                      wire:model.defer="additionalComments.{{ $additionalCommentIndex }}.additionalComment_text"></textarea>
                            @error("additionalComments.$additionalCommentIndex.additionalComment_text") <span
                                class="text-red-500">{{ $message }}</span> @enderror
                        </div>
                        <div class="mt-4">
                            <button type="button" wire:click="addAdditionalComment({{ $additionalCommentIndex }})"
                                    class="border border-black bg-slate-200">
                                自由記述欄を追加
                            </button>
                            <button type="button" wire:click="removeAdditionalComment({{ $additionalCommentIndex }})"
                                    class="border border-black bg-slate-200">
                                自由記述欄を削除
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <button type="submit" class="mt-8 border border-black bg-slate-200">作成する</button>
    </form>
</div>
