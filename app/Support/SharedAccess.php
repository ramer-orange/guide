<?php

namespace App\Support;

use App\Models\SharedPassword;
use App\Models\TravelOverview;
use Illuminate\Http\Request;

class SharedAccess
{
    public static function grant(Request $request, SharedPassword $sharedPassword): void
    {
        $request->session()->put(self::sessionKey($sharedPassword->travel_id), [
            'shared_password_id' => (int) $sharedPassword->getKey(),
            'access_version' => (int) $sharedPassword->access_version,
            'authorized_at' => now()->toIso8601String(),
        ]);
    }

    public static function forget(Request $request, string $travelId): void
    {
        $request->session()->forget(self::sessionKey($travelId));
        $request->session()->forget("access_granted_{$travelId}");
    }

    public static function hasValidAccess(Request $request, TravelOverview $overview): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        $sessionAccess = $request->session()->get(self::sessionKey($overview->id));

        if (! is_array($sessionAccess)) {
            return false;
        }

        $sharedPasswordId = filter_var(
            $sessionAccess['shared_password_id'] ?? null,
            FILTER_VALIDATE_INT,
        );

        if (! $sharedPasswordId) {
            self::forget($request, $overview->id);

            return false;
        }

        $sharedPassword = SharedPassword::query()
            ->whereKey($sharedPasswordId)
            ->where('travel_id', $overview->id)
            ->first();

        if (! $sharedPassword?->isActive()) {
            self::forget($request, $overview->id);

            return false;
        }

        $currentSharedPasswordId = SharedPassword::query()
            ->where('travel_id', $overview->id)
            ->latest('created_at')
            ->latest('id')
            ->value('id');

        if ((int) $currentSharedPasswordId !== (int) $sharedPassword->getKey()) {
            self::forget($request, $overview->id);

            return false;
        }

        if ((int) ($sessionAccess['access_version'] ?? 0) !== (int) $sharedPassword->access_version) {
            self::forget($request, $overview->id);

            return false;
        }

        return true;
    }

    public static function sessionKey(string $travelId): string
    {
        return "shared_access.{$travelId}";
    }
}
