<div class="max-w-4xl mx-auto">
    <form wire:submit.prevent="submit" enctype="multipart/form-data">
        @csrf
        <!-- タイトルと概要 -->
        <div class="space-y-6 bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-3 sm:p-8 mb-8">
            <div>
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700">タイトル</label>
                    <input type="text" id="title" wire:model.defer="title"
                           class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-xs focus:outline-hidden focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="フランス旅行">
                    @error('title')
                    <span class="text-red-500 text-sm error-message">{{ $message }}</span>
                    @enderror
                </div>
                <div class="mt-6">
                    <label for="overviewText" class="block text-sm font-medium text-gray-700">旅行概要</label>
                    <textarea id="overviewText" wire:model.defer="overviewText"
                              class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-xs focus:outline-hidden focus:ring-indigo-500 focus:border-indigo-500 field-sizing-content"
                              placeholder="美食を求める旅"></textarea>
                    @error('overviewText')
                    <span class="text-red-500 text-sm error-message">{{ $message }}</span>
                    @enderror
                </div>
            </div>
        </div>

        <!-- プランセクション -->
        <seciton>
            <div class="mt-12 bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-3 sm:p-8 mb-8">
                <h2 class="flex items-center justify-center text-2xl font-semibold text-gray-800">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" class="w-6 h-6 mr-2 text-yellow-600">
                        <path
                            d="M51.7 295.1l31.7 6.3c7.9 1.6 16-.9 21.7-6.6l15.4-15.4c11.6-11.6 31.1-8.4 38.4 6.2l9.3 18.5c4.8 9.6 14.6 15.7 25.4 15.7c15.2 0 26.1-14.6 21.7-29.2l-6-19.9c-4.6-15.4 6.9-30.9 23-30.9l2.3 0c13.4 0 25.9-6.7 33.3-17.8l10.7-16.1c5.6-8.5 5.3-19.6-.8-27.7l-16.1-21.5c-10.3-13.7-3.3-33.5 13.4-37.7l17-4.3c7.5-1.9 13.6-7.2 16.5-14.4l16.4-40.9C303.4 52.1 280.2 48 256 48C141.1 48 48 141.1 48 256c0 13.4 1.3 26.5 3.7 39.1zm407.7 4.6c-3-.3-6-.1-9 .8l-15.8 4.4c-6.7 1.9-13.8-.9-17.5-6.7l-2-3.1c-6-9.4-16.4-15.1-27.6-15.1s-21.6 5.7-27.6 15.1l-6.1 9.5c-1.4 2.2-3.4 4.1-5.7 5.3L312 330.1c-18.1 10.1-25.5 32.4-17 51.3l5.5 12.4c8.6 19.2 30.7 28.5 50.5 21.1l2.6-1c10-3.7 21.3-2.2 29.9 4.1l1.5 1.1c37.2-29.5 64.1-71.4 74.4-119.5zM0 256a256 256 0 1 1 512 0A256 256 0 1 1 0 256zm144.5 92.1c-2.1 8.6 3.1 17.3 11.6 19.4l32 8c8.6 2.1 17.3-3.1 19.4-11.6s-3.1-17.3-11.6-19.4l-32-8c-8.6-2.1-17.3 3.1-19.4 11.6zm92-20c-2.1 8.6 3.1 17.3 11.6 19.4s17.3-3.1 19.4-11.6l8-32c2.1-8.6-3.1-17.3-11.6-19.4s-17.3 3.1-19.4 11.6l-8 32zM343.2 113.7c-7.9-4-17.5-.7-21.5 7.2l-16 32c-4 7.9-.7 17.5 7.2 21.5s17.5 .7 21.5-7.2l16-32c4-7.9 .7-17.5-7.2-21.5z"/>
                    </svg>
                    プラン
                </h2>
                <div class="mt-8 p-2 md:p-6 bg-gray-50 rounded-lg shadow-inner relative pt-6">
                    <div wire:sortable="updatePlanOrder" wire:sortable.options="{ animation: 100, scroll: false  }"
                         class="flex gap-4 flex-col">
                        @foreach($plans as $index => $plan)
                            <div wire:sortable.item="{{ $plan['id'] }}"
                                 wire:key="plan-{{ $plan['id'] }}">
                                <div wire:sortable.handle class="cursor-grab">
                                    <div>
                                        <div class="translate-x-1 -translate-y-2 md:-translate-x-2 md:-translate-y-2">
                                            <x-button.drag-button></x-button.drag-button>
                                        </div>
                                        <!-- 日付と時間 -->
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div>
                                                <label for="plans.{{ $index }}.date"
                                                       class="block text-sm font-medium text-gray-700">日付</label>
                                                <input type="date" id="plans.{{ $index }}.date"
                                                       wire:model.defer="plans.{{ $index }}.date"
                                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-xs focus:outline-hidden focus:ring-indigo-500 focus:border-indigo-500">
                                                @error("plans.$index.date")
                                                <span class="text-red-500 text-sm error-message">{{ $message }}</span>
                                                @enderror
                                            </div>
                                            <div>
                                                <label for="plans.{{ $index }}.time"
                                                       class="block text-sm font-medium text-gray-700">時間</label>
                                                <input type="time" id="plans.{{ $index }}.time"
                                                       wire:model.defer="plans.{{ $index }}.time"
                                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-xs focus:outline-hidden focus:ring-indigo-500 focus:border-indigo-500">
                                                @error("plans.$index.time")
                                                <span class="text-red-500 text-sm error-message">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>

                                        <!-- タイトル -->
                                        <div class="mt-6">
                                            <label for="plans.{{ $index }}.plans_title"
                                                   class="block text-sm font-medium text-gray-700">プランタイトル</label>
                                            <input type="text" id="plans.{{ $index }}.plans_title"
                                                   wire:model.defer="plans.{{ $index }}.plans_title"
                                                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-xs focus:outline-hidden focus:ring-indigo-500 focus:border-indigo-500"
                                                   placeholder="東京駅発">
                                            @error("plans.$index.plans_title")
                                            <span class="text-red-500 text-sm error-message">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <!-- 内容 -->
                                        <div class="mt-4">
                                            <label for="plans.{{ $index }}.content"
                                                   class="block text-sm font-medium text-gray-700">プラン内容</label>
                                            <textarea id="plans.{{ $index }}.content"
                                                      wire:model.defer="plans.{{ $index }}.content"
                                                      class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-xs focus:outline-hidden focus:ring-indigo-500 focus:border-indigo-500 field-sizing-content"
                                                      placeholder="成田エクスプレスで成田空港に向かう"></textarea>
                                            @error("plans.$index.content")
                                            <span class="text-red-500 text-sm error-message">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <!-- ファイルアップロード -->
                                        <div class="mt-6">
                                            <h3 class="text-sm font-medium text-gray-700">ファイルアップロード (10MB以下)</h3>
                                            @foreach($plan['planFiles'] as $fileIndex => $planFile)
                                                <div class="flex items-center mt-1">
                                                    <!-- inputをhiddenで隠す -->
                                                    <input
                                                        type="file"
                                                        wire:model="plans.{{ $index }}.planFiles.{{ $fileIndex }}"
                                                        id="file-{{ $index }}-{{ $fileIndex }}"
                                                        class="hidden"/>

                                                    <!-- labelでクリック可能エリアを作る -->
                                                    <label
                                                        for="file-{{ $index }}-{{ $fileIndex }}"
                                                        class="flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-xs cursor-pointer focus:outline-hidden focus:ring-indigo-500 focus:border-indigo-500">
                                                        @if(isset($plans[$index]['planFiles'][$fileIndex]) && $plans[$index]['planFiles'][$fileIndex])
                                                            <!-- ファイルが選択済みの場合、ファイル名を表示 -->
                                                            {{ $plans[$index]['planFiles'][$fileIndex]->getClientOriginalName() }}
                                                        @else
                                                            <!-- ファイル未選択時の表示 -->
                                                            <span class="flex items-center">
                                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                                     class="h-5 w-5 ml-2 text-gray-500" fill="none"
                                                                     viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                          stroke-width="2"
                                                                          d="M12 4v16m8-8H4"/>
                                                                </svg>
                                                                ファイルを選択する
                                                            </span>
                                                        @endif
                                                    </label>
                                                    @error("plans.$index.planFiles.$fileIndex")
                                                    <span
                                                        class="text-red-500 text-sm ml-2 error-message">{{ $message }}</span>
                                                    @enderror

                                                    <button type="button"
                                                            class="text-red-600 hover:text-red-900 transition duration-150 transform hover:scale-110 ml-4"
                                                            wire:click="removePlanFiles({{ $index }}, {{ $fileIndex }})"
                                                            aria-label="削除"
                                                            title="削除">
                                                        <x-button.trash-button></x-button.trash-button>
                                                    </button>
                                                </div>
                                            @endforeach

                                            <!-- ファイル追加ボタン -->
                                            <div class="mt-4">
                                                <button type="button" wire:click="addPlanFiles({{ $index }})">
                                                    <x-button.addFile-button>ファイル追加</x-button.addFile-button>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 各種ボタン -->
                                    <div class="mt-4 sp2:mt-8 flex justify-around sp2:justify-center gap-2 sp2:gap-6">
                                        <!-- プラン追加ボタン -->
                                        <button type="button" wire:click="addPlan({{ $index }})">
                                            <x-button.button1>
                                                プランを追加
                                            </x-button.button1>
                                        </button>
                                        <!-- プラン削除ボタン -->
                                        <button type="button" wire:click="removePlan({{ $index }})">
                                            <x-button.button1>
                                                プランを削除
                                            </x-button.button1>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </seciton>

        <!-- 持ち物リストセクション -->
        <section>
            <div class="mt-12 bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-3 sm:p-8 mb-8">
                <h2 class="flex items-center justify-center text-2xl font-semibold text-gray-800">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"
                         class="w-6 h-6 mr-2 text-indigo-600">
                        <path
                            d="M160 112c0-35.3 28.7-64 64-64s64 28.7 64 64l0 48-128 0 0-48zm-48 48l-64 0c-26.5 0-48 21.5-48 48L0 416c0 53 43 96 96 96l256 0c53 0 96-43 96-96l0-208c0-26.5-21.5-48-48-48l-64 0 0-48C336 50.1 285.9 0 224 0S112 50.1 112 112l0 48zm24 48a24 24 0 1 1 0 48 24 24 0 1 1 0-48zm152 24a24 24 0 1 1 48 0 24 24 0 1 1 -48 0z"/>
                    </svg>
                    持ち物リスト
                </h2>
                <div class="mt-6 space-y-6">
                    <div class="p-2 pt-6 pb-6 md:p-6 bg-gray-50 rounded-lg shadow-inner">
                        <div class="flex flex-col sp2:flex-row justify-center items-center gap-2 sp:gap-4">
                            <!-- テンプレートボタン(国内版) -->
                            <button type="button" wire:click="useTemplatePackingItems('domestic')"
                                    class="relative inline-flex items-center px-4 py-2 font-medium text-sm text-white bg-blue-500 border-b-2 border-blue-700 rounded-full hover:bg-blue-600 hover:border-b border-blue-600 transform hover:-translate-y-1 transition-all duration-300 ease-out disabled:opacity-50 disabled:cursor-not-allowed group cursor-pointer"
                                    @if ($template_type === 'domestic') disabled @endif
                                    aria-label="テンプレートを使う(国内版)" title="テンプレートを使う(国内版)">
                                テンプレートを使う(国内版)
                                <i class="fas fa-angle-right ml-2 transition-transform duration-300 ease-out group-hover:translate-x-2"></i>
                            </button>
                            <!-- テンプレートボタン(海外版) -->
                            <button type="button" wire:click="useTemplatePackingItems('overseas')"
                                    class="relative inline-flex items-center px-4 py-2 font-medium text-sm text-white bg-blue-500 border-b-2 border-blue-700 rounded-full hover:bg-blue-600 hover:border-b border-blue-600 transform hover:-translate-y-1 transition-all duration-300 ease-out disabled:opacity-50 disabled:cursor-not-allowed group cursor-pointer"
                                    @if ($template_type === 'overseas') disabled @endif
                                    aria-label="テンプレートを使う(海外版)" title="テンプレートを使う(海外版)">
                                テンプレートを使う(海外版)
                                <i class="fas fa-angle-right ml-2 transition-transform duration-300 ease-out group-hover:translate-x-2"></i>
                            </button>
                        </div>

                        <div class="mt-4">
                            <button type="button" wire:click="allRemovePackingItem">
                                <x-button.allRemove-button>全て削除</x-button.allRemove-button>
                            </button>
                        </div>
                        <div wire:sortable="updatePackingItemOrder"
                             wire:sortable.options="{ animation: 100, scroll: false  }">
                            @foreach($packingItems as $packingItem)
                                <div wire:sortable.item="{{ $packingItem['id'] }}"
                                     wire:key="packingItem-{{ $packingItem['id'] }}">
                                    <div wire:sortable.handle class="cursor-grab">
                                        <div class="flex items-center space-x-1.5 sp:space-x-4 mt-4">
                                            <div>
                                                <x-button.drag-button></x-button.drag-button>
                                            </div>
                                            <input type="checkbox"
                                                   wire:model.defer="packingItems.{{ $loop->index }}.packing_is_checked"
                                                   class="h-4 w-4 text-indigo-600 border-gray-300 rounded-sm">
                                            <input type="text" id="packingItems.{{ $loop->index }}.packing_name"
                                                   wire:model.defer="packingItems.{{ $loop->index }}.packing_name"
                                                   placeholder="持ち物の名前"
                                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-xs focus:outline-hidden focus:ring-indigo-500 focus:border-indigo-500">
                                            @error("packingItems.$loop->index.packing_name")
                                            <span class="text-red-500 text-sm error-message">{{ $message }}</span>
                                            @enderror
                                            <button type="button"
                                                    class="text-red-600 hover:text-red-900 transition duration-150 transform hover:scale-110 ml-4 hidden sp2:block"
                                                    wire:click="removePackingItem({{ $loop->index }})"
                                                    aria-label="削除"
                                                    title="削除">
                                                <x-button.trash-button></x-button.trash-button>
                                            </button>
                                        </div>
                                        <!-- 持ち物追加ボタン -->
                                        <div class="ml-2 mt-2 sp2:mt-4 flex gap-2">
                                            <button type="button" wire:click="addPackingItem({{ $loop->index }})">
                                                <x-button.plus-button></x-button.plus-button>
                                            </button>
                                            <button type="button"
                                                    class="text-red-600 hover:text-red-900 transition duration-150 transform hover:scale-110 sp2:hidden"
                                                    wire:click="removePackingItem({{ $loop->index }})"
                                                    aria-label="削除"
                                                    title="削除">
                                                <x-button.trash-button></x-button.trash-button>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- お土産リストセクション -->
        <section>
            <div class="mt-12 bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-3 sm:p-8 mb-8">
                <h2 class="flex items-center justify-center text-2xl font-semibold text-gray-800">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"
                         class="w-6 h-6 mr-2 text-yellow-600">
                        <path
                            d="M547.6 103.8L490.3 13.1C485.2 5 476.1 0 466.4 0L109.6 0C99.9 0 90.8 5 85.7 13.1L28.3 103.8c-29.6 46.8-3.4 111.9 51.9 119.4c4 .5 8.1 .8 12.1 .8c26.1 0 49.3-11.4 65.2-29c15.9 17.6 39.1 29 65.2 29c26.1 0 49.3-11.4 65.2-29c15.9 17.6 39.1 29 65.2 29c26.2 0 49.3-11.4 65.2-29c16 17.6 39.1 29 65.2 29c4.1 0 8.1-.3 12.1-.8c55.5-7.4 81.8-72.5 52.1-119.4zM499.7 254.9c0 0 0 0-.1 0c-5.3 .7-10.7 1.1-16.2 1.1c-12.4 0-24.3-1.9-35.4-5.3L448 384l-320 0 0-133.4c-11.2 3.5-23.2 5.4-35.6 5.4c-5.5 0-11-.4-16.3-1.1l-.1 0c-4.1-.6-8.1-1.3-12-2.3L64 384l0 64c0 35.3 28.7 64 64 64l320 0c35.3 0 64-28.7 64-64l0-64 0-131.4c-4 1-8 1.8-12.3 2.3z"/>
                    </svg>
                    お土産リスト
                </h2>
                <div class="mt-6 space-y-6">
                    <div class="p-2 pt-6 pb-6 md:p-6 bg-gray-50 rounded-lg shadow-inner">
                        <div>
                            <button type="button" wire:click="allRemoveSouvenir">
                                <x-button.allRemove-button>全て削除</x-button.allRemove-button>
                            </button>
                        </div>
                        <div wire:sortable="updateSouvenirOrder"
                             wire:sortable.options="{ animation: 100, scroll: false }">
                            @foreach($souvenirs as $souvenir)
                                <div wire:sortable.item="{{ $souvenir['id'] }}"
                                     wire:key="souvenir-{{ $souvenir['id'] }}">
                                    <div wire:sortable.handle class="cursor-grab">
                                        <div class="flex items-center space-x-1.5 sp:space-x-4 mt-4">
                                            <div>
                                                <x-button.drag-button></x-button.drag-button>
                                            </div>
                                            <input type="checkbox"
                                                   wire:model.defer="souvenirs.{{ $loop->index }}.souvenir_is_checked"
                                                   class="h-4 w-4 text-indigo-600 border-gray-300 rounded-sm">
                                            <input type="text" id="souvenirs.{{ $loop->index }}.souvenir_name"
                                                   wire:model.defer="souvenirs.{{ $loop->index }}.souvenir_name"
                                                   placeholder="お土産の名前"
                                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-xs focus:outline-hidden focus:ring-indigo-500 focus:border-indigo-500">
                                            @error("souvenirs.$loop->index.souvenir_name")
                                            <span class="text-red-500 text-sm error-message">{{ $message }}</span>
                                            @enderror
                                            <button type="button"
                                                    class="text-red-600 hover:text-red-900 transition duration-150 transform hover:scale-110 sp2:ml-4 hidden sp2:block"
                                                    wire:click="removeSouvenir({{ $loop->index }})"
                                                    aria-label="削除"
                                                    title="削除">
                                                <x-button.trash-button></x-button.trash-button>
                                            </button>
                                        </div>
                                        <!-- お土産追加ボタン -->
                                        <div class="ml-2 mt-2 sp2:mt-4 flex gap-2">
                                            <button type="button" wire:click="addSouvenir({{ $loop->index }})">
                                                <x-button.plus-button></x-button.plus-button>
                                            </button>
                                            <button type="button"
                                                    class="text-red-600 hover:text-red-900 transition duration-150 transform hover:scale-110 sp2:hidden"
                                                    wire:click="removeSouvenir({{ $loop->index }})"
                                                    aria-label="削除"
                                                    title="削除">
                                                <x-button.trash-button></x-button.trash-button>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- メモ欄セクション -->
        <seciton>
            <div class="mt-12 bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-3 sm:p-8 mb-8">
                <h2 class="flex items-center justify-center text-2xl font-semibold text-gray-800">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" class="w-6 h-6 mr-2 text-yellow-600">
                        <path
                            d="M362.7 19.3L314.3 67.7 444.3 197.7l48.4-48.4c25-25 25-65.5 0-90.5L453.3 19.3c-25-25-65.5-25-90.5 0zm-71 71L58.6 323.5c-10.4 10.4-18 23.3-22.2 37.4L1 481.2C-1.5 489.7 .8 498.8 7 505s15.3 8.5 23.7 6.1l120.3-35.4c14.1-4.2 27-11.8 37.4-22.2L421.7 220.3 291.7 90.3z"/>
                    </svg>
                    メモ欄
                </h2>
                <div class="mt-6 space-y-6">
                    <div class="p-2 pt-6 pb-6 md:p-6 bg-gray-50 rounded-lg shadow-inner">
                        <div wire:sortable="updateAdditionalCommentsOrder"
                             wire:sortable.options="{ animation: 100, scroll: false }" class="flex gap-4 flex-col">
                            @foreach($additionalComments as $additionalComment)
                                <div wire:sortable.item="{{ $additionalComment['id'] }}"
                                     wire:key="additionalComment-{{ $additionalComment['id'] }}">
                                    <div wire:sortable.handle class="cursor-grab">
                                        <div class="translate-x-1 -translate-y-2 md:-translate-x-2 md:-translate-y-2">
                                            <x-button.drag-button></x-button.drag-button>
                                        </div>
                                        <div>
                                            <div>
                                                <label
                                                    for="additionalComments.{{ $loop->index }}.additionalComment_title"
                                                    class="block text-sm font-medium text-gray-700">タイトル</label>
                                                <input type="text"
                                                       id="additionalComments.{{ $loop->index }}.additionalComment_title"
                                                       wire:model.defer="additionalComments.{{ $loop->index }}.additionalComment_title"
                                                       placeholder="タイトル"
                                                       class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-xs focus:outline-hidden focus:ring-indigo-500 focus:border-indigo-500">
                                                @error("additionalComments.$loop->index.additionalComment_title")
                                                <span class="text-red-500 text-sm error-message">{{ $message }}</span>
                                                @enderror
                                            </div>
                                            <div class="mt-4">
                                                <label
                                                    for="additionalComments.{{ $loop->index }}.additionalComment_text"
                                                    class="block text-sm font-medium text-gray-700">テキスト</label>
                                                <textarea
                                                    id="additionalComments.{{ $loop->index }}.additionalComment_text"
                                                    wire:model.defer="additionalComments.{{ $loop->index }}.additionalComment_text"
                                                    placeholder="テキスト"
                                                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-xs focus:outline-hidden focus:ring-indigo-500 focus:border-indigo-500 field-sizing-content"></textarea>
                                                @error("additionalComments.$loop->index.additionalComment_text")
                                                <span class="text-red-500 text-sm error-message">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                        <div
                                            class="mt-4 sp2:mt-8 flex justify-around sp2:justify-center gap-2 sp2:gap-6">
                                            <!-- メモ欄追加ボタン -->
                                            <button type="button" wire:click="addAdditionalComment({{ $loop->index }})">
                                                <x-button.button1>
                                                    メモを追加
                                                </x-button.button1>
                                            </button>
                                            <!-- メモ削除ボタン -->
                                            <button type="button"
                                                    wire:click="removeAdditionalComment({{ $index }})">
                                                <x-button.button1>
                                                    メモを削除
                                                </x-button.button1>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </seciton>

        <!-- 共有パスワード -->
        @auth
            <section>
                <div class="mt-12 bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-3 sm:p-8 mb-8">
                    <h2 class="flex items-center justify-center text-2xl font-semibold text-gray-800">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"
                             class="w-6 h-6 mr-2 text-indigo-600">
                            <path
                                d="M336 352c97.2 0 176-78.8 176-176S433.2 0 336 0S160 78.8 160 176c0 18.7 2.9 36.8 8.3 53.7L7 391c-4.5 4.5-7 10.6-7 17l0 80c0 13.3 10.7 24 24 24l80 0c13.3 0 24-10.7 24-24l0-40 40 0c13.3 0 24-10.7 24-24l0-40 40 0c6.4 0 12.5-2.5 17-7l33.3-33.3c16.9 5.4 35 8.3 53.7 8.3zM376 96a40 40 0 1 1 0 80 40 40 0 1 1 0-80z"/>
                        </svg>
                        共有パスワード設定
                    </h2>
                    <p class="flex items-center justify-center text-base text-gray-800 mt-4">
                        共有パスワードは他の誰かと共同編集したいときなどに使います。
                    </p>

                    <!-- パスワード入力欄 -->
                    <div class="mt-6 space-y-6">
                        <div class="p-2 pt-6 pb-6 md:p-6 bg-gray-50 rounded-lg shadow-inner">
                            <div>
                                <p class="text-sm text-gray-500">※8文字~32文字で設定してください。</p>
                                <label for="shared_password"
                                       class="block text-sm font-medium text-gray-700 mt-4">共有パスワード</label>
                                <input type="password" id="shared_password"
                                       wire:model.defer="shared_password"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                @error('shared_password')
                                <span class="text-red-500 text-sm error-message">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- 確認用入力欄 -->
                            <div class="mt-6">
                                <label for="shared_password_confirmation"
                                       class="block text-sm font-medium text-gray-700">共有パスワード（確認用）</label>
                                <input type="password" id="shared_password_confirmation"
                                       wire:model.defer="shared_password_confirmation"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                @error('shared_password_confirmation')
                                <span class="text-red-500 text-sm error-message">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        @endauth

        <!-- 送信ボタン -->
        <div class="mt-6 flex justify-center">
            <x-button.button2>
                作成する
            </x-button.button2>
        </div>
    </form>
</div>
