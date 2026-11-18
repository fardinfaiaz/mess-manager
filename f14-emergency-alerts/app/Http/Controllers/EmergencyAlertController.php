<?php

namespace App\Http\Controllers;

use App\Models\EmergencyAlert;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmergencyAlertController extends Controller
{
    // Alert list: managers also get the publish form and acknowledgement progress
    public function index(Request $request)
    {
        $membership = $this->membershipOf($request);
        $mess = $membership->mess;

        $alerts = $mess->emergencyAlerts()->withCount('acknowledgements')->latest()->get();

        return view('emergency_alerts.index', [
            'mess'         => $mess,
            'membership'   => $membership,
            'isManager'    => $membership->isManager(),
            'activeAlerts' => $alerts->filter->isActive()->values(),
            'pastAlerts'   => $alerts->reject->isActive()->take(10)->values(),
            'types'        => EmergencyAlert::TYPES,
        ]);
    }

    // Manager publishes an urgent alert
    public function store(Request $request)
    {
        $membership = $this->membershipOf($request);

        $data = $request->validate([
            'type'       => ['required', Rule::in(EmergencyAlert::TYPES)],
            'message'    => ['required', 'string', 'min:5', 'max:500'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $membership->mess->emergencyAlerts()->create([
            'created_by' => $request->user()->id,
            'type'       => $data['type'],
            'message'    => $data['message'],
            'expires_at' => $data['expires_at'] ?? null,
            'status'     => 'active',
        ]);

        return redirect()->route('alerts.index')->with('success', 'Emergency alert published.');
    }

    // Manager closes an alert once the situation is over
    public function resolve(Request $request, EmergencyAlert $alert)
    {
        $membership = $this->membershipOf($request);
        abort_unless($alert->mess_id === $membership->mess_id, 403, 'This alert belongs to another mess.');

        if ($alert->status === 'resolved') {
            return back()->with('error', 'This alert is already resolved.');
        }

        $alert->resolve();

        return redirect()->route('alerts.index')->with('success', 'Alert marked as resolved.');
    }

    // A resident confirms they have seen an active alert
    public function acknowledge(Request $request, EmergencyAlert $alert)
    {
        $membership = $this->membershipOf($request);
        abort_unless($alert->mess_id === $membership->mess_id, 403, 'This alert belongs to another mess.');

        if ($membership->isManager()) {
            return back()->with('error', 'The manager who publishes an alert does not need to acknowledge it.');
        }
        if (! $alert->isActive()) {
            return back()->with('error', 'This alert is no longer active.');
        }

        $alert->acknowledgeBy($membership);

        return back()->with('success', 'Thanks, your acknowledgement is recorded.');
    }

    // Only people who currently belong to a mess can use this feature
    private function membershipOf(Request $request)
    {
        $membership = $request->user()->activeMembership;
        abort_unless($membership, 403, 'You are not part of a mess.');

        return $membership;
    }
}
