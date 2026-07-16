<?php

namespace App\Http\Controllers;

use App\Models\TravelOverview;
use App\Support\SharedAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class SharedPasswordController extends Controller
{
    private const FAILURE_LIMIT = 5;

    private const BLOCK_SECONDS = 900;

    private const REPEATED_ABUSE_SECONDS = 3600;

    public function show($id)
    {
        $travelOverview = TravelOverview::findOrFail($id);
        abort_unless($travelOverview->sharedPasswords?->isActive(), 404);

        return view('itineraries.shared-access', compact('travelOverview'));
    }

    public function verify(Request $request, $id)
    {
        $request->validate([
            'shared_password' => 'required|string',
        ]);

        $overview = TravelOverview::findOrFail($id);
        $sharedPassword = $overview->sharedPasswords;
        $keys = $this->rateLimitKeys($request, $overview);

        if (RateLimiter::tooManyAttempts($keys['long_block'], 1)
            || RateLimiter::tooManyAttempts($keys['failures'], self::FAILURE_LIMIT)) {
            return $this->failedResponse();
        }

        if (! $sharedPassword?->isActive()
            || ! Hash::check($request->input('shared_password'), $sharedPassword->shared_password)) {
            $failureCount = RateLimiter::hit($keys['failures'], self::BLOCK_SECONDS);

            if ($failureCount === self::FAILURE_LIMIT) {
                $abuseCount = RateLimiter::hit($keys['abuse'], self::REPEATED_ABUSE_SECONDS);

                if ($abuseCount > 1) {
                    RateLimiter::hit($keys['long_block'], self::REPEATED_ABUSE_SECONDS);
                }
            }

            return $this->failedResponse();
        }

        foreach ($keys as $key) {
            RateLimiter::clear($key);
        }

        SharedAccess::grant($request, $sharedPassword);

        return redirect()->route('itineraries.edit', ['overview' => $id]);
    }

    private function failedResponse()
    {
        return back()->withErrors([
            'shared_password' => ['閲覧用パスワードが一致しないか、共有が無効です'],
        ]);
    }

    private function rateLimitKeys(Request $request, TravelOverview $overview): array
    {
        $hashedIp = hash('sha256', (string) $request->ip());
        $base = "shared-access:{$overview->id}:ip:{$hashedIp}";

        return [
            'failures' => "{$base}:failures",
            'abuse' => "{$base}:abuse",
            'long_block' => "{$base}:long-block",
        ];
    }
}
