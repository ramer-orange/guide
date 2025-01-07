<?php

namespace App\Livewire;

use App\Http\Requests\SubmitFormRequest;
use App\Models\AdditionalComment;
use App\Models\PackingItem;
use App\Models\PlanFile;
use App\Models\Plan;
use App\Models\Souvenir;
use App\Models\TravelOverview;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Livewire\Traits\AddItems;
use App\Livewire\Traits\InitializeLists;
use App\Livewire\Traits\UpdateOrder;


class EditPlansForm extends Component
{
    use WithFileUploads;
    use AddItems;
    use InitializeLists;
    use UpdateOrder;

    public $title;
    public $overviewText;
    public $overview;
    public $plans = [];
    public $deletedPlans = [];
    public $deletedPlanFiles = [];
    public $packingItems = [];
    public $deletePackingItems = [];
    public $useTemplatePackingItem = false;
    public $allRemovePackingItemFlag = 0;
    public $allRemoveSouvenirsFlag = 0;
    public $template_type;
    public $souvenirs = [];
    public $deleteSouvenirs = [];
    public $additionalComments = [];
    public $deleteAdditionalComments = [];

    protected function rules(): array
    {
        return (new SubmitFormRequest())->rules();
    }


    /**
     * マウント時にコンポーネントの初期値を設定
     *
     * @param \App\Models\TravelOverview $overview
     * @return void
     */
    public function mount(TravelOverview $overview)
    {
        $this->overview = $overview;
        $this->title = $overview->title;
        $this->overviewText = $overview->overviewText;

        // プランをロード
        $this->plans = $overview->plans->map(function ($plan) {
            return [
                'id' => $plan->id,
                'date' => $plan->date,
                'time' => $plan->time,
                'plans_title' => $plan->plans_title,
                'content' => $plan->content,
                'planFiles' => [null],

                // アップロードファイルをロード
                'existing_planFiles' => $plan->planFiles->map(function ($planFile) {
                    return [
                        'id' => $planFile->id,
                        'path' => $planFile->path,
                        'file_name' => $planFile->file_name,
                    ];
                })->toArray()
            ];
        })->toArray();

        if ($overview->templateType) {
            $this->template_type = $overview->templateType->template_name;
        } else {
            $this->template_type = null;
        }

        // 持ち物リストをロード
        $this->packingItems = $overview->packingItems->map(function ($packingItem) {
            return [
                'id' => $packingItem->id,
                'packing_name' => $packingItem->packing_name,
                'packing_is_checked' => $packingItem->packing_is_checked == 1,
            ];
        })->toArray();

        // お土産リストをロード
        $this->souvenirs = $overview->souvenirs->map(function ($souvenir) {
            return [
                'id' => $souvenir->id,
                'souvenir_name' => $souvenir->souvenir_name,
                'souvenir_is_checked' => $souvenir->souvenir_is_checked == 1,
            ];
        })->toArray();

        // 自由記述欄をロード
        $this->additionalComments = $overview->additionalComments->map(function ($additionalComment) {
            return [
                'id' => $additionalComment->id,
                'additionalComment_title' => $additionalComment->additionalComment_title,
                'additionalComment_text' => $additionalComment->additionalComment_text,
            ];
        })->toArray();
    }

    /**
     * 指定した位置のプランを削除し、削除されたプランのidを記録し、リストのインデックスを再構築。
     * 削除した際、配列の要素数が0であれば、初期値を設置
     *
     * @param int $index
     * @return void
     */
    public function removePlan($index)
    {
        if (isset($this->plans[$index]['id'])) {
            // 既存のプランのIDを記録
            $this->deletedPlans[] = $this->plans[$index]['id'];
        }

        unset($this->plans[$index]);
        $this->plans = array_values($this->plans);

        if (count($this->plans) === 0) {
            $this->plans[] = $this->getDefaultPlan();
        }
    }

