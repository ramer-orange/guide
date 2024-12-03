<?php

namespace App\Livewire;

use App\Models\Plan;
use App\Models\TravelOverview;
use Livewire\Component;
use Livewire\WithFileUploads;

class PlansForm extends Component
{
    use WithFileUploads;

    public $title;
    public $overview;
    public $overviewText;
    public $plans = [];
    public $packingItems = [];

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

        $this->packingItems[] = [
            //持ち物リスト
            'packing_name' => '',
            'packing_is_checked' => false,
        ];
    }

    public function addPlan()
    {
        $this->plans[] = [
            //プラン
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

    public function addPackingItem()
    {
        $this->packingItems[] = [
            //持ち物リスト
            'packing_name' => '',
            'packing_is_checked' => false,
        ];
    }

    public function removePlan($index)
    {
        unset($this->plans[$index]);
        $this->plans = array_values($this->plans);
    }

    public function removePlanFiles($index, $fileIndex)
    {
        unset($this->plans[$index]['planFiles'][$fileIndex]);
        $this->plans[$index]['planFiles'] = array_values($this->plans[$index]['planFiles']);
    }

    public function removePackingItem($packingIndex)
    {
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
            'plans.*.planFiles' => 'nullable|array',
            'plans.*.planFiles.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048',
            'packingItems' => 'required | array',
            'packingItems.*.packing_name' => 'nullable | string | max:255',
            'packingItems.*.packing_is_checked' => 'nullable | boolean',
        ]);

        $overview = TravelOverview::create([
            'user_id' => auth()->id(),
            'title' => $this->title,
            'overview' => $this->overviewText,
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
        foreach ($this->packingItems as $packingItem) {
            $overview->packingItems()->create([
                'packing_name' => $packingItem['packing_name'],
                'packing_is_checked' => $packingItem['packing_is_checked'],
            ]);
        }
        return redirect()->route('itineraries.edit', [$overview->id]);
    }

    public function render()
    {
        return view('livewire.plans-form');
    }
}
