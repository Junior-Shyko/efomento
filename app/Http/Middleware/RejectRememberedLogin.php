<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RejectRememberedLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        // Old remember-me cookies must not skip the new email challenge.
        if ($guard->check() && $guard->viaRemember()) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
