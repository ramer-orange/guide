<?php

namespace App\Livewire;

use App\Models\PackingItem;
use App\Models\PlanFile;
use App\Models\Plan;
use App\Models\TravelOverview;
use Livewire\Component;
use Livewire\WithFileUploads;

class EditPlansForm extends Component
{
    use WithFileUploads;

    public $title;
    public $overviewText;
    public $overview;
    public $plans = [];
    public $deletedPlans = [];
    public $deletedPlanFiles = [];
    public $packingItems = [];
    public $deletePackingItems = [];

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
        $this->overviewText = $overview->overview;

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

        // 持ち物リストをロード
        $this->packingItems = $overview->packingItems->map(function ($packingItem) {
            return [
                'id' => $packingItem->id,
                'packing_name' => $packingItem->packing_name,
                'packing_is_checked' => $packingItem->packing_is_checked == 1,
            ];
        })->toArray();
    }

    public function addPlan()
    {
        $this->plans[] = [
            'date' => '',
            'time' => '',
            'plans_title' => '',
            'content' => '',
            // 新規ファイルアップロード用
            'planFiles' => [null],
        ];
    }

    public function addPlanFiles($index)
    {
        $this->plans[$index]['planFiles'][] = null;
    }

    /**
     * 持ち物リストに新しいアイテムを追加する。
     *
     * @return void
     */
    public function addPackingItem()
    {
        $this->packingItems[] = [
            //持ち物リストの初期値
            'packing_name' => '',
            'packing_is_checked' => false,
        ];
    }

    public function removePlan($index)
    {
        if (isset($this->plans[$index]['id'])) {
            // 既存のプランのIDを記録
            $this->deletedPlans[] = $this->plans[$index]['id'];
        }

        unset($this->plans[$index]);
        $this->plans = array_values($this->plans);
    }

    public function removePlanFiles($index, $fileIndex)
    {
        if (isset($this->plans[$index]['planFiles'][$fileIndex]['id'])) {
            // 既存のファイルのIDを記録
            $this->deletedPlanFiles[] = $this->plans[$index]['planFiles'][$fileIndex]['id'];
        }

        unset($this->plans[$index]['planFiles'][$fileIndex]);
        $this->plans[$index]['planFiles'] = array_values($this->plans[$index]['planFiles']);
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
     *
     * @param int $packingIndex
     * @return void
     */
    public function removePackingItems($packingIndex)
    {
        if (isset($this->packingItems[$packingIndex]['id'])) {
            // 既存の持ち物のIDを記録
            $this->deletePackingItems[] = $this->packingItems[$packingIndex]['id'];
        }

        // リストのインデックスを再構築
        unset($this->packingItems[$packingIndex]);
        $this->packingItems = array_values($this->packingItems);
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
            'plans.*.planFiles' => 'nullable | array',
            'plans.*.planFiles.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048',
            'packingItems' => 'required | array',
            'packingItems.*.packing_name' => 'nullable | string | max:255',
            'packingItems.*.packing_is_checked' => 'nullable | boolean',
        ]);

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
            if (!empty($this->deletedPlans)) {
                Plan::whereIn('id', $this->deletedPlans)->delete();
            }
            if (!empty($this->deletedPlanFiles)) {
                Planfile::whereIn('id', $this->deletedPlanFiles)->delete();
            }
        }

        // 持ち物リスト更新
        foreach ($this->packingItems as $packingItemData) {
            if (isset($packingItemData['id'])) {
                $packingItem = $this->overview->packingItems()->find($packingItemData['id']);
                if ($packingItem) {
                    $packingItem->update([
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
            // 削除した持ち物をデータベースから削除
            if (!empty($this->deletePackingItems)) {
                PackingItem::whereIn('id', $this->deletePackingItems)->delete();
            }
        }
        return redirect()->route('itineraries.edit', [$this->overview->id]);
    }

    public function render()
    {
        return view('livewire.edit-plans-form');
    }
}
