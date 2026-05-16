<?php

namespace App\Policies;

use App\Models\TravelOverview;
use App\Models\User;

class TravelOverviewPolicy
{
    public function view(?User $user, TravelOverview $overview): bool
    {
        return $this->update($user, $overview)
            || session()->has("access_granted_{$overview->id}");
    }

    public function update(?User $user, TravelOverview $overview): bool
    {
        return $this->own($user, $overview)
            || $this->collaborate($user, $overview);
    }

    public function manageMembers(?User $user, TravelOverview $overview): bool
    {
        return $this->own($user, $overview);
    }

    public function manageViewerShare(?User $user, TravelOverview $overview): bool
    {
        return $this->own($user, $overview);
    }

    public function delete(?User $user, TravelOverview $overview): bool
    {
        return $this->own($user, $overview);
    }

    private function own(?User $user, TravelOverview $overview): bool
    {
        return $user !== null && $overview->user_id === $user->id;
    }

    private function collaborate(?User $user, TravelOverview $overview): bool
    {
        return $user !== null
            && $overview->travelMembers()
                ->where('user_id', $user->id)
                ->exists();
    }
}
