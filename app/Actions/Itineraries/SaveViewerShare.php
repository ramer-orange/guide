<?php

namespace App\Actions\Itineraries;

use App\Models\SharedPassword;
use App\Models\TravelOverview;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SaveViewerShare
{
    public function handle(TravelOverview $itinerary, ?string $password, mixed $expiresAt = null): SharedPassword
    {
        return DB::transaction(function () use ($itinerary, $password, $expiresAt) {
            $overview = TravelOverview::whereKey($itinerary->id)->lockForUpdate()->firstOrFail();
            $current = $overview->sharedPasswords()->first();
            $newLifecycle = ! $current || $current->lifecycleElapsed();

            if (($newLifecycle || ! $current?->shared_password) && ! filled($password)) {
                throw ValidationException::withMessages([
                    'shared_password' => ['A password is required to start a new sharing period.'],
                ]);
            }

            $maximum = $newLifecycle ? now()->addDays(SharedPassword::MAX_LIFETIME_DAYS) : $current->maximumExpiresAt();
            $expiry = $expiresAt
                ? Carbon::parse($expiresAt)
                : ($newLifecycle || ! $current?->expires_at ? SharedPassword::defaultExpiresAt() : $current->expires_at->copy());
            if (! $expiry->isFuture() || $expiry->greaterThan($maximum)) {
                throw ValidationException::withMessages([
                    'expires_at' => ['The expiry must be in the future and within the maximum sharing lifetime.'],
                ]);
            }

            if ($newLifecycle) {
                return $overview->sharedPasswordHistory()->create([
                    'shared_password' => Hash::make($password),
                    'expires_at' => $expiry,
                    'disabled_at' => null,
                    'access_version' => 1,
                ]);
            }

            $current->update([
                'shared_password' => filled($password) ? Hash::make($password) : $current->shared_password,
                'expires_at' => $expiry,
                'disabled_at' => null,
                'access_version' => $current->nextVersion(),
            ]);

            return $current;
        });
    }

    public function revoke(TravelOverview $itinerary): void
    {
        DB::transaction(function () use ($itinerary) {
            $overview = TravelOverview::whereKey($itinerary->id)->lockForUpdate()->firstOrFail();
            $share = $overview->sharedPasswords()->first();
            if (! $share) {
                return;
            }
            $share->update([
                'shared_password' => null,
                'expires_at' => null,
                'disabled_at' => now(),
                'access_version' => $share->nextVersion(),
            ]);
        });
    }
}
