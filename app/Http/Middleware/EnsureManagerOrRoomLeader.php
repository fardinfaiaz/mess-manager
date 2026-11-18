<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureManagerOrRoomLeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $membership = $request->user()?->activeMembership()->first();

        if (! $membership || ! in_array($membership->role, ['manager', 'room_leader'], true)) {
            abort(403, 'Only a mess manager or room leader can do this.');
        }

        return $next($request);
    }
}
