<?php

namespace App\Livewire;

use App\Models\PlanFile;
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

    public function mount(TravelOverview $overview)
    {
        $this->overview = $overview;
        $this->title = $overview->title;
        $this->overviewText = $overview->overview;

        $this->plans = $overview->plans->map(function ($plan) {
            return [
                'id' => $plan->id,
                'date' => $plan->date,
                'time' => $plan->time,
                'plans_title' => $plan->plans_title,
                'content' => $plan->content,
                'planFiles' => [null],
                'existing_planFiles' => $plan->planFiles->map(function ($planFile) {
                    return [
                        'id' => $planFile->id,
                        'path' => $planFile->path,
                        'file_name' => $planFile->file_name,
                    ];
                })->toArray()
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
                    if(!empty($planData['planFiles'])){
                        foreach ($planData['planFiles'] as $planFile) {
                            if($planFile) {
                                $filePath = $planFile->store('files', 'public');
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
                    if($planFile) {
                        $filePath = $planFile->store('files', 'public');
                        $newPlan->planFiles()->create([
                            'path' => $filePath,
                            'file_name' => $planFile->getClientOriginalName(),
                        ]);
                    }
                }
            }
            if (!empty($this->deletedPlans)) {
                $this->overview->plans()->whereIn('id', $this->deletedPlans)->delete();
            }
            if (!empty($this->deletedPlanFiles)) {
                Planfile::whereIn('id', $this->deletedPlanFiles)->delete();
            }
        }
        return redirect()->route('itineraries.edit', [$this->overview->id]);
    }

    public function render()
    {
        return view('livewire.edit-plans-form');
    }
}
