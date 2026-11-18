<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $membership = $user->activeMembership;

        // F14: active emergency alerts of the user's mess, shown as a banner
        $activeAlerts = $membership
            ? $membership->mess->emergencyAlerts()->active()->latest()->get()
            : collect();
        $acknowledgedIds = $membership
            ? $membership->alertAcknowledgements()->pluck('emergency_alert_id')->all()
            : [];

        // F20: the latest current notices this user is allowed to read
        $latestNotices = $membership
            ? $membership->mess->notices()->visibleTo($membership)->current()
                ->orderByDesc('pinned')->latest()->take(3)->get()
            : collect();

        return view('dashboard', [
            'latestNotices'   => $latestNotices,
            'activeAlerts'    => $activeAlerts,
            'acknowledgedIds' => $acknowledgedIds,
            'user'         => $user,
            'membership'   => $membership,
            'requests'     => $user->joinRequests()->with('mess')->latest()->take(5)->get(),
            'pendingCount' => $membership?->isManager()
                ? $membership->mess->joinRequests()->where('status', 'pending')->count()
                : 0,
        ]);
    }
}