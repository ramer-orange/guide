<?php

namespace App\Livewire;

use App\Models\TravelOverview;
use Livewire\Component;

class EditPlansForm extends Component
{
    public $title;
    public $overviewText;
    public $overview;
    public $plans = [];
    public $deletedPlans = [];

    public function mount(TravelOverview $overview)
    {
        $this->overview = $overview;
        $this->title = $overview->title;
        $this->overviewText = $overview->overview;

        $this->plans = $overview->plans->map(function ($plan) {
            return[
                'id' => $plan->id,
                'date' => $plan->date,
                'time' => $plan->time,
                'plans_title' => $plan->plans_title,
                'content' => $plan->content,
            ];
        })->toArray();
    }

    public function addPlan()
    {
        $this->plans[] = ['date' => '', 'time' => '', 'plans_title' => '', 'content' => ''];
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
                }
            } else {
                // 新しいプランを作成
                $this->overview->plans()->create([
                    'date' => $planData['date'] ?: null,
                    'time' => $planData['time'] ?: null,
                    'plans_title' => $planData['plans_title'],
                    'content' => $planData['content'],
                ]);
            }
            if(!empty($this->deletedPlans)) {
                $this->overview->plans()->whereIn('id', $this->deletedPlans)->delete();
            }
        }
        return redirect()->route('itineraries.edit', [$this->overview->id]);
    }

    public function render()
    {
        return view('livewire.edit-plans-form');
    }
}
