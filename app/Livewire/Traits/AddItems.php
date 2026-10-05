<?php

namespace App\Livewire\Traits;

trait AddItems
{
    /**
     * 指定した位置に新しいプランを追加
     *
     * @param  int  $index
     * @return void
     */
    public function addPlan($index)
    {
        array_splice($this->plans, $index + 1, 0, [
            $this->getDefaultPlan(),
        ]);
    }

    /**
     * 新しいファイルを追加
     *
     * @param  int  $index
     * @return void
     */
    public function addPlanFiles($index)
    {
        $this->plans[$index]['planFiles'][] = null;
    }

    /**
     * 持ち物リストのテンプレートの使用時に配列を初期化
     *
     * @param  string  $type
     * @return void
     */
    public function useTemplatePackingItems($type)
    {
        $this->useTemplatePackingItem = true;

        $this->template_type = $type;

        // 現在の持ち物リストをリセット
        $this->packingItems = [];

        $this->packingItems = \App\Support\PackingTemplateCatalog::for($type);
    }

    /**
     * 指定した位置に新しい持ち物項目を追加
     *
     * @param  int  $index
     * @return void
     */
    public function addPackingItem($index)
    {
        // 追加ボタンを押した箇所の次に挿入
        array_splice($this->packingItems, $index + 1, 0, [
            $this->getDefaultPackingItems(),
        ]);
    }

    /**
     * 指定した位置に新しいお土産を追加
     *
     * @param  int  $index
     * @return void
     */
    public function addSouvenir($index)
    {
        array_splice($this->souvenirs, $index + 1, 0, [
            $this->getDefaultSouvenirs(),
        ]);
    }

    /**
     * 指定した位置に新しい自由記述欄を追加
     *
     * @param  int  $index
     * @return void
     */
    public function addAdditionalComment($index)
    {
        array_splice($this->additionalComments, $index + 1, 0, [
            $this->getDefaultAdditionalComments(),
        ]);
    }
}
