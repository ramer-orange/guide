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
    public $useTemplatePackingItem = false;
    public $allRemovePackingItemFlag = 0;
    public  $template_type;


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

    /**
     * 全ての持ち物を一括削除してリセット
     * @return void
     */
    public function allRemovePackingItem()
    {
        $this->packingItems = [
            ['packing_name' => '', 'packing_is_checked' => false]
        ];
        $this->allRemovePackingItemFlag = 1;
        $this->template_type = null;
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
        if ($this->allRemovePackingItemFlag == 1){
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
        return redirect()->route('itineraries.edit', [$this->overview->id]);
    }

    public function render()
    {
        return view('livewire.edit-plans-form');
    }
}
