<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureManager
{
    public function handle(Request $request, Closure $next): Response
    {
        $membership = $request->user()->activeMembership;

        if (! $membership || ! $membership->isManager()) {
            abort(403, 'Only the mess manager can do this.');
        }

        return $next($request);
    }
}