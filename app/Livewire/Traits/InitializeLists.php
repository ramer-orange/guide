<?php
namespace App\Livewire\Traits;

/*
 * 初期化に関連するデフォルトデータに関するトレイト。
 */
trait InitializeLists
{
    /**
     * プラン情報の初期値を取得する
     *
     * @return array
     */
    public function getDefaultPlan(): array
    {
        return
        [
            //プラン
            'date' => '',
            'time' => '',
            'plans_title' => '',
            'content' => '',

            // 新規ファイルアップロード
            'planFiles' => [null],
        ];
    }

    /**
     * 持ち物情報の初期値を取得する
     *
     * @return array
     */
    public function getDefaultPackingItems(): array
    {
        return
        [
            'packing_name' => '',
            'packing_is_checked' => false,
        ];
    }

    /**
     * お土産情報の初期値を取得する
     *
     * @return array
     */
    public function getDefaultSouvenirs(): array
    {
        return
            [
                'souvenir_name' => '',
                'souvenir_is_checked' => false,
            ];
    }

    /**
     * メモ欄情報の初期値を取得する
     *
     * @return array
     */
    public function getDefaultAdditionalComments(): array
    {
        return
            [
                'additionalComment_title' => '',
                'additionalComment_text' => '',
            ];
    }
}
