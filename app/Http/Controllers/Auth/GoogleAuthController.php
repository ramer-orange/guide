<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            report($exception);

            return $this->failedAuthentication();
        }

        $raw = $googleUser->getRaw();
        $googleId = trim((string) $googleUser->getId());
        $email = mb_strtolower(trim((string) $googleUser->getEmail()));

        if ($googleId === ''
            || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || ! is_array($raw)
            || ($raw['email_verified'] ?? null) !== true) {
            return $this->failedAuthentication();
        }

        try {
            $user = DB::transaction(function () use ($googleUser, $googleId, $email) {
                $providerUser = User::where('google_id', $googleId)->lockForUpdate()->first();
                $emailUsers = User::whereRaw('LOWER(email) = ?', [$email])
                    ->lockForUpdate()
                    ->get();

                if ($emailUsers->count() > 1) {
                    return null;
                }

                $emailUser = $emailUsers->first();

                if ($providerUser && $emailUser && ! $providerUser->is($emailUser)) {
                    return null;
                }

                if (! $providerUser && $emailUser?->google_id !== null) {
                    return null;
                }

                $user = $providerUser ?? $emailUser ?? new User;
                $user->fill([
                    'google_id' => $googleId,
                    'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: $email,
                    'email' => $email,
                    'avatar' => $googleUser->getAvatar(),
                ]);
                $user->email_verified_at = now();
                $user->save();

                return $user;
            });
        } catch (QueryException) {
            $user = null;
        }

        if (! $user) {
            return $this->failedAuthentication();
        }

        Auth::login($user);
        request()->session()->regenerate();

        return redirect()->intended(route('itineraries.index', absolute: false));
    }

    private function failedAuthentication(): RedirectResponse
    {
        return redirect()->route('home')->with(
            'auth_error',
            'Google アカウントを確認できませんでした。別のアカウントとの関連付けを確認してください。',
        );
    }
}
