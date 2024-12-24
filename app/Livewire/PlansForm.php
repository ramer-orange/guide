<?php

namespace App\Livewire;

use App\Http\Requests\SubmitFormRequest;
use App\Models\Plan;
use App\Models\TravelOverview;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Livewire\Traits\AddItems;
use App\Livewire\Traits\InitializeLists;

class PlansForm extends Component
{
    use WithFileUploads;
    use AddItems;
    use InitializeLists;

    public $title;
    public $overviewText;
    public $plans = [];
    public $useTemplatePackingItem = false;
    public $packingItems = [];
    public $template_type;
    public $souvenirs = [];
    public $additionalComments = [];

    protected function rules(): array
    {
        return (new SubmitFormRequest())->rules();
    }

    public function mount()
    {
        $this->plans[] = $this->getDefaultPlan();

        $this->template_type = null;

        $this->packingItems[] = $this->getDefaultPackingItems();

        $this->souvenirs[] = $this->getDefaultSouvenirs();

        $this->additionalComments[] = $this->getDefaultAdditionalComments();
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
            $this->plans[] = $this->getDefaultPlan();
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
            $this->packingItems[] = $this->getDefaultPackingItems();
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
            $this->souvenirs[] = $this->getDefaultSouvenirs();
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
        $this->template_type = null;
    }

    /**
     * 全てのお土産を一括削除してリセット
     * @return void
     */
    public function allRemoveSouvenir()
    {
        $this->souvenirs = [$this->getDefaultSouvenirs()];
    }

    public function submit()
    {
        $this->validate();

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
