<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\PackingTemplateCatalog;
use Illuminate\Http\JsonResponse;

class PackingTemplateController
{
    public function index(): JsonResponse
    {
        $templates = [];
        foreach ([['domestic', '国内旅行'], ['overseas', '海外旅行']] as [$type, $label]) {
            $templates[] = [
                'type' => $type,
                'label' => $label,
                'items' => array_map(fn ($item) => $item['packing_name'], PackingTemplateCatalog::for($type)),
            ];
        }

        return response()->json(['templates' => $templates])->header('Cache-Control', 'private, no-store');
    }
}
