<div class="max-w-4xl mx-auto">
    <form wire:submit.prevent="submit">
        <!-- タイトルと概要 -->
        <div class="space-y-6 bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-3 sm:p-8 mb-8">
            <div>
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700">タイトル</label>
                    <input type="text" id="title" wire:model.defer="title"
                           class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-xs focus:outline-hidden focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="フランス旅行">
                    @error('title')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>
                <div class="mt-6">
                    <label for="overviewText" class="block text-sm font-medium text-gray-700">旅行概要</label>
                    <textarea id="overviewText" wire:model.defer="overviewText"
                              class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-xs focus:outline-hidden focus:ring-indigo-500 focus:border-indigo-500 field-sizing-content"
                              placeholder="美食を求める旅"></textarea>
                    @error('overviewText')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
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
                <div class="mt-8 p-2 md:p-6 bg-gray-50 rounded-lg shadow-inner relative">
                    @foreach($plans as $index => $plan)
                        <!-- 日付と時間 -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="plans.{{ $index }}.date"
                                       class="block text-sm font-medium text-gray-700">日付</label>
                                <input type="date" id="plans.{{ $index }}.date"
                                       wire:model.defer="plans.{{ $index }}.date"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-xs focus:outline-hidden focus:ring-indigo-500 focus:border-indigo-500">
                                @error("plans.$index.date")
                                <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label for="plans.{{ $index }}.time"
                                       class="block text-sm font-medium text-gray-700">時間</label>
                                <input type="time" id="plans.{{ $index }}.time"
                                       wire:model.defer="plans.{{ $index }}.time"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-xs focus:outline-hidden focus:ring-indigo-500 focus:border-indigo-500">
                                @error("plans.$index.time")
                                <span class="text-red-500 text-sm">{{ $message }}</span>
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
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- 内容 -->
                        <div class="mt-4">
                            <label for="plans.{{ $index }}.content"
                                   class="block text-sm font-medium text-gray-700">プラン内容</label>
                            <textarea id="plans.{{ $index }}.content" wire:model.defer="plans.{{ $index }}.content"
                                      class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-xs focus:outline-hidden focus:ring-indigo-500 focus:border-indigo-500 field-sizing-content"
                                      placeholder="成田エクスプレスで成田空港に向かう"></textarea>
                            @error("plans.$index.content")
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- ファイルアップロード -->
                        <div class="mt-6">
                            <h3 class="text-sm font-medium text-gray-700">ファイルアップロード</h3>
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
                                    @error("plans.{$index}.planFiles.{$fileIndex}")
                                    <span class="text-red-500 text-sm ml-2">{{ $message }}</span>
                                    @enderror

                                    <button type="button"
                                            class="text-red-600 hover:text-red-900 transition duration-150 transform hover:scale-110 ml-4"
                                            wire:click="removePlanFiles({{ $index }}, {{ $fileIndex }})"
                                            aria-label="削除"
                                            title="削除">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                                             viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            @endforeach

                            <!-- ファイル追加ボタン -->
                            <div class="mt-4">
                                <button type="button" wire:click="addPlanFiles({{ $index }})">
                                    <span class="relative inline-block text-base group">
                                        <span
                                            class="relative z-10 block px-4 py-2 overflow-hidden font-medium leading-tight text-gray-800 transition-colors duration-300 ease-out border border-gray-900 rounded-md group-hover:text-white">
                                            <span
                                                class="absolute inset-0 w-full h-full px-4 py-2 rounded-md bg-gray-50"></span>
                                            <span
                                                class="absolute left-0 w-40 h-40 -ml-2 transition-all duration-300 origin-top-right -rotate-90 -translate-x-full translate-y-12 bg-gray-900 group-hover:-rotate-180 ease"></span>
                                            <span class="relative">ファイル追加</span>
                                        </span>
                                        <span
                                            class="absolute bottom-0 right-0 w-full h-8 -mb-1 -mr-1 transition-all duration-200 ease-linear bg-gray-900 rounded-md group-hover:mb-0 group-hover:mr-0"
                                            data-rounded="rounded-md"></span>
                                    </span>
                                </button>
                            </div>
                        </div>

                        <!-- 各種ボタン -->
                        <div class="mt-4 sp2:mt-8 flex justify-around sp2:justify-center gap-2 sp2:gap-6">
                            <!-- プラン追加ボタン -->
                            <button type="button" wire:click="addPlan">
                            <span class="relative inline-block text-lg group">
                                <span
                                    class="relative z-10 block px-3 sp2:px-5 py-3 overflow-hidden font-medium leading-tight text-gray-800 transition-colors duration-300 ease-out border-2 border-gray-900 rounded-lg group-hover:text-white">
                                    <span
                                        class="absolute inset-0 w-full h-full px-3 sp2:px-5 py-3 rounded-lg bg-gray-50"></span>
                                    <span
                                        class="absolute left-0 w-48 h-48 -ml-2 transition-all duration-300 origin-top-right -rotate-90 -translate-x-full translate-y-12 bg-gray-900 group-hover:-rotate-180 ease"></span>
                                    <span class="relative">プラン追加</span>
                                </span>
                                <span
                                    class="absolute bottom-0 right-0 w-full h-12 -mb-1 -mr-1 transition-all duration-200 ease-linear bg-gray-900 rounded-lg group-hover:mb-0 group-hover:mr-0"
                                    data-rounded="rounded-lg">
                                </span>
                            </span>
                            </button>
                            <!-- プラン削除ボタン -->
                            <button type="button" wire:click="removePlan({{ $index }})">
                            <span class="relative inline-block text-lg group">
                                <span
                                    class="relative z-10 block px-3 sp2:px-5 py-3 overflow-hidden font-medium leading-tight text-gray-800 transition-colors duration-300 ease-out border-2 border-gray-900 rounded-lg group-hover:text-white">
                                    <span
                                        class="absolute inset-0 w-full h-full px-3 sp2:px-5 py-3 rounded-lg bg-gray-50"></span>
                                    <span
                                        class="absolute left-0 w-48 h-48 -ml-2 transition-all duration-300 origin-top-right -rotate-90 -translate-x-full translate-y-12 bg-gray-900 group-hover:-rotate-180 ease"></span>
                                    <span class="relative">プラン削除</span>
                                </span>
                                <span
                                    class="absolute bottom-0 right-0 w-full h-12 -mb-1 -mr-1 transition-all duration-200 ease-linear bg-gray-900 rounded-lg group-hover:mb-0 group-hover:mr-0"
                                    data-rounded="rounded-lg">
                                </span>
                            </span>
                            </button>
                        </div>
                    @endforeach
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
                                    class="relative inline-flex items-center px-4 py-2 font-medium text-sm text-white bg-blue-500 border-b-2 border-blue-700 rounded-full hover:bg-blue-600 hover:border-b border-blue-600 transform hover:-translate-y-1 transition-all duration-300 ease-out disabled:opacity-50 disabled:cursor-not-allowed group"
                                    @if ($template_type === 'domestic') disabled @endif
                                    aria-label="テンプレートを使う(国内版)" title="テンプレートを使う(国内版)">
                                テンプレートを使う(国内版)
                                <i class="fas fa-angle-right ml-2 transition-transform duration-300 ease-out group-hover:translate-x-2"></i>
                            </button>
                            <!-- テンプレートボタン(海外版) -->
                            <button type="button" wire:click="useTemplatePackingItems('overseas')"
                                    class="relative inline-flex items-center px-4 py-2 font-medium text-sm text-white bg-blue-500 border-b-2 border-blue-700 rounded-full hover:bg-blue-600 hover:border-b border-blue-600 transform hover:-translate-y-1 transition-all duration-300 ease-out disabled:opacity-50 disabled:cursor-not-allowed group"
                                    @if ($template_type === 'overseas') disabled @endif
                                    aria-label="テンプレートを使う(海外版)" title="テンプレートを使う(海外版)">
                                テンプレートを使う(海外版)
                                <i class="fas fa-angle-right ml-2 transition-transform duration-300 ease-out group-hover:translate-x-2"></i>
                            </button>
                        </div>

                        <div class="mt-4">
                            <button type="button" wire:click="allRemovePackingItem"
                                    onclick="return confirm('本当に全ての持ち物を削除しますか？')">
                                <span
                                    class="relative inline-flex items-center justify-start px-4 py-2 overflow-hidden font-medium transition-all bg-red-500 rounded-lg group">
                                    <span
                                        class="absolute top-0 right-0 inline-block w-3 h-3 transition-all duration-500 ease-in-out bg-red-700 rounded-sm group-hover:-mr-3 group-hover:-mt-3">
                                        <span
                                            class="absolute top-0 right-0 w-4 h-4 rotate-45 translate-x-1/2 -translate-y-1/2 bg-white"></span>
                                    </span>
                                    <span
                                        class="absolute bottom-0 left-0 w-full h-full transition-all duration-500 ease-in-out delay-200 -translate-x-full translate-y-full bg-red-600 rounded-xl group-hover:mb-8 group-hover:translate-x-0"></span>
                                    <span
                                        class="relative w-full text-left text-sm text-white transition-colors duration-200 ease-in-out group-hover:text-white">全て削除</span>
                                </span>
                            </button>
                        </div>
                        @foreach($packingItems as $packingIndex => $packingItem)
                            <div class="flex items-center space-x-1.5 sp:space-x-4 mt-4">
                                <input type="checkbox"
                                       wire:model.defer="packingItems.{{ $packingIndex }}.packing_is_checked"
                                       class="h-4 w-4 text-indigo-600 border-gray-300 rounded-sm">
                                <input type="text" id="packingItems.{{ $packingIndex }}.packing_name"
                                       wire:model.defer="packingItems.{{ $packingIndex }}.packing_name"
                                       placeholder="持ち物の名前"
                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-xs focus:outline-hidden focus:ring-indigo-500 focus:border-indigo-500">
                                @error("packingItems.$packingIndex.packing_name")
                                <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                                <button type="button"
                                        class="text-red-600 hover:text-red-900 transition duration-150 transform hover:scale-110 ml-4"
                                        wire:click="removePackingItem({{ $packingIndex }})"
                                        aria-label="削除"
                                        title="削除">
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                         class="h-6 w-6"
                                         fill="none"
                                         viewBox="0 0 24 24"
                                         stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                            <!-- 持ち物追加ボタン -->
                            <div class="mt-2">
                                <button type="button" wire:click="addPackingItem({{ $packingIndex }})">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" class="w-6 h-6">
                                        <path
                                            d="M256 80c0-17.7-14.3-32-32-32s-32 14.3-32 32l0 144L48 224c-17.7 0-32 14.3-32 32s14.3 32 32 32l144 0 0 144c0 17.7 14.3 32 32 32s32-14.3 32-32l0-144 144 0c17.7 0 32-14.3 32-32s-14.3-32-32-32l-144 0 0-144z"/>
                                    </svg>
                                </button>
                            </div>
                        @endforeach
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
                            <button type="button" wire:click="allRemoveSouvenir"
                                    onclick="return confirm('本当に全てのお土産を削除しますか？')">
                                <span
                                    class="relative inline-flex items-center justify-start px-4 py-2 overflow-hidden font-medium transition-all bg-red-500 rounded-lg group">
                                    <span
                                        class="absolute top-0 right-0 inline-block w-3 h-3 transition-all duration-500 ease-in-out bg-red-700 rounded-sm group-hover:-mr-3 group-hover:-mt-3">
                                        <span
                                            class="absolute top-0 right-0 w-4 h-4 rotate-45 translate-x-1/2 -translate-y-1/2 bg-white"></span>
                                    </span>
                                    <span
                                        class="absolute bottom-0 left-0 w-full h-full transition-all duration-500 ease-in-out delay-200 -translate-x-full translate-y-full bg-red-600 rounded-lg group-hover:mb-8 group-hover:translate-x-0"></span>
                                    <span
                                        class="relative w-full text-left text-sm text-white transition-colors duration-200 ease-in-out group-hover:text-white">全て削除</span>
                                </span>
                            </button>
                        </div>
                        @foreach($souvenirs as $souvenirIndex => $souvenir)
                            <div class="flex items-center space-x-1.5 sp:space-x-4 mt-4">
                                <input type="checkbox"
                                       wire:model.defer="souvenirs.{{ $souvenirIndex }}.souvenir_is_checked"
                                       class="h-4 w-4 text-indigo-600 border-gray-300 rounded-sm">
                                <input type="text" id="souvenirs.{{ $souvenirIndex }}.souvenir_name"
                                       wire:model.defer="souvenirs.{{ $souvenirIndex }}.souvenir_name"
                                       placeholder="お土産の名前"
                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-xs focus:outline-hidden focus:ring-indigo-500 focus:border-indigo-500">
                                @error("souvenirs.$souvenirIndex.souvenir_name")
                                <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                                <button type="button"
                                        class="text-red-600 hover:text-red-900 transition duration-150 transform hover:scale-110 ml-4"
                                        wire:click="removeSouvenir({{ $souvenirIndex }})"
                                        aria-label="削除"
                                        title="削除">
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                         class="h-6 w-6"
                                         fill="none"
                                         viewBox="0 0 24 24"
                                         stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                            <!-- お土産追加ボタン -->
                            <div class="mt-4">
                                <button type="button" wire:click="addSouvenir({{ $souvenirIndex }})">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" class="w-6 h-6">
                                        <path
                                            d="M256 80c0-17.7-14.3-32-32-32s-32 14.3-32 32l0 144L48 224c-17.7 0-32 14.3-32 32s14.3 32 32 32l144 0 0 144c0 17.7 14.3 32 32 32s32-14.3 32-32l0-144 144 0c17.7 0 32-14.3 32-32s-14.3-32-32-32l-144 0 0-144z"/>
                                    </svg>
                                </button>
                            </div>
                        @endforeach
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
                        @foreach($additionalComments as $additionalCommentIndex => $additionalComment)
                            <div>
                                <label for="additionalComments.{{ $additionalCommentIndex }}.additionalComment_title"
                                       class="block text-sm font-medium text-gray-700">タイトル</label>
                                <input type="text"
                                       id="additionalComments.{{ $additionalCommentIndex }}.additionalComment_title"
                                       wire:model.defer="additionalComments.{{ $additionalCommentIndex }}.additionalComment_title"
                                       placeholder="タイトル"
                                       class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-xs focus:outline-hidden focus:ring-indigo-500 focus:border-indigo-500">
                                @error("additionalComments.$additionalCommentIndex.additionalComment_title")
                                <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mt-4">
                                <label for="additionalComments.{{ $additionalCommentIndex }}.additionalComment_text"
                                       class="block text-sm font-medium text-gray-700">テキスト</label>
                                <textarea id="additionalComments.{{ $additionalCommentIndex }}.additionalComment_text"
                                          wire:model.defer="additionalComments.{{ $additionalCommentIndex }}.additionalComment_text"
                                          placeholder="テキスト"
                                          class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-xs focus:outline-hidden focus:ring-indigo-500 focus:border-indigo-500 field-sizing-content"></textarea>
                                @error("additionalComments.$additionalCommentIndex.additionalComment_text")
                                <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mt-4 sp2:mt-8 flex justify-around sp2:justify-center gap-2 sp2:gap-6">
                                <!-- メモ欄追加ボタン -->
                                <button type="button" wire:click="addAdditionalComment({{ $additionalCommentIndex }})">
                                    <span class="relative inline-block text-lg group">
                                        <span
                                            class="relative z-10 block px-3 sp2:px-5 py-3 overflow-hidden font-medium leading-tight text-gray-800 transition-colors duration-300 ease-out border-2 border-gray-900 rounded-lg group-hover:text-white">
                                            <span
                                                class="absolute inset-0 w-full h-full px-3 sp2:px-5 py-3 rounded-lg bg-gray-50"></span>
                                            <span
                                                class="absolute left-0 w-48 h-48 -ml-2 transition-all duration-300 origin-top-right -rotate-90 -translate-x-full translate-y-12 bg-gray-900 group-hover:-rotate-180 ease"></span>
                                            <span class="relative">メモ追加</span>
                                        </span>
                                        <span
                                            class="absolute bottom-0 right-0 w-full h-12 -mb-1 -mr-1 transition-all duration-200 ease-linear bg-gray-900 rounded-lg group-hover:mb-0 group-hover:mr-0"
                                            data-rounded="rounded-lg"></span>
                                    </span>
                                </button>
                                <!-- メモ削除ボタン -->
                                <button type="button"
                                        wire:click="removeAdditionalComment({{ $additionalCommentIndex }})">
                                    <span class="relative inline-block text-lg group">
                                        <span
                                            class="relative z-10 block px-3 sp2:px-5 py-3 overflow-hidden font-medium leading-tight text-gray-800 transition-colors duration-300 ease-out border-2 border-gray-900 rounded-lg group-hover:text-white">
                                            <span
                                                class="absolute inset-0 w-full h-full px-3 sp2:px-5 py-3 rounded-lg bg-gray-50"></span>
                                            <span
                                                class="absolute left-0 w-48 h-48 -ml-2 transition-all duration-300 origin-top-right -rotate-90 -translate-x-full translate-y-12 bg-gray-900 group-hover:-rotate-180 ease"></span>
                                            <span class="relative">メモ削除</span>
                                        </span>
                                        <span
                                            class="absolute bottom-0 right-0 w-full h-12 -mb-1 -mr-1 transition-all duration-200 ease-linear bg-gray-900 rounded-lg group-hover:mb-0 group-hover:mr-0"
                                            data-rounded="rounded-lg"></span>
                                    </span>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </seciton>

        <!-- 送信ボタン -->
        <div class="mt-6 flex justify-center">
            <button type="submit"
                    class="relative inline-block px-8 py-4 font-semibold text-xl group">
                <span
                    class="absolute inset-0 w-full h-full transition duration-300 ease-out transform translate-x-2 translate-y-2 bg-black group-hover:translate-x-0 group-hover:translate-y-0"></span>
                <span
                    class="absolute inset-0 w-full h-full bg-white border-2 border-black transition duration-300 ease-out group-hover:bg-black"></span>
                <span class="relative flex items-center text-black group-hover:text-white">
                    作成する
                </span>
            </button>
        </div>
    </form>
</div>