    /**
     * 指定した位置のファイルを削除し、削除されたファイルのidを記録し、リストのインデックスを再構築。
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

    public function removeExistingPlanFile($index, $existingFileIndex)
    {
        if (isset($this->plans[$index]['existing_planFiles'][$existingFileIndex]['id'])) {
            // 既存のファイルのIDを記録
            $this->deletedPlanFiles[] = $this->plans[$index]['existing_planFiles'][$existingFileIndex]['id'];;
        }

        unset($this->plans[$index]['existing_planFiles'][$existingFileIndex]);
        $this->plans[$index]['existing_planFiles'] = array_values($this->plans[$index]['existing_planFiles']);
    }

    /**
     * 持ち物が削除された際に、削除された持ち物のidを記録し、リストのインデックスを再構築。
     * 削除した際、配列の要素数が0であれば、初期値を設置
     *
     * @param int $index
     * @return void
     */
    public function removePackingItem($index)
    {
        // 既存の持ち物のIDを記録
        if (isset($this->packingItems[$index]['id'])) {
            $this->deletePackingItems[] = $this->packingItems[$index]['id'];
        }
        // リストのインデックスを再構築
        unset($this->packingItems[$index]);
        $this->packingItems = array_values($this->packingItems);

        if (count($this->packingItems) === 0) {
            $this->packingItems[] = $this->getDefaultPackingItems();
        }
    }

    /**
     * お土産が削除された際に、削除されたお土産のidを記録し、リストのインデックスを再構築。
     * 削除した際、配列の要素数が0であれば、初期値を設置
     *
     * @param int $index
     * @return void
     */
    public function removeSouvenir($index)
    {
        // 既存のお土産のIDを記録
        if (isset($this->souvenirs[$index]['id'])) {
            $this->deleteSouvenirs[] = $this->souvenirs[$index]['id'];
        }
        // リストのインデックスを再構築
        unset($this->souvenirs[$index]);
        $this->souvenirs = array_values($this->souvenirs);

        if (count($this->souvenirs) === 0) {
            $this->souvenirs[] = $this->getDefaultSouvenirs();
        }
    }

    /**
     * 指定した位置の自由記述欄を削除し、削除された自由記述欄のidを記録し、リストのインデックスを再構築。
     * 削除した際、配列の要素数が0であれば、初期値を設置
     *
     * @param int $index
     * @return void
     */
    public function removeAdditionalComment($index)
    {
        if (isset($this->additionalComments[$index]['id'])) {
            $this->deleteAdditionalComments[] = $this->additionalComments[$index]['id'];
        }
        unset($this->additionalComments[$index]);
        $this->additionalComments = array_values($this->additionalComments);

        if (count($this->additionalComments) === 0) {
            $this->additionalComments[] = $this->getDefaultAdditionalComments();
        }
    }

    /**
     * 全ての持ち物を一括削除してリセット
     * @return void
     */
    public function allRemovePackingItem()
    {
        $this->packingItems = [$this->getDefaultPackingItems()];

        // データベースを削除する際のフラグ
        $this->allRemovePackingItemFlag = 1;
        $this->template_type = null;
    }

    /**
     * 全てのお土産を一括削除してリセット
     * @return void
     */
    public function allRemoveSouvenir()
    {
        $this->souvenirs = [$this->getDefaultSouvenirs()];
        // データベースを削除する際のフラグ
        $this->allRemoveSouvenirsFlag = 1;
    }

    /**
     * プランの要素を並び替えした場合
     *
     * @param $orderedIds
     * @return void
     */
    public function updatePlanOrder($orderedIds)
    {
        $this->plans = $this->updateOrder($this->plans, $orderedIds);
    }

    /**
     * 持ち物の要素を並び替えした場合
     *
     * @param $orderedIds
     * @return void
     */
    public function updatePackingItemOrder($orderedIds)
    {
        $this->packingItems = $this->updateOrder($this->packingItems, $orderedIds);
    }

    /**
     * お土産の要素を並び替えした場合
     *
     * @param $orderedIds
     * @return void
     */
    public function updateSouvenirOrder($orderedIds)
    {
        $this->souvenirs = $this->updateOrder($this->souvenirs, $orderedIds);
        dd($this->souvenirs);
    }

    /**
     * メモの要素を並び替えした場合
     *
     * @param $orderedIds
     * @return void
     */
    public function updateAdditionalCommentsOrder($orderedIds)
    {
        $this->additionalComments = $this->updateOrder($this->additionalComments, $orderedIds);
    }

