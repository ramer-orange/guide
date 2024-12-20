<?php

namespace App\Livewire;

use App\Models\AdditionalComment;
use App\Models\PackingItem;
use App\Models\PlanFile;
use App\Models\Plan;
use App\Models\Souvenir;
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
    public $useTemplatePackingItem = false;
    public $allRemovePackingItemFlag = 0;
    public $allRemoveSouvenirsFlag = 0;
    public $template_type;
    public $souvenirs = [];
    public $deleteSouvenirs = [];
    public $additionalComments = [];
    public $deleteAdditionalComments = [];


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
     * 持ち物リストのテンプレートの使用時に配列を初期化
     *
     * @param string $type
     * @return void
     */
    public function useTemplatePackingItems($type)
    {
        $this->useTemplatePackingItem = true;

        $this->template_type = $type;

        // 現在の持ち物リストをリセット
        $this->packingItems = [];

        $template = [
            // 国内版
            'domestic' => [
                ['packing_name' => '航空券', 'packing_is_checked' => false],
                ['packing_name' => '現金（日本円）', 'packing_is_checked' => false],
                ['packing_name' => 'クレジットカード', 'packing_is_checked' => false],
                ['packing_name' => '各種乗車券・ホテルバウチャー等', 'packing_is_checked' => false],
                ['packing_name' => '各種証明書のコピー', 'packing_is_checked' => false],
                ['packing_name' => '免許証', 'packing_is_checked' => false],
                ['packing_name' => '健康保険証', 'packing_is_checked' => false],

                ['packing_name' => 'スマホ', 'packing_is_checked' => false],
                ['packing_name' => '常備薬・医薬品', 'packing_is_checked' => false],
                ['packing_name' => '各種充電器', 'packing_is_checked' => false],
                ['packing_name' => 'モバイルバッテリー', 'packing_is_checked' => false],
                ['packing_name' => 'カメラ', 'packing_is_checked' => false],
                ['packing_name' => '腕時計', 'packing_is_checked' => false],

                ['packing_name' => '小銭入れ', 'packing_is_checked' => false],
                ['packing_name' => '折りたたみバッグ、エコバッグ', 'packing_is_checked' => false],
                ['packing_name' => 'ジッパー付きの透明ビニール袋(機内持ち込み)', 'packing_is_checked' => false],
                ['packing_name' => '機内用パーカー', 'packing_is_checked' => false],
                ['packing_name' => 'アイマスク', 'packing_is_checked' => false],
                ['packing_name' => 'マスク', 'packing_is_checked' => false],
                ['packing_name' => '耳栓', 'packing_is_checked' => false],
                ['packing_name' => 'リップクリーム', 'packing_is_checked' => false],
                ['packing_name' => '使い捨てスリッパ', 'packing_is_checked' => false],
                ['packing_name' => 'トラベル枕（首用）', 'packing_is_checked' => false],
                ['packing_name' => 'イヤホン', 'packing_is_checked' => false],

                ['packing_name' => '衣服', 'packing_is_checked' => false],
                ['packing_name' => 'パジャマ', 'packing_is_checked' => false],
                ['packing_name' => 'サンダル', 'packing_is_checked' => false],
                ['packing_name' => '水着', 'packing_is_checked' => false],
                ['packing_name' => 'ハンカチ', 'packing_is_checked' => false],
                ['packing_name' => 'ポケットティッシュ', 'packing_is_checked' => false],

                ['packing_name' => 'コンタクトレンズ用品・眼鏡', 'packing_is_checked' => false],
                ['packing_name' => 'S字フック', 'packing_is_checked' => false],
                ['packing_name' => 'ガイドブック', 'packing_is_checked' => false],
                ['packing_name' => '筆記用具・メモ帳', 'packing_is_checked' => false],
                ['packing_name' => '折り畳み傘', 'packing_is_checked' => false],
                ['packing_name' => '日傘', 'packing_is_checked' => false],
                ['packing_name' => 'レインコート', 'packing_is_checked' => false],
                ['packing_name' => 'タオル', 'packing_is_checked' => false],
                ['packing_name' => '日焼け止め', 'packing_is_checked' => false],
                ['packing_name' => 'サングラス', 'packing_is_checked' => false],
                ['packing_name' => '虫よけスプレー', 'packing_is_checked' => false],
                ['packing_name' => 'ハンドファン', 'packing_is_checked' => false],
                ['packing_name' => '汗拭きシート', 'packing_is_checked' => false],
                ['packing_name' => 'カイロ', 'packing_is_checked' => false],
                ['packing_name' => 'ばんそうこう', 'packing_is_checked' => false],
                ['packing_name' => 'ウェットティッシュ', 'packing_is_checked' => false],
                ['packing_name' => 'ポリ袋', 'packing_is_checked' => false],
                ['packing_name' => '食料', 'packing_is_checked' => false],

                ['packing_name' => '化粧品', 'packing_is_checked' => false],
                ['packing_name' => '洗顔料・メイク落とし', 'packing_is_checked' => false],
                ['packing_name' => '生理用品', 'packing_is_checked' => false],
                ['packing_name' => '洗濯洗剤', 'packing_is_checked' => false],
                ['packing_name' => '洗濯ネット', 'packing_is_checked' => false],

                ['packing_name' => 'シェーバー', 'packing_is_checked' => false],

            ],
            // 海外版
            'overseas' => [
                ['packing_name' => 'パスポート', 'packing_is_checked' => false],
                ['packing_name' => 'ビザ(VISA、査証)、電子渡航申請(ESTA、ETASなど)', 'packing_is_checked' => false],
                ['packing_name' => '海外旅行保険保険証', 'packing_is_checked' => false],
                ['packing_name' => '航空券', 'packing_is_checked' => false],
                ['packing_name' => '現金（日本円）', 'packing_is_checked' => false],
                ['packing_name' => '現金（現地通貨）', 'packing_is_checked' => false],
                ['packing_name' => 'クレジットカード', 'packing_is_checked' => false],
                ['packing_name' => '各種乗車券・ホテルバウチャー等', 'packing_is_checked' => false],
                ['packing_name' => 'パスポート等、各種証明書のコピー', 'packing_is_checked' => false],
                ['packing_name' => '国際免許証', 'packing_is_checked' => false],

                ['packing_name' => 'スマホ', 'packing_is_checked' => false],
                ['packing_name' => '常備薬・医薬品', 'packing_is_checked' => false],
                ['packing_name' => '各種充電器', 'packing_is_checked' => false],
                ['packing_name' => 'モバイルバッテリー', 'packing_is_checked' => false],
                ['packing_name' => '海外用電源プラグ変換アダプター', 'packing_is_checked' => false],
                ['packing_name' => 'ポケットWiFi', 'packing_is_checked' => false],
                ['packing_name' => 'カメラ', 'packing_is_checked' => false],
                ['packing_name' => '腕時計', 'packing_is_checked' => false],

                ['packing_name' => '小銭入れ', 'packing_is_checked' => false],
                ['packing_name' => '折りたたみバッグ、エコバッグ', 'packing_is_checked' => false],
                ['packing_name' => 'ジッパー付きの透明ビニール袋(機内持ち込み)', 'packing_is_checked' => false],
                ['packing_name' => '機内用パーカー', 'packing_is_checked' => false],
                ['packing_name' => 'アイマスク', 'packing_is_checked' => false],
                ['packing_name' => 'マスク', 'packing_is_checked' => false],
                ['packing_name' => '耳栓', 'packing_is_checked' => false],
                ['packing_name' => 'リップクリーム', 'packing_is_checked' => false],
                ['packing_name' => '使い捨てスリッパ', 'packing_is_checked' => false],
                ['packing_name' => 'トラベル枕（首用）', 'packing_is_checked' => false],
                ['packing_name' => 'イヤホン', 'packing_is_checked' => false],

                ['packing_name' => '衣服', 'packing_is_checked' => false],
                ['packing_name' => 'パジャマ', 'packing_is_checked' => false],
                ['packing_name' => 'サンダル', 'packing_is_checked' => false],
                ['packing_name' => '水着', 'packing_is_checked' => false],
                ['packing_name' => 'ハンカチ', 'packing_is_checked' => false],
                ['packing_name' => 'ポケットティッシュ', 'packing_is_checked' => false],

                ['packing_name' => 'コンタクトレンズ用品・眼鏡', 'packing_is_checked' => false],
                ['packing_name' => 'S字フック', 'packing_is_checked' => false],
                ['packing_name' => 'ガイドブック', 'packing_is_checked' => false],
                ['packing_name' => '筆記用具・メモ帳', 'packing_is_checked' => false],
                ['packing_name' => '折り畳み傘', 'packing_is_checked' => false],
                ['packing_name' => '日傘', 'packing_is_checked' => false],
                ['packing_name' => 'レインコート', 'packing_is_checked' => false],
                ['packing_name' => 'タオル', 'packing_is_checked' => false],
                ['packing_name' => '日焼け止め', 'packing_is_checked' => false],
                ['packing_name' => 'サングラス', 'packing_is_checked' => false],
                ['packing_name' => '虫よけスプレー', 'packing_is_checked' => false],
                ['packing_name' => 'ハンドファン', 'packing_is_checked' => false],
                ['packing_name' => '汗拭きシート', 'packing_is_checked' => false],
                ['packing_name' => 'カイロ', 'packing_is_checked' => false],
                ['packing_name' => 'ばんそうこう', 'packing_is_checked' => false],
                ['packing_name' => 'ウェットティッシュ', 'packing_is_checked' => false],
                ['packing_name' => 'ポリ袋', 'packing_is_checked' => false],
                ['packing_name' => '食料', 'packing_is_checked' => false],

                ['packing_name' => '化粧品', 'packing_is_checked' => false],
                ['packing_name' => '洗顔料・メイク落とし', 'packing_is_checked' => false],
                ['packing_name' => 'シャンプー、リンス、ボディソープ', 'packing_is_checked' => false],
                ['packing_name' => '生理用品', 'packing_is_checked' => false],
                ['packing_name' => 'ヘアブラシ', 'packing_is_checked' => false],
                ['packing_name' => '洗濯洗剤', 'packing_is_checked' => false],
                ['packing_name' => '洗濯ネット', 'packing_is_checked' => false],
                ['packing_name' => '歯ブラシ・歯磨き粉', 'packing_is_checked' => false],

                ['packing_name' => 'シェーバー', 'packing_is_checked' => false],
            ]
        ];
        $this->packingItems = array_merge($this->packingItems, $template[$type]);
    }

    /**
     * 指定した位置に新しい持ち物項目を追加
     *
     * @param int $packingIndex
     * @return void
     */
    public function addPackingItem($packingIndex)
    {
        // 追加ボタンを押した箇所の次に挿入
        array_splice($this->packingItems, $packingIndex + 1, 0, [
            [
                'packing_name' => '',
                'packing_is_checked' => false,
            ]
        ]);
    }

    /**
     * 指定した位置に新しいお土産を追加
     *
     * @param int $souvenirIndex
     * @return void
     */
    public function addSouvenir($souvenirIndex)
    {
        // 追加ボタンを押した箇所の次に挿入
        array_splice($this->souvenirs, $souvenirIndex + 1, 0, [
            [
                'souvenir_name' => '',
                'souvenir_is_checked' => false,
            ]
        ]);
    }

    /**
     * 指定した位置に新しい自由記述欄を追加
     *
     * @param int $additionalCommentIndex
     * @return void
     */
    public function addAdditionalComment($additionalCommentIndex)
    {
        array_splice($this->additionalComments, $additionalCommentIndex + 1, 0, [
            [
                'additionalComment_title' => '',
                'additionalComment_text' => '',
            ]
        ]);
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
     * @param int $packingIndex
     * @return void
     */
    public function removePackingItem($packingIndex)
    {
        // 既存の持ち物のIDを記録
        if (isset($this->packingItems[$packingIndex]['id'])) {
            $this->deletePackingItems[] = $this->packingItems[$packingIndex]['id'];
        }
        // リストのインデックスを再構築
        unset($this->packingItems[$packingIndex]);
        $this->packingItems = array_values($this->packingItems);

        if (count($this->packingItems) === 0) {
            $this->packingItems[] = [
                'packing_name' => '',
                'packing_is_checked' => false,
            ];
        }
    }

    /**
     * お土産が削除された際に、削除されたお土産のidを記録し、リストのインデックスを再構築。
     * 削除した際、配列の要素数が0であれば、初期値を設置
     *
     * @param int $packingIndex
     * @return void
     */
    public function removeSouvenir($souvenirIndex)
    {
        // 既存のお土産のIDを記録
        if (isset($this->souvenirs[$souvenirIndex]['id'])) {
            $this->deleteSouvenirs[] = $this->souvenirs[$souvenirIndex]['id'];
        }
        // リストのインデックスを再構築
        unset($this->souvenirs[$souvenirIndex]);
        $this->souvenirs = array_values($this->souvenirs);

        if (count($this->souvenirs) === 0) {
            $this->souvenirs[] = [
                'souvenir_name' => '',
                'souvenir_is_checked' => false,
            ];
        }
    }

    /**
     * 指定した位置の自由記述欄を削除し、削除された自由記述欄のidを記録し、リストのインデックスを再構築。
     * 削除した際、配列の要素数が0であれば、初期値を設置
     *
     * @param int $additionalCommentIndex
     * @return void
     */
    public function removeAdditionalComment($additionalCommentIndex)
    {
        if (isset($this->additionalComments[$additionalCommentIndex]['id'])) {
            $this->deleteAdditionalComments[] = $this->additionalComments[$additionalCommentIndex]['id'];
        }
        unset($this->additionalComments[$additionalCommentIndex]);
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
        $this->packingItems = [
            [
                'souvenir_name' => '',
                'souvenir_is_checked' => false
            ]
        ];
        // データベースを削除する際のフラグ
        $this->allRemoveSouvenirsFlag = 1;
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
            'template_type' => 'nullable | string | max:255',
            'souvenirs' => 'required | array',
            'souvenirs.*.souvenir_name' => 'nullable | string | max:255',
            'souvenirs.*.souvenir_is_checked' => 'nullable | boolean',
            'additionalComments' => 'required | array',
            'additionalComments.*.additionalComment_title' => 'nullable | string | max:255',
            'additionalComments.*.additionalComment_text' => 'nullable | string',
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

        // 自由記述欄を更新
        foreach ($this->additionalComments as $additionalCommentData) {
            if (isset($additionalCommentData['id'])) {
                $additionalComment = $this->overview->additionalComments()->find($additionalCommentData['id']);
                if ($additionalComment) {
                    $additionalComment->update([
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
