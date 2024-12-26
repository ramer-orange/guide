<?php

namespace App\Livewire\Traits;

trait UpdateOrder
{
    /**
     * 指定した配列の要素を新しい順番で並び替え
     *
     * @param array $list
     * @param array $orderedIds
     * @return array
     */
    public function updateOrder(array $list, array $orderedIds)
    {
        // $orderedIdsからIDだけを抽出
        $orderedIds = collect($orderedIds)->pluck('value');

        // 新しい順序に基づいて$orderedIdsを再構築
        return $orderedIds->map(function ($id) use ($list) {
            return collect($list)->firstWhere('id', $id);
        })->filter() // 存在しないIDをフィルタリング
        // インデックスを0から振り直す
        ->values()->Toarray();
    }
}