    public function submit()
    {
        $this->validate();

        $this->overview->update([
            'title' => $this->title,
            'overview' => $this->overviewText,
        ]);

        // 各プランの更新または作成
        foreach ($this->plans as $planData) {
            if (isset($planData['id'])) {
                // 既存のプランを更新
                $plan = $this->overview->plans()->find($planData['id']);
                if ($plan) {
                    $plan->update([
                        'id' => $planData['id'],
                        'date' => $planData['date'] ?: null,
                        'time' => $planData['time'] ?: null,
                        'plans_title' => $planData['plans_title'],
                        'content' => $planData['content'],
                    ]);
                    if (!empty($planData['planFiles'])) {
                        foreach ($planData['planFiles'] as $planFile) {
                            if ($planFile) {
                                $filePath = $planFile->store('files', 'public');
                                //なぜupdateではない？
                                $plan->planFiles()->create([
                                    'path' => $filePath,
                                    'file_name' => $planFile->getClientOriginalName(),
                                ]);
                            }
                        }
                    }
                }
            } else {
                // 新しいプランを作成
                $newPlan = $this->overview->plans()->create([
                    'date' => $planData['date'] ?: null,
                    'time' => $planData['time'] ?: null,
                    'plans_title' => $planData['plans_title'],
                    'content' => $planData['content'],
                ]);
                foreach ($planData['planFiles'] as $planFile) {
                    if ($planFile) {
                        $filePath = $planFile->store('files', 'public');
                        $newPlan->planFiles()->create([
                            'path' => $filePath,
                            'file_name' => $planFile->getClientOriginalName(),
                        ]);
                    }
                }
            }
        }
        if (!empty($this->deletedPlans)) {
            Plan::whereIn('id', $this->deletedPlans)->delete();
        }
        if (!empty($this->deletedPlanFiles)) {
            Planfile::whereIn('id', $this->deletedPlanFiles)->delete();
        }

        $this->overview->templateType = $this->template_type;

        //全て削除ボタンを押された時データベースの値も削除
        if ($this->allRemovePackingItemFlag == 1) {
            PackingItem::where('travel_id', $this->overview->id)->delete();
        }

        // 持ち物リスト更新
        foreach ($this->packingItems as $packingItemData) {
            if (isset($packingItemData['id'])) {
                $packingItem = $this->overview->packingItems()->find($packingItemData['id']);
                if ($packingItem) {
                    $packingItem->update([
                        'id' => $packingItemData['id'],
                        'packing_name' => $packingItemData['packing_name'],
                        'packing_is_checked' => $packingItemData['packing_is_checked'],
                    ]);
                }
            } else {
                $this->overview->packingItems()->create([
                    'packing_name' => $packingItemData['packing_name'],
                    'packing_is_checked' => $packingItemData['packing_is_checked'],
                ]);
            }
        }
        // 削除した持ち物をデータベースから削除
        if (!empty($this->deletePackingItems)) {
            PackingItem::whereIn('id', $this->deletePackingItems)->delete();
        }
        //全て削除ボタンを押された時データベースの値も削除
        if ($this->allRemoveSouvenirsFlag == 1) {
            Souvenir::where('travel_id', $this->overview->id)->delete();
        }

        // お土産リスト更新
        foreach ($this->souvenirs as $souvenirData) {
            if (isset($souvenirData['id'])) {
                $souvenir = $this->overview->souvenirs()->find($souvenirData['id']);
                if ($souvenir) {
                    $souvenir->update([
                        'id' => $souvenirData['id'],
                        'souvenir_name' => $souvenirData['souvenir_name'],
                        'souvenir_is_checked' => $souvenirData['souvenir_is_checked'],
                    ]);
                }
            } else {
                $this->overview->souvenirs()->create([
                    'souvenir_is_checked' => $souvenirData['souvenir_is_checked'],
                    'souvenir_name' => $souvenirData['souvenir_name'],
                ]);
            }
            // 削除したお土産をデータベースから削除
            if (!empty($this->deleteSouvenirs)) {
                Souvenir::whereIn('id', $this->deleteSouvenirs)->delete();
            }
        }

        // メモを更新
        foreach ($this->additionalComments as $additionalCommentData) {
            if (isset($additionalCommentData['id'])) {
                $additionalComment = $this->overview->additionalComments()->find($additionalCommentData['id']);
                if ($additionalComment) {
                    $additionalComment->update([
                        'id' => $additionalCommentData['id'],
                        'additionalComment_title' => $additionalCommentData['additionalComment_title'],
                        'additionalComment_text' => $additionalCommentData['additionalComment_text'],
                    ]);
                }
            } else {
                $this->overview->additionalComments()->create([
                    'additionalComment_title' => $additionalCommentData['additionalComment_title'],
                    'additionalComment_text' => $additionalCommentData['additionalComment_text'],
                ]);
            }

            // 削除した自由記述欄をデータベースから削除
            if (!empty($this->deleteAdditionalComments)) {
                AdditionalComment::whereIn('id', $this->deleteAdditionalComments)->delete();
            }
        }

        return redirect()->route('itineraries.edit', [$this->overview->id]);
    }

    public function render()
    {
        return view('livewire.edit-plans-form');
    }
}
