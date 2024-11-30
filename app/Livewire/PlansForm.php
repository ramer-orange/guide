<?php

namespace App\Livewire;

use App\Models\Plan;
use App\Models\TravelOverview;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Http;

class PlansForm extends Component
{
    use WithFileUploads;

    public $title;
    public $overview;
    public $overviewText;
    public $plans = [];
    public $planFiles = [];

    public function mount()
    {
        $this->plans[] = [
            'date' => '',
            'time' => '',
            'plans_title' => '',
            'content' => '',
            // 新規ファイルアップロード用
            'planFiles' => [],
            // 既存ファイル表示用
            'existing_planFiles' => [],
        ];
    }

    public function addPlan()
    {
        $this->plans[] = [
            'date' => '',
            'time' => '',
            'plans_title' => '',
            'content' => '',
            // 新規ファイルアップロード用
            'planFiles' => [],
            // 既存ファイル表示用
            'existing_planFiles' => [],
        ];
    }

    public function removePlan($index)
    {
        unset($this->plans[$index]);
        $this->plans = array_values($this->plans);
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
                $filePath = $planFile->store('files', 'public');
                $newPlan->planFiles()->create([
                    'path' => $filePath,
                    'file_name' => $planFile->getClientOriginalName(),
                ]);
            }
        }

        return redirect()->route('itineraries.edit', [$overview->id]);
    }

    public function render()
    {
        return view('livewire.plans-form');
    }
}
