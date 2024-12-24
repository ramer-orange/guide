<?php

namespace App\Livewire;

use App\Models\Plan;
use App\Models\TravelOverview;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Livewire\Traits\addItems;

class PlansForm extends Component
{
    use WithFileUploads;
    use AddItems;

    public $title;
    public $overviewText;
    public $plans = [];
    public $useTemplatePackingItem = false;
    public $packingItems = [];
    public $template_type;
    public $souvenirs = [];
    public $additionalComments = [];

    public function mount()
    {
        $this->plans[] = [
            //プラン
            'date' => '',
            'time' => '',
            'plans_title' => '',
            'content' => '',

            // 新規ファイルアップロード
            'planFiles' => [null],
        ];

        $this->template_type = null;

        //持ち物リスト
        $this->packingItems[] = [
            'packing_name' => '',
            'packing_is_checked' => false,
        ];

        //お土産リスト
        $this->souvenirs[] = [
            'souvenir_name' => '',
            'souvenir_is_checked' => false,
        ];

        //自由記述欄
        $this->additionalComments[] = [
            'additionalComment_title' => '',
            'additionalComment_text' => '',
        ];
    }

    /**
     * 指定した位置のプランを削除し、インデックスを再構築
     * 削除した際、配列の要素数が0であれば、初期値を設置
     *
     * @param int $index
     * @return void
     */
    public function removePlan($index)
    {
        unset($this->plans[$index]);
        $this->plans = array_values($this->plans);
        if (count($this->plans) === 0) {
            $this->plans[] = [
                //プラン
                'date' => '',
                'time' => '',
                'plans_title' => '',
                'content' => '',

                // 新規ファイルアップロード
                'planFiles' => [null],
            ];
        }
    }

    /**
     * 指定した位置のファイルを削除し、インデックスを再構築
     * 削除した際、配列の要素数が0であれば、初期値を設置
     *
     * @param int $index
     * @param int $fileIndex
     * @return void
     */
    public function removePlanFiles($index, $fileIndex)
    {
        unset($this->plans[$index]['planFiles'][$fileIndex]);
        $this->plans[$index]['planFiles'] = array_values($this->plans[$index]['planFiles']);

        if (count($this->plans[$index]['planFiles']) === 0) {
            $this->plans[$index]['planFiles'][] = null;
        }
    }

    /**
     * 指定した位置の持ち物を削除し、インデックスを再構築
     * 削除した際、配列の要素数が0であれば、初期値を設置
     *
     * @param int $index
     * @return void
     */
    public function removePackingItem($index)
    {
        unset($this->packingItems[$index]);
        $this->packingItems = array_values($this->packingItems);

        if (count($this->packingItems) === 0) {
            $this->packingItems[] = [
                'packing_name' => '',
                'packing_is_checked' => false,
            ];
        }
    }

    /**
     * 指定した位置のお土産を削除し、インデックスを再構築
     * 削除した際、配列の要素数が0であれば、初期値を設置
     *
     * @param int $index
     * @return void
     */
    public function removeSouvenir($index)
    {
        unset($this->souvenirs[$index]);
        $this->souvenirs = array_values($this->souvenirs);

        if (count($this->souvenirs) === 0) {
            $this->souvenirs[] = [
                'souvenir_name' => '',
                'souvenir_is_checked' => false,
            ];
        }
    }

    /**
     * 指定した位置の自由記述欄を削除し、インデックスを再構築
     * 削除した際、配列の要素数が0であれば、初期値を設置
     *
     * @param int $index
     * @return void
     */
    public function removeAdditionalComment($index)
    {
        unset($this->additionalComments[$index]);
        $this->additionalComments = array_values($this->additionalComments);

        if (count($this->additionalComments) === 0) {
            $this->additionalComments[] = [
                'additionalComment_title' => '',
                'additionalComment_text' => '',
            ];
        }
    }

    /**
     * 全ての持ち物を一括削除してリセット
     * @return void
     */
    public function allRemovePackingItem()
    {
        $this->packingItems = [
            [
                'packing_name' => '',
                'packing_is_checked' => false
            ]
        ];
        $this->template_type = null;
    }

    /**
     * 全てのお土産を一括削除してリセット
     * @return void
     */
    public function allRemoveSouvenir()
    {
        $this->souvenirs = [
            [
                'souvenir_name' => '',
                'souvenir_is_checked' => false
            ]
        ];
    }

    public function submit()
    {
        $this->validate([
            'title' => 'required | string | max:255',
            'overviewText' => 'nullable | string',
            'plans' => 'required | array',
            'plans.*.date' => 'nullable | date',
            'plans.*.time' => 'nullable | date_format:H:i',
            'plans.*.plans_title' => 'nullable | string | max:255',
            'plans.*.content' => 'nullable | string',
            'plans.*.planFiles' => 'nullable|array',
            'plans.*.planFiles.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240',
            'packingItems' => 'required | array',
            'packingItems.*.packing_name' => 'nullable | string | max:255',
            'packingItems.*.packing_is_checked' => 'nullable | boolean',
            'template_type' => 'nullable | string | max:255',
            'souvenirs' => 'required | array',
            'souvenirs.*.souvenirs_name' => 'nullable | string | max:255',
            'souvenirs.*.souvenirs_is_checked' => 'nullable | boolean',
            'additionalComments' => 'required | array',
            'additionalComments.*.additionalComment_title' => 'nullable | string | max:255',
            'additionalComments.*.additionalComment_text' => 'nullable | string',
        ]);

        $overview = TravelOverview::create([
            'user_id' => auth()->id(),
            'title' => $this->title,
            'overviewText' => $this->overviewText,
        ]);
        foreach ($this->plans as $plan) {
            $newPlan = $overview->plans()->create([
                'date' => $plan['date'] ?: null,
                'time' => $plan['time'] ?: null,
                'plans_title' => $plan['plans_title'],
                'content' => $plan['content'],
            ]);
            foreach ($plan['planFiles'] as $planFile) {
                if ($planFile) {
                    $filePath = $planFile->store('files', 'public');
                    $newPlan->planFiles()->create([
                        'path' => $filePath,
                        'file_name' => $planFile->getClientOriginalName(),
                    ]);
                }
            }
        }
        $overview->templateType = $this->template_type;
        foreach ($this->packingItems as $packingItem) {
            $overview->packingItems()->create([
                'packing_name' => $packingItem['packing_name'],
                'packing_is_checked' => $packingItem['packing_is_checked'],
            ]);
        }
        foreach ($this->souvenirs as $souvenir) {
            $overview->souvenirs()->create([
                'souvenir_name' => $souvenir['souvenir_name'],
                'souvenir_is_checked' => $souvenir['souvenir_is_checked'],
            ]);
        }
        foreach ($this->additionalComments as $additionalComment) {
            $overview->additionalComments()->create([
                'additionalComment_title' => $additionalComment['additionalComment_title'],
                'additionalComment_text' => $additionalComment['additionalComment_text'],
            ]);
        }
        return redirect()->route('itineraries.edit', [$overview->id]);
    }

    public function render()
    {
        return view('livewire.plans-form');
    }
}
