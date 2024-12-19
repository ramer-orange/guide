<div class="max-w-4xl mx-auto p-6 bg-white shadow-md rounded-lg">
    <form wire:submit.prevent="submit">
        <!-- タイトルと概要 -->
        <div class="space-y-6">
            <div class="p-6 bg-gray-50 rounded-lg shadow-inner">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700">タイトル</label>
                    <input type="text" id="title" wire:model.defer="title"
                           class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    @error('title')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>
                <div>
                    <label for="overviewText" class="block text-sm font-medium text-gray-700">旅行概要</label>
                    <textarea id="overviewText" wire:model.defer="overviewText"
                              class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                    @error('overviewText')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>
            </div>
        </div>

        <!-- プランセクション -->
        <seciton>
            <div class="mt-12">
                <h2 class="text-2xl font-semibold text-center text-gray-800">プラン</h2>

                <div class="mt-8 p-6 bg-gray-50 rounded-lg shadow-inner relative">
                    @foreach($plans as $index => $plan)
                        <!-- 日付と時間 -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="plans.{{ $index }}.date"
                                       class="block text-sm font-medium text-gray-700">日付</label>
                                <input type="date" id="plans.{{ $index }}.date"
                                       wire:model.defer="plans.{{ $index }}.date"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                @error("plans.$index.date")
                                <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label for="plans.{{ $index }}.time"
                                       class="block text-sm font-medium text-gray-700">時間</label>
                                <input type="time" id="plans.{{ $index }}.time"
                                       wire:model.defer="plans.{{ $index }}.time"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                @error("plans.$index.time")
                                <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <!-- タイトル -->
                        <div class="mt-6">
                            <label for="plans.{{ $index }}.plans_title"
                                   class="block text-sm font-medium text-gray-700">タイトル</label>
                            <input type="text" id="plans.{{ $index }}.plans_title"
                                   wire:model.defer="plans.{{ $index }}.plans_title"
                                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            @error("plans.$index.plans_title")
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- 内容 -->
                        <div class="mt-4">
                            <label for="plans.{{ $index }}.content"
                                   class="block text-sm font-medium text-gray-700">内容</label>
                            <textarea id="plans.{{ $index }}.content" wire:model.defer="plans.{{ $index }}.content"
                                      class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                            @error("plans.$index.content")
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- ファイルアップロード -->
                        <div class="mt-6">
                            <h3 class="text-lg font-medium text-gray-700">ファイルアップロード</h3>
                            @foreach($plan['planFiles'] as $fileIndex => $planFile)
                                <div class="flex items-center mt-4">
                                    <input type="file" wire:model="plans.{{ $index }}.planFiles.{{ $fileIndex }}"
                                           class="flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
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
                                        <span class="relative">ファイルを追加</span>
                                    </span>
                                    <span
                                        class="absolute bottom-0 right-0 w-full h-8 -mb-1 -mr-1 transition-all duration-200 ease-linear bg-gray-900 rounded-md group-hover:mb-0 group-hover:mr-0"
                                        data-rounded="rounded-md"></span>
                                </span>
                                </button>
                            </div>
                        </div>
                    @endforeach

                    <div class="mt-8 flex justify-center gap-6">
                        <!-- プラン追加ボタン -->
                        <button type="button" wire:click="addPlan">
                            <span class="relative inline-block text-lg group">
                                <span
                                    class="relative z-10 block px-5 py-3 overflow-hidden font-medium leading-tight text-gray-800 transition-colors duration-300 ease-out border-2 border-gray-900 rounded-lg group-hover:text-white">
                                    <span
                                        class="absolute inset-0 w-full h-full px-5 py-3 rounded-lg bg-gray-50"></span>
                                    <span
                                        class="absolute left-0 w-48 h-48 -ml-2 transition-all duration-300 origin-top-right -rotate-90 -translate-x-full translate-y-12 bg-gray-900 group-hover:-rotate-180 ease"></span>
                                    <span class="relative">プランを追加</span>
                                </span>
                                <span
                                    class="absolute bottom-0 right-0 w-full h-12 -mb-1 -mr-1 transition-all duration-200 ease-linear bg-gray-900 rounded-lg group-hover:mb-0 group-hover:mr-0"
                                    data-rounded="rounded-lg"></span>
                            </span>
                        </button>
                        <!-- プラン削除ボタン -->
                        <button type="button" wire:click="removePlan({{ $index }})">
                            <span class="relative inline-block text-lg group">
                                <span
                                    class="relative z-10 block px-5 py-3 overflow-hidden font-medium leading-tight text-gray-800 transition-colors duration-300 ease-out border-2 border-gray-900 rounded-lg group-hover:text-white">
                                    <span
                                        class="absolute inset-0 w-full h-full px-5 py-3 rounded-lg bg-gray-50"></span>
                                    <span
                                        class="absolute left-0 w-48 h-48 -ml-2 transition-all duration-300 origin-top-right -rotate-90 -translate-x-full translate-y-12 bg-gray-900 group-hover:-rotate-180 ease"></span>
                                    <span class="relative">プランを削除</span>
                                </span>
                                <span
                                    class="absolute bottom-0 right-0 w-full h-12 -mb-1 -mr-1 transition-all duration-200 ease-linear bg-gray-900 rounded-lg group-hover:mb-0 group-hover:mr-0"
                                    data-rounded="rounded-lg"></span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </seciton>

        <!-- 持ち物リストセクション -->
        <section>
            <div class="mt-12">
                <h2 class="text-2xl font-semibold text-center text-gray-800">持ち物リスト</h2>
                <div class="mt-6 space-y-6">
                    <div class="p-6 bg-gray-50 rounded-lg shadow-inner">
                        <div class="flex justify-center space-x-6">
                            <!-- テンプレートボタン(国内版) -->
                            <button type="button" wire:click="useTemplatePackingItems('domestic')"
                                    class="relative inline-flex items-center px-6 py-3 font-semibold text-lg text-white bg-blue-500 border-b-4 border-blue-700 rounded-full hover:bg-blue-600 hover:border-b-2 transform hover:-translate-y-1 transition-all duration-300 ease-out disabled:opacity-50 disabled:cursor-not-allowed group"
                                    @if ($template_type === 'domestic') disabled @endif
                                    aria-label="テンプレートを使う(国内版)" title="テンプレートを使う(国内版)">
                                テンプレートを使う<br>(国内版)
                                <i class="fas fa-angle-right ml-3 transition-transform duration-300 ease-out group-hover:translate-x-2"></i>
                            </button>

                            <!-- テンプレートボタン(海外版) -->
                            <button type="button" wire:click="useTemplatePackingItems('overseas')"
                                    class="relative inline-flex items-center px-6 py-3 font-semibold text-lg text-white bg-blue-500 border-b-4 border-blue-700 rounded-full hover:bg-blue-600 hover:border-b-2 transform hover:-translate-y-1 transition-all duration-300 ease-out disabled:opacity-50 disabled:cursor-not-allowed group"
                                    @if ($template_type === 'overseas') disabled @endif
                                    aria-label="テンプレートを使う(海外版)" title="テンプレートを使う(海外版)">
                                テンプレートを使う<br>(海外版)
                                <i class="fas fa-angle-right ml-3 transition-transform duration-300 ease-out group-hover:translate-x-2"></i>
                            </button>
                        </div>

                        <div class="mt-4">
                            <button type="button" wire:click="allRemovePackingItem"
                                    onclick="return confirm('本当に全ての持ち物を削除しますか？')">
                                <span
                                    class="relative inline-flex items-center justify-start px-6 py-3 overflow-hidden font-medium transition-all bg-red-500 rounded-xl group">
                                    <span
                                        class="absolute top-0 right-0 inline-block w-4 h-4 transition-all duration-500 ease-in-out bg-red-700 rounded group-hover:-mr-4 group-hover:-mt-4">
                                        <span
                                            class="absolute top-0 right-0 w-5 h-5 rotate-45 translate-x-1/2 -translate-y-1/2 bg-white"></span>
                                    </span>
                                    <span
                                        class="absolute bottom-0 left-0 w-full h-full transition-all duration-500 ease-in-out delay-200 -translate-x-full translate-y-full bg-red-600 rounded-2xl group-hover:mb-12 group-hover:translate-x-0"></span>
                                    <span
                                        class="relative w-full text-left text-white transition-colors duration-200 ease-in-out group-hover:text-white">持ち物を全て削除</span>
                                </span>
                            </button>
                        </div>
                        @foreach($packingItems as $packingIndex => $packingItem)
                            <div class="flex items-center space-x-4 mt-4">
                                <input type="checkbox"
                                       wire:model.defer="packingItems.{{ $packingIndex }}.packing_is_checked"
                                       class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                <input type="text" id="packingItems.{{ $packingIndex }}.packing_name"
                                       wire:model.defer="packingItems.{{ $packingIndex }}.packing_name"
                                       placeholder="持ち物の名前"
                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                @error("packingItems.$packingIndex.packing_name")
                                <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                                <button type="submit"
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
                            <div class="mt-4">
                                <button type="button" wire:click="addPackingItem({{ $packingIndex }})">
                                <span class="relative inline-block text-base group">
                                    <span
                                        class="relative z-10 block px-4 py-2 overflow-hidden font-medium leading-tight text-gray-800 transition-colors duration-300 ease-out border border-gray-900 rounded-md group-hover:text-white">
                                        <span
                                            class="absolute inset-0 w-full h-full px-4 py-2 rounded-md bg-gray-50"></span>
                                        <span
                                            class="absolute left-0 w-40 h-40 -ml-2 transition-all duration-300 origin-top-right -rotate-90 -translate-x-full translate-y-12 bg-gray-900 group-hover:-rotate-180 ease"></span>
                                        <span class="relative">持ち物を追加</span>
                                    </span>
                                    <span
                                        class="absolute bottom-0 right-0 w-full h-8 -mb-1 -mr-1 transition-all duration-200 ease-linear bg-gray-900 rounded-md group-hover:mb-0 group-hover:mr-0"
                                        data-rounded="rounded-md"></span>
                                </span>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <!-- お土産リストセクション -->
        <section>
            <div class="mt-12">
                <h2 class="text-2xl font-semibold text-center text-gray-800">お土産リスト</h2>
                <div class="mt-6 space-y-6">
                    <div class="p-6 bg-gray-50 rounded-lg shadow-inner">
                        <div>
                            <button type="button" wire:click="allRemoveSouvenir"
                                    onclick="return confirm('本当に全てのお土産を削除しますか？')">
                                <span
                                    class="relative inline-flex items-center justify-start px-6 py-3 overflow-hidden font-medium transition-all bg-red-500 rounded-xl group">
                                    <span
                                        class="absolute top-0 right-0 inline-block w-4 h-4 transition-all duration-500 ease-in-out bg-red-700 rounded group-hover:-mr-4 group-hover:-mt-4">
                                        <span
                                            class="absolute top-0 right-0 w-5 h-5 rotate-45 translate-x-1/2 -translate-y-1/2 bg-white"></span>
                                    </span>
                                    <span
                                        class="absolute bottom-0 left-0 w-full h-full transition-all duration-500 ease-in-out delay-200 -translate-x-full translate-y-full bg-red-600 rounded-2xl group-hover:mb-12 group-hover:translate-x-0"></span>
                                    <span
                                        class="relative w-full text-left text-white transition-colors duration-200 ease-in-out group-hover:text-white">お土産を全て削除</span>
                                </span>
                            </button>
                        </div>
                        @foreach($souvenirs as $souvenirIndex => $souvenir)
                            <div class="flex items-center space-x-4 mt-4">
                                <input type="checkbox"
                                       wire:model.defer="souvenirs.{{ $souvenirIndex }}.souvenir_is_checked"
                                       class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                <input type="text" id="souvenirs.{{ $souvenirIndex }}.souvenir_name"
                                       wire:model.defer="souvenirs.{{ $souvenirIndex }}.souvenir_name"
                                       placeholder="お土産の名前"
                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                @error("souvenirs.$souvenirIndex.souvenir_name")
                                <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                                <button type="submit"
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
                                    <span class="relative inline-block text-base group">
                                        <span
                                            class="relative z-10 block px-4 py-2 overflow-hidden font-medium leading-tight text-gray-800 transition-colors duration-300 ease-out border border-gray-900 rounded-md group-hover:text-white">
                                            <span
                                                class="absolute inset-0 w-full h-full px-4 py-2 rounded-md bg-gray-50"></span>
                                            <span
                                                class="absolute left-0 w-40 h-40 -ml-2 transition-all duration-300 origin-top-right -rotate-90 -translate-x-full translate-y-12 bg-gray-900 group-hover:-rotate-180 ease"></span>
                                            <span class="relative">お土産を追加</span>
                                        </span>
                                        <span
                                            class="absolute bottom-0 right-0 w-full h-8 -mb-1 -mr-1 transition-all duration-200 ease-linear bg-gray-900 rounded-md group-hover:mb-0 group-hover:mr-0"
                                            data-rounded="rounded-md"></span>
                                    </span>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <!-- 自由記述欄セクション -->
        <seciton>
            <div class="mt-12">
                <h2 class="text-2xl font-semibold text-center text-gray-800">自由記述欄</h2>
                <div class="mt-6 space-y-6">
                    @foreach($additionalComments as $additionalCommentIndex => $additionalComment)
                        <div class="p-6 bg-gray-50 rounded-lg shadow-inner">
                            <div>
                                <label for="additionalComments.{{ $additionalCommentIndex }}.additionalComment_title"
                                       class="block text-sm font-medium text-gray-700">タイトル</label>
                                <input type="text"
                                       id="additionalComments.{{ $additionalCommentIndex }}.additionalComment_title"
                                       wire:model.defer="additionalComments.{{ $additionalCommentIndex }}.additionalComment_title"
                                       placeholder="タイトル"
                                       class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
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
                                          class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                                @error("additionalComments.$additionalCommentIndex.additionalComment_text")
                                <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mt-4 flex space-x-4">
                                <!-- 自由記述欄追加ボタン -->
                                <button type="button" wire:click="addAdditionalComment({{ $additionalCommentIndex }})">
                                    <span class="relative inline-block text-lg group">
                                        <span
                                            class="relative z-10 block px-5 py-3 overflow-hidden font-medium leading-tight text-gray-800 transition-colors duration-300 ease-out border-2 border-gray-900 rounded-lg group-hover:text-white">
                                            <span
                                                class="absolute inset-0 w-full h-full px-5 py-3 rounded-lg bg-gray-50"></span>
                                            <span
                                                class="absolute left-0 w-48 h-48 -ml-2 transition-all duration-300 origin-top-right -rotate-90 -translate-x-full translate-y-12 bg-gray-900 group-hover:-rotate-180 ease"></span>
                                            <span class="relative">自由記述欄を追加</span>
                                        </span>
                                        <span
                                            class="absolute bottom-0 right-0 w-full h-12 -mb-1 -mr-1 transition-all duration-200 ease-linear bg-gray-900 rounded-lg group-hover:mb-0 group-hover:mr-0"
                                            data-rounded="rounded-lg"></span>
                                    </span>
                                </button>
                                <!-- 自由記述欄削除ボタン -->
                                <button type="button"
                                        wire:click="removeAdditionalComment({{ $additionalCommentIndex }})">
                                    <span class="relative inline-block text-lg group">
                                            <span
                                                class="relative z-10 block px-5 py-3 overflow-hidden font-medium leading-tight text-gray-800 transition-colors duration-300 ease-out border-2 border-gray-900 rounded-lg group-hover:text-white">
                                                <span
                                                    class="absolute inset-0 w-full h-full px-5 py-3 rounded-lg bg-gray-50"></span>
                                                <span
                                                    class="absolute left-0 w-48 h-48 -ml-2 transition-all duration-300 origin-top-right -rotate-90 -translate-x-full translate-y-12 bg-gray-900 group-hover:-rotate-180 ease"></span>
                                                <span class="relative">自由記述欄を削除</span>
                                            </span>
                                            <span
                                                class="absolute bottom-0 right-0 w-full h-12 -mb-1 -mr-1 transition-all duration-200 ease-linear bg-gray-900 rounded-lg group-hover:mb-0 group-hover:mr-0"
                                                data-rounded="rounded-lg"></span>
                                    </span>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </seciton>

        <!-- 送信ボタン -->
        <div class="mt-6 flex justify-center">
            <a href="{{ route('itineraries.create') }}"
               class="relative inline-block px-6 py-3 font-semibold text-lg group"
               aria-label="作成する" title="作成する">
                <span
                    class="absolute inset-0 w-full h-full transition duration-300 ease-out transform translate-x-2 translate-y-2 bg-black group-hover:translate-x-0 group-hover:translate-y-0"></span>
                <span
                    class="absolute inset-0 w-full h-full bg-white border-2 border-black transition duration-300 ease-out group-hover:bg-black"></span>

                <span class="relative flex items-center text-black group-hover:text-white">
                    作成する
                </span>
            </a>
        </div>
    </form>
</div>
