<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $membership = $request->user()?->activeMembership()->first();

        if (! $membership) {
            abort(403, 'You must belong to an active mess to access this page.');
        }

        return $next($request);
    }
}
