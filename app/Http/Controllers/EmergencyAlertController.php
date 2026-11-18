<?php

namespace App\Http\Controllers;

use App\Models\EmergencyAlert;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmergencyAlertController extends Controller
{
    // Everyone in the mess: managers get the control page, residents get the alert list
    public function index(Request $request)
    {
        $membership = $this->membership($request);
        $mess = $membership->mess;

        if ($membership->isManager()) {
            $alerts = $mess->emergencyAlerts()
                ->withCount('acknowledgements')
                ->latest()
                ->get();
            $memberCount = $mess->activeMembers()->where('role', '!=', 'manager')->count();

            return view('emergency_alerts.manage', compact('mess', 'alerts', 'memberCount'));
        }

        $active = $mess->emergencyAlerts()->active()->latest()->get();
        $closed = $mess->emergencyAlerts()->closed()->latest()->take(10)->get();
        $acknowledgedIds = $membership->alertAcknowledgements()->pluck('emergency_alert_id')->all();

        return view('emergency_alerts.index', compact('mess', 'active', 'closed', 'acknowledgedIds'));
    }

    // Manager: publish a new alert to the whole mess
    public function store(Request $request)
    {
        $membership = $this->membership($request);

        $data = $request->validate([
            'type'           => ['required', Rule::in(array_keys(EmergencyAlert::TYPES))],
            'message'        => ['required', 'string', 'min:5', 'max:1000'],
            'duration_hours' => ['required', 'integer', Rule::in(array_keys(EmergencyAlert::DURATIONS))],
        ], [
            'type.required'      => 'Choose what kind of emergency this is.',
            'message.required'   => 'Write the alert message.',
            'message.min'        => 'The message must be at least :min characters.',
            'duration_hours.in'  => 'Choose how long the alert should stay active.',
        ]);

        $membership->mess->emergencyAlerts()->create([
            'created_by' => $request->user()->id,
            'type'       => $data['type'],
            'message'    => $data['message'],
            'status'     => 'active',
            'expires_at' => now()->addHours((int) $data['duration_hours']),
        ]);

        return redirect()->route('alerts.index')->with('success', 'Emergency alert published.');
    }

    // Manager: who has seen the alert and who has not
    public function show(Request $request, EmergencyAlert $alert)
    {
        $this->ensureOwnAlert($request, $alert);

        $acknowledged = $alert->acknowledgements()->with('membership.user')->oldest('acknowledged_at')->get();
        $pending = $alert->pendingAcknowledgements();

        return view('emergency_alerts.show', compact('alert', 'acknowledged', 'pending'));
    }

    // Manager: close an alert once the problem is over
    public function resolve(Request $request, EmergencyAlert $alert)
    {
        $this->ensureOwnAlert($request, $alert);

        if ($alert->status === 'resolved') {
            return back()->with('error', 'This alert is already resolved.');
        }

        $alert->resolve();

        return redirect()->route('alerts.index')->with('success', 'Alert marked as resolved.');
    }

    // Member: confirm "I have seen this alert"
    public function acknowledge(Request $request, EmergencyAlert $alert)
    {
        $membership = $this->membership($request);
        $this->ensureOwnAlert($request, $alert);

        if ($membership->isManager()) {
            return back()->with('error', 'Managers publish alerts and do not need to acknowledge them.');
        }
        if (! $alert->isActive()) {
            return back()->with('error', 'This alert is no longer active.');
        }

        // The unique (alert, member) pair means a repeat click does nothing
        $alert->acknowledgements()->firstOrCreate(
            ['membership_id' => $membership->id],
            ['acknowledged_at' => now()]
        );

        return back()->with('success', 'Thanks, your acknowledgement was recorded.');
    }

    // The signed-in user's current membership; people outside a mess have no alerts
    private function membership(Request $request)
    {
        $membership = $request->user()->activeMembership;

        if (! $membership) {
            abort(403, 'Join a mess to see its emergency alerts.');
        }

        return $membership;
    }

    // Users may only touch alerts of their own mess
    private function ensureOwnAlert(Request $request, EmergencyAlert $alert): void
    {
        if ($alert->mess_id != $this->membership($request)->mess_id) {
            abort(404);
        }
    }
}
