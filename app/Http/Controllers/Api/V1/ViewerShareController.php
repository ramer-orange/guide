<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Itineraries\SaveViewerShare;
use App\Http\Resources\Api\V1\ItineraryResource;
use App\Models\TravelOverview;
use App\Support\SharedAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ViewerShareController
{
    private const FAILURE_LIMIT = 5;

    private const BLOCK_SECONDS = 900;

    private const REPEATED_ABUSE_SECONDS = 3600;

    public function status(TravelOverview $itinerary): JsonResponse
    {
        abort_unless($itinerary->sharedPasswords?->isActive(), 404);

        return response()->json(['id' => $itinerary->id, 'title' => $itinerary->title, 'available' => true]);
    }

    public function verify(Request $request, TravelOverview $itinerary): JsonResponse
    {
        $data = $request->validate(['shared_password' => ['required', 'string', 'max:255']]);
        $share = $itinerary->sharedPasswords;
        $keys = $this->rateLimitKeys($request, $itinerary);
        if (RateLimiter::tooManyAttempts($keys['long_block'], 1)
            || RateLimiter::tooManyAttempts($keys['failures'], self::FAILURE_LIMIT)) {
            return response()->json(['message' => 'Too many attempts. Try again later.'], 429);
        }
        if (! $share?->isActive() || ! Hash::check($data['shared_password'], $share->shared_password)) {
            $count = RateLimiter::hit($keys['failures'], self::BLOCK_SECONDS);
            if ($count === self::FAILURE_LIMIT) {
                $abuseCount = RateLimiter::hit($keys['abuse'], self::REPEATED_ABUSE_SECONDS);
                if ($abuseCount > 1) {
                    RateLimiter::hit($keys['long_block'], self::REPEATED_ABUSE_SECONDS);
                }
            }
            if ($count >= self::FAILURE_LIMIT) {
                return response()->json(['message' => 'Too many attempts. Try again later.'], 429);
            }
            throw ValidationException::withMessages(['shared_password' => ['The password is incorrect or sharing is inactive.']]);
        }
        foreach ($keys as $key) {
            RateLimiter::clear($key);
        }
        SharedAccess::grant($request, $share);

        return response()->json((new ItineraryResource($itinerary->fresh()))->resolve($request));
    }

    public function update(Request $request, TravelOverview $itinerary, SaveViewerShare $saveViewerShare): JsonResponse
    {
        Gate::authorize('manageViewerShare', $itinerary);
        $existing = $itinerary->sharedPasswords;
        $requiresPassword = ! $existing || $existing->lifecycleElapsed() || ! $existing->shared_password;
        $data = $request->validate([
            'shared_password' => [$requiresPassword ? 'required' : 'sometimes', 'nullable', 'string', 'min:8', 'max:32', 'confirmed'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ]);
        if (! array_key_exists('shared_password', $data) && ! array_key_exists('expires_at', $data)) {
            return response()->json(['message' => 'Provide a password or expiry update.'], 422);
        }
        $saveViewerShare->handle($itinerary, $data['shared_password'] ?? null, $data['expires_at'] ?? null);

        return response()->json((new ItineraryResource($itinerary->fresh()))->resolve($request));
    }

    public function destroy(Request $request, TravelOverview $itinerary, SaveViewerShare $saveViewerShare): JsonResponse
    {
        Gate::authorize('manageViewerShare', $itinerary);
        $saveViewerShare->revoke($itinerary);
        SharedAccess::forget($request, $itinerary->id);

        return response()->json((new ItineraryResource($itinerary->fresh()))->resolve($request));
    }

    private function rateLimitKeys(Request $request, TravelOverview $overview): array
    {
        $hashedIp = hash('sha256', (string) $request->ip());
        $base = "shared-access:{$overview->id}:ip:{$hashedIp}";

        return ['failures' => "{$base}:failures", 'abuse' => "{$base}:abuse", 'long_block' => "{$base}:long-block"];
    }
}
