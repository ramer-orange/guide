<?php

namespace App\Livewire;

use App\Http\Requests\SubmitFormRequest;
use App\Livewire\Traits\AddItems;
use App\Livewire\Traits\InitializeLists;
use App\Livewire\Traits\UpdateOrder;
use App\Models\SharedPassword;
use App\Models\TravelOverview;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class EditPlansForm extends Component
{
    use AddItems;
    use InitializeLists;
    use UpdateOrder;
    use WithFileUploads;

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

    public $shared_password_check;

    public $shared_password;

    public $shared_password_confirmation;

    public $viewer_share_expires_at;

    public $showPasswordField = false;

    public bool $isOwner = false;

    public bool $canEdit = false;

    protected function rules(): array
    {
        $existingShare = $this->overview?->sharedPasswords;
        $startsNewLifecycle = ! $existingShare || $existingShare->lifecycleElapsed();
        $shareCap = $startsNewLifecycle
            ? now()->addDays(SharedPassword::MAX_LIFETIME_DAYS)
            : $existingShare->maximumExpiresAt();

        return array_merge((new SubmitFormRequest)->rules(), [
            'shared_password' => [
                Rule::requiredIf(fn () => $this->showPasswordField
                    && ($startsNewLifecycle || ! $existingShare?->shared_password)),
                'nullable',
                'string',
                'min:8',
                'max:32',
                'confirmed',
            ],
            'viewer_share_expires_at' => [
                Rule::requiredIf(fn () => $this->showPasswordField),
                'date',
                'after:now',
                'before_or_equal:'.$shareCap->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * マウント時にコンポーネントの初期値を設定
     *
     * @return void
     */
    public function mount(TravelOverview $overview)
    {
        abort_unless(Gate::allows('view', $overview), 403);

        $this->overview = $overview;
        $this->isOwner = Gate::allows('manageViewerShare', $overview);
        $this->canEdit = Gate::allows('update', $overview);
        $this->title = $overview->title;
        $this->overviewText = $overview->overviewText;

        // プランをロード
        $this->plans = $overview->plans
            ->sortBy('order')
            ->values()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'date' => $plan->date,
                    'time' => substr($plan->time, 0, 5),
                    'plans_title' => $plan->plans_title,
                    'content' => $plan->content,
                    'planFiles' => [null],
                    'order' => $plan->order,

                    // アップロードファイルをロード
                    'existing_planFiles' => $plan->planFiles->map(function ($planFile) {
                        return [
                            'id' => $planFile->id,
                            'path' => $planFile->path,
                            'url' => $planFile->url(),
                            'file_name' => $planFile->file_name,
                        ];
                    })->toArray(),
                ];
            })->toArray();

        if ($overview->templateType) {
            $this->template_type = $overview->templateType->template_name;
        } else {
            $this->template_type = null;
        }

        // 持ち物リストをロード
        $this->packingItems = $overview->packingItems
            ->where('user_id', auth()->id())
            ->sortBy('order')
            ->values()
            ->map(function ($packingItem) {
                return [
                    'id' => $packingItem->id,
                    'packing_name' => $packingItem->packing_name,
                    'packing_is_checked' => $packingItem->packing_is_checked == 1,
                    'order' => $packingItem->order,
                ];
            })->toArray();

        if (count($this->packingItems) === 0) {
            $this->packingItems[] = $this->getDefaultPackingItems();
        }

        // お土産リストをロード
        $this->souvenirs = $overview->souvenirs
            ->sortBy('order')
            ->values()
            ->map(function ($souvenir) {
                return [
                    'id' => $souvenir->id,
                    'souvenir_name' => $souvenir->souvenir_name,
                    'souvenir_is_checked' => $souvenir->souvenir_is_checked == 1,
                    'order' => $souvenir->order,
                ];
            })->toArray();

        // 自由記述欄をロード
        $this->additionalComments = $overview->additionalComments
            ->sortBy('order')
            ->values()
            ->map(function ($additionalComment) {
                return [
                    'id' => $additionalComment->id,
                    'additionalComment_title' => $additionalComment->additionalComment_title,
                    'additionalComment_text' => $additionalComment->additionalComment_text,
                    'order' => $additionalComment->order,

                ];
            })->toArray();

        // 閲覧用パスワードの存在確認
        if ($overview->sharedPasswords?->isActive()) {
            $this->shared_password_check = true;
            $this->viewer_share_expires_at = $overview->sharedPasswords->expires_at?->format('Y-m-d\TH:i');
        }
    }

    /**
     * 指定した位置のプランを削除し、削除されたプランのidを記録し、リストのインデックスを再構築。
     * 削除した際、配列の要素数が0であれば、初期値を設置
     *
     * @param  int  $index
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
     * @param  int  $index
     * @param  int  $fileIndex
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
            $this->deletedPlanFiles[] = $this->plans[$index]['existing_planFiles'][$existingFileIndex]['id'];
        }

        unset($this->plans[$index]['existing_planFiles'][$existingFileIndex]);
        $this->plans[$index]['existing_planFiles'] = array_values($this->plans[$index]['existing_planFiles']);
    }

    /**
     * 持ち物が削除された際に、削除された持ち物のidを記録し、リストのインデックスを再構築。
     * 削除した際、配列の要素数が0であれば、初期値を設置
     *
     * @param  int  $index
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
     * @param  int  $index
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
     * @param  int  $index
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
     *
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
     *
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
     * @return void
     */
    public function updatePlanOrder($orderedIds)
    {
        $this->plans = $this->updateOrder($this->plans, $orderedIds);
    }

    /**
     * 持ち物の要素を並び替えした場合
     *
     * @return void
     */
    public function updatePackingItemOrder($orderedIds)
    {
        $this->packingItems = $this->updateOrder($this->packingItems, $orderedIds);
    }

    /**
     * お土産の要素を並び替えした場合
     *
     * @return void
     */
    public function updateSouvenirOrder($orderedIds)
    {
        $this->souvenirs = $this->updateOrder($this->souvenirs, $orderedIds);
    }

    /**
     * メモの要素を並び替えした場合
     *
     * @return void
     */
    public function updateAdditionalCommentsOrder($orderedIds)
    {
        $this->additionalComments = $this->updateOrder($this->additionalComments, $orderedIds);
    }

    /**
     * 閲覧用パスワード設定ボタンの表示、非表示
     *
     * @return void
     */
    public function showPasswordFields()
    {
        Gate::authorize('manageViewerShare', $this->overview);

        $this->showPasswordField = true;
    }

    public function disableViewerShare(\App\Actions\Itineraries\SaveViewerShare $saveViewerShare)
    {
        Gate::authorize('manageViewerShare', $this->overview);

        $saveViewerShare->revoke($this->overview);

        \App\Support\SharedAccess::forget(request(), $this->overview->id);
        $this->overview->unsetRelation('sharedPasswords');
        $this->shared_password_check = false;
        $this->shared_password = null;
        $this->shared_password_confirmation = null;
        $this->viewer_share_expires_at = null;
        $this->showPasswordField = false;
    }

    public function submit(\App\Actions\Itineraries\SaveItinerary $saveItinerary)
    {
        Gate::authorize('update', $this->overview);

        if (Gate::allows('manageViewerShare', $this->overview)
            && $this->showPasswordField
            && ! $this->viewer_share_expires_at) {
            $this->viewer_share_expires_at = SharedPassword::defaultExpiresAt()->format('Y-m-d\\TH:i');
        }

        $this->validate();
        $payload = \App\Actions\Itineraries\LegacyItineraryPayload::fromLivewire($this);
        unset($payload['data']['shared_password'], $payload['data']['viewer_share_expires_at']);
        $saveItinerary->handle(auth()->user(), $payload['data'], $payload['uploads'], $this->overview);

        if (Gate::allows('manageViewerShare', $this->overview) && $this->showPasswordField) {
            $this->saveViewerShare();
        }

        return redirect()->route('itineraries.edit', [$this->overview->id]);
    }

    public function render()
    {
        return view('livewire.edit-plans-form');
    }

    private function saveViewerShare(): void
    {
        app(\App\Actions\Itineraries\SaveViewerShare::class)->handle(
            $this->overview,
            $this->shared_password,
            $this->viewer_share_expires_at,
        );

        $this->overview->unsetRelation('sharedPasswords');
    }
}
