<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Signs out a suspended user on their next request, even if they were already logged in. */
class EnsureNotSuspended
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->isSuspended()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => 'This account has been suspended.'], 403)
                : redirect()->route('login')->withErrors(['email' => 'This account has been suspended.']);
        }

        return $next($request);
    }
}
