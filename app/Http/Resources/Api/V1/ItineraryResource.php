<?php

namespace App\Http\Resources\Api\V1;

use App\Models\TravelOverview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/** @mixin TravelOverview */
class ItineraryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing([
            'plans.planFiles', 'souvenirs', 'additionalComments', 'travelMembers.user', 'sharedPasswords', 'templateType',
        ]);
        $canEdit = Gate::allows('update', $this->resource);
        $canManageMembers = Gate::allows('manageMembers', $this->resource);
        $canManageShare = Gate::allows('manageViewerShare', $this->resource);
        $share = $this->sharedPasswords;

        return [
            'id' => (string) $this->id,
            'revision' => (int) $this->revision,
            'title' => $this->title,
            'overview_text' => $this->overviewText,
            'template_type' => $this->templateType?->template_name,
            'plans' => $this->plans->sortBy('order')->values()->map(fn ($plan) => [
                'id' => (int) $plan->id,
                'date' => $plan->date ? substr((string) $plan->date, 0, 10) : null,
                'time' => $plan->time ? substr((string) $plan->time, 0, 5) : null,
                'title' => $plan->plans_title,
                'content' => $plan->content,
                'order' => (int) $plan->order,
                'files' => $plan->planFiles->map(fn ($file) => [
                    'id' => (int) $file->id,
                    'file_name' => $file->file_name,
                    'url' => route('api.v1.itineraries.files.show', [$this->id, $file->id]),
                    'preview_url' => $file->previewUrl(),
                ])->values(),
            ])->values(),
            'packing_items' => $canEdit ? $this->packingItems()
                ->where('user_id', $request->user()?->id)
                ->orderBy('order')->get()->map(fn ($item) => [
                    'id' => (int) $item->id,
                    'name' => $item->packing_name,
                    'is_checked' => (bool) $item->packing_is_checked,
                    'order' => (int) $item->order,
                ])->values() : [],
            'souvenirs' => $this->souvenirs->sortBy('order')->values()->map(fn ($item) => [
                'id' => (int) $item->id,
                'name' => $item->souvenir_name,
                'is_checked' => (bool) $item->souvenir_is_checked,
                'order' => (int) $item->order,
            ])->values(),
            'notes' => $this->additionalComments->sortBy('order')->values()->map(fn ($item) => [
                'id' => (int) $item->id,
                'title' => $item->additionalComment_title,
                'text' => $item->additionalComment_text,
                'order' => (int) $item->order,
            ])->values(),
            'members' => $canManageMembers ? $this->travelMembers->map(fn ($member) => [
                'id' => (int) $member->id,
                'user' => [
                    'id' => (int) $member->user->id,
                    'name' => $member->user->name,
                    'email' => $member->user->email,
                ],
                'role' => $member->role,
            ])->values() : [],
            'permissions' => [
                'can_edit' => $canEdit,
                'can_delete' => Gate::allows('delete', $this->resource),
                'can_manage_members' => $canManageMembers,
                'can_manage_viewer_share' => $canManageShare,
            ],
            'viewer_share' => $canManageShare ? [
                'active' => (bool) $share?->isActive(),
                'expires_at' => $share?->isActive() ? $share->expires_at?->toIso8601String() : null,
            ] : ['active' => (bool) $share?->isActive()],
        ];
    }
}
