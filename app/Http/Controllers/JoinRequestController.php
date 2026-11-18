<?php

namespace App\Http\Controllers;

use App\Models\JoinRequest;
use App\Models\Membership;
use App\Models\Mess;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class JoinRequestController extends Controller
{
    // Visitor: search active messes that still have free seats
    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $messes = Mess::where('status', 'active')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                      ->orWhere('area', 'like', "%{$q}%");
                });
            })
            ->with('rooms')
            ->orderBy('name')
            ->get()
            ->filter(fn ($mess) => $mess->vacantSeats() > 0);

        return view('join_requests.search', compact('messes', 'q'));
    }

    // Visitor: send a join request
    public function store(Request $request, Mess $mess)
    {
        $user = $request->user();

        if ($user->activeMembership) {
            return back()->with('error', 'You already belong to a mess.');
        }
        if ($user->joinRequests()->where('status', 'pending')->exists()) {
            return back()->with('error', 'You already have a pending request. Wait for a decision first.');
        }
        if ($mess->status !== 'active' || $mess->vacantSeats() === 0) {
            return back()->with('error', 'This mess is not accepting members right now.');
        }

        $data = $request->validate([
            'preferred_room_id' => ['nullable', Rule::exists('rooms', 'id')->where('mess_id', $mess->id)],
            'message'           => ['nullable', 'string', 'max:500'],
        ]);

        $user->joinRequests()->create($data + ['mess_id' => $mess->id]);

        return redirect()->route('dashboard')->with('success', "Request sent to {$mess->name}.");
    }

    // Manager: pending requests for my mess
    public function index(Request $request)
    {
        $mess = $request->user()->activeMembership->mess;

        $pending = $mess->joinRequests()
            ->where('status', 'pending')
            ->with(['user', 'preferredRoom'])
            ->oldest()
            ->get();

        $rooms = $mess->rooms()->orderBy('name')->get()->filter(fn ($room) => $room->hasFreeSeat());

        return view('join_requests.index', compact('mess', 'pending', 'rooms'));
    }

    // Manager: approve into a room with a free seat
    public function approve(Request $request, JoinRequest $joinRequest)
    {
        $this->ensurePendingForMyMess($request, $joinRequest);

        $data = $request->validate([
            'room_id' => ['required', Rule::exists('rooms', 'id')->where('mess_id', $joinRequest->mess_id)],
        ]);

        $room = Room::find($data['room_id']);

        if (! $room->hasFreeSeat()) {
            return back()->with('error', "Room {$room->name} is full.");
        }
        if ($joinRequest->user->activeMembership) {
            return back()->with('error', 'This person has already joined a mess.');
        }

        // Membership + request decision are saved together, or not at all
        DB::transaction(function () use ($request, $joinRequest, $room) {
            Membership::create([
                'user_id'   => $joinRequest->user_id,
                'mess_id'   => $joinRequest->mess_id,
                'room_id'   => $room->id,
                'role'      => 'resident',
                'status'    => 'active',
                'joined_at' => now()->toDateString(),
            ]);

            $joinRequest->update([
                'status'     => 'approved',
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
            ]);
        });

        return redirect()->route('join.index')
            ->with('success', "{$joinRequest->user->name} joined room {$room->name}.");
    }

    // Manager: reject with a reason
    public function reject(Request $request, JoinRequest $joinRequest)
    {
        $this->ensurePendingForMyMess($request, $joinRequest);

        $data = $request->validate([
            'decision_reason' => ['required', 'string', 'max:255'],
        ]);

        $joinRequest->update($data + [
            'status'     => 'rejected',
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        return redirect()->route('join.index')->with('success', 'Request rejected.');
    }

    private function ensurePendingForMyMess(Request $request, JoinRequest $joinRequest): void
    {
        if ($joinRequest->mess_id != $request->user()->activeMembership->mess_id) {
            abort(404);
        }
        if (! $joinRequest->isPending()) {
            abort(422, 'This request has already been decided.');
        }
    }
}