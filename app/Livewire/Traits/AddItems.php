<?php
namespace App\Livewire\Traits;

trait AddItems
{
    /**
     * 指定した位置に新しいプランを追加
     *
     * @param int $index
     * @return void
     */
    public function addPlan($index)
    {
        array_splice($this->plans, $index + 1, 0, [
             [
                 //プラン
                 'date' => '',
                 'time' => '',
                 'plans_title' => '',
                 'content' => '',

                 // 新規ファイルアップロード用
                 'planFiles' => [null],
             ]
        ]);
    }

    /**
     * 新しいファイルを追加
     *
     * @param int $index
     * @return void
     */
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
     * @param int $index
     * @return void
     */
    public function addPackingItem($index)
    {
        // 追加ボタンを押した箇所の次に挿入
        array_splice($this->packingItems, $index + 1, 0, [
            [
                'packing_name' => '',
                'packing_is_checked' => false,
            ]
        ]);
    }

    /**
     * 指定した位置に新しいお土産を追加
     *
     * @param int $index
     * @return void
     */
    public function addSouvenir($index)
    {
        array_splice($this->souvenirs, $index + 1, 0, [
            [
                'souvenir_name' => '',
                'souvenir_is_checked' => false,
            ]
        ]);
    }

    /**
     * 指定した位置に新しい自由記述欄を追加
     *
     * @param int $index
     * @return void
     */
    public function addAdditionalComment($index)
    {
        array_splice($this->additionalComments, $index + 1, 0, [
            [
                'additionalComment_title' => '',
                'additionalComment_text' => '',
            ]
        ]);
    }

}


