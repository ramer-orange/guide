<?php

namespace App\Livewire;

use App\Models\Plan;
use App\Models\TravelOverview;
use Livewire\Component;
use Illuminate\Support\Facades\Http;

class PlansForm extends Component
{
    public $title;
    public $overview;
    public $plans = [];

    public function mount()
    {
        $this->plans = [
          ['date' => '', 'time' => '', 'plans_title' => '', 'content' => '']
        ];
    }

    public function addPlan()
    {
        $this->plans[] = ['date' => '', 'time' => '', 'plans_title' => '', 'content' => ''];
    }

    public function removePlan($index)
    {
        unset($this->plans[$index]);
    }

    public function submit()
    {
        $this->validate([
            'title' => 'required | string | max:255',
            'overview' => 'nullable | string',
            'plans' => 'required | array',
            'plans.*.date' => 'nullable | date',
            'plans.*.time' => 'nullable | date_format:H:i',
            'plans.*.plans_title' => 'nullable | string | max:255',
            'plans.*.content' => 'nullable | string',
        ]);

        $overview = TravelOverview::create([
            'user_id' => auth()->id(),
            'title' => $this->title,
            'overview' => $this->overview,
        ]);
        foreach ($this->plans as $plan) {
            $overview->plans()->create([
                'date' => $plan['date'] ?: null,
                'time' => $plan['time'] ?: null,
                'plans_title' => $plan['plans_title'],
                'content' => $plan['content'],
            ]);
        }

        return redirect()->route('itineraries.edit', [$overview->id]);
    }

    public function render()
    {
        return view('livewire.plans-form');
    }
}
