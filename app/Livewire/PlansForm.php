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
    public $overviewText;
    public $plans = [];
    public $useTemplatePackingItem = false;
    public $packingItems = [];
    public $template_type;

    public $souvenirs = [];

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

        $this->template_type = null;

        //持ち物リスト
        $this->packingItems[] = [
            'packing_name' => '',
            'packing_is_checked' => false,
        ];

        //お土産リスト
        $this->souvenirs[] = [
            'souvenir_name' => '',
            'souvenir_is_checked' => false,
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

        $this->packingItems = $template[$type];
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
        array_splice($this->souvenirs, $souvenirIndex + 1, 0, [
            [
                'souvenir_name' => '',
                'souvenir_is_checked' => false,
            ]
        ]);
    }

    /**
     * 指定した位置のプランを削除し、インデックスを再構築
     *
     * @param int $index
     * @return void
     */
    public function removePlan($index)
    {
        unset($this->plans[$index]);
        $this->plans = array_values($this->plans);
    }

    /**
     * 指定した位置のファイルを削除し、インデックスを再構築
     *
     * @param int $index
     * @param int $fileIndex
     * @return void
     */
    public function removePlanFiles($index, $fileIndex)
    {
        unset($this->plans[$index]['planFiles'][$fileIndex]);
        $this->plans[$index]['planFiles'] = array_values($this->plans[$index]['planFiles']);
    }

    /**
     * 指定した位置の持ち物を削除し、インデックスを再構築
     *
     * @param int $packingIndex
     * @return void
     */
    public function removePackingItem($packingIndex)
    {
        unset($this->souvenirs[$packingIndex]);
        $this->souvenirs = array_values($this->souvenirs);
    }

    /**
     * 指定した位置のお土産を削除し、インデックスを再構築
     *
     * @param int $souvenirIndex
     * @return void
     */
    public function removeSouvenir($packingIndex)
    {
        unset($this->souvenirs[$packingIndex]);
        $this->souvenirs = array_values($this->souvenirs);
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
        $this->template_type = null;
    }

    /**
     * 全てのお土産を一括削除してリセット
     * @return void
     */
    public function allRemoveSouvenir()
    {
        $this->souvenirs = [
            [
                'souvenir_name' => '',
                'souvenir_is_checked' => false
            ]
        ];
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
            'template_type' => 'nullable | string | max:255',
            'souvenirs' => 'required | array',
            'souvenirs.*.souvenirs_name' => 'nullable | string | max:255',
            'souvenirs.*.souvenirs_is_checked' => 'nullable | boolean',
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
        return redirect()->route('itineraries.edit', [$overview->id]);
    }

    public function render()
    {
        return view('livewire.plans-form');
    }
}
