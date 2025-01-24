<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckAuthOrSharedAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check())
        {
            return $next($request);
        }

        // 共有パスワードを入力した際の認証
        $travelId = $request->route('overview')->id;
        if (session()->has("access_granted_$travelId")) {
            return $next($request);
        }

        return redirect()->route('shared-access.show', ['id' => $travelId]);
    }
}
