<?php

namespace App\Http\Middleware;

use App\Support\SharedAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckAuthOrSharedAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            return $next($request);
        }

        $overview = $request->route('overview');
        if ($overview && SharedAccess::hasValidAccess($request, $overview)) {
            return $next($request);
        }

        abort_unless($overview, 404);

        return redirect()->route('shared-access.show', ['id' => $overview->id]);
    }
}
