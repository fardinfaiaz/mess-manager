<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    // Roster: every room in the manager's mess
    public function index(Request $request)
    {
        $mess = $request->user()->activeMembership->mess;

        $rooms = $mess->rooms()
            ->with(['activeMembers.user', 'leader.user'])
            ->orderBy('name')
            ->get();

        $unassigned = $mess->activeMembers()->whereNull('room_id')->with('user')->get();
        $members = $mess->activeMembers()->with(['user', 'room'])->get();

        return view('rooms.index', compact('mess', 'rooms', 'unassigned', 'members'));
    }

    public function store(Request $request)
    {
        $mess = $request->user()->activeMembership->mess;

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:50',
                           Rule::unique('rooms')->where('mess_id', $mess->id)],
            'capacity' => ['required', 'integer', 'min:1', 'max:20'],
        ], [
            'name.unique' => 'This mess already has a room with that name.',
        ]);

        $mess->rooms()->create($data);

        return redirect()->route('rooms.index')->with('success', "Room {$data['name']} added.");
    }

    public function edit(Request $request, Room $room)
    {
        $this->ensureOwnRoom($request, $room);

        return view('rooms.edit', compact('room'));
    }

    public function update(Request $request, Room $room)
    {
        $this->ensureOwnRoom($request, $room);

        // Business rule: capacity can never go below the people already living there
        $occupancy = $room->occupancy();

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:50',
                           Rule::unique('rooms')->where('mess_id', $room->mess_id)->ignore($room->id)],
            'capacity' => ['required', 'integer', 'min:' . max(1, $occupancy), 'max:20'],
        ], [
            'name.unique'  => 'This mess already has a room with that name.',
            'capacity.min' => "Capacity must be at least :min (current occupancy is {$occupancy}).",
        ]);

        $room->update($data);

        return redirect()->route('rooms.index')->with('success', "Room {$room->name} updated.");
    }

    // Move a member into a room (or place a member who has no room yet)
    public function assignMember(Request $request)
    {
        $messId = $request->user()->activeMembership->mess_id;

        $data = $request->validate([
            'membership_id' => ['required', Rule::exists('memberships', 'id')
                                ->where('mess_id', $messId)->where('status', 'active')],
            'room_id'       => ['required', Rule::exists('rooms', 'id')->where('mess_id', $messId)],
        ]);

        $member = Membership::with('user')->find($data['membership_id']);
        $room = Room::find($data['room_id']);

        if ($member->room_id == $room->id) {
            return back()->with('error', "{$member->user->name} is already in room {$room->name}.");
        }
        if (! $room->hasFreeSeat()) {
            return back()->with('error', "Room {$room->name} is full.");
        }

        // Business rule: a leader who moves out stops leading the old room
        if ($member->role === 'room_leader') {
            $member->role = 'resident';
        }
        $member->room_id = $room->id;
        $member->save();

        return redirect()->route('rooms.index')
            ->with('success', "{$member->user->name} moved to room {$room->name}.");
    }

    // Set (or clear) the one leader of a room
    public function setLeader(Request $request, Room $room)
    {
        $this->ensureOwnRoom($request, $room);

        $data = $request->validate([
            'membership_id' => ['nullable', Rule::exists('memberships', 'id')
                                ->where('room_id', $room->id)->where('status', 'active')],
        ], [
            'membership_id.exists' => 'The leader must be someone living in this room.',
        ]);

        $member = ($data['membership_id'] ?? null) ? Membership::with('user')->find($data['membership_id']) : null;

        if ($member && $member->isManager()) {
            return back()->with('error', 'The manager cannot also be a room leader.');
        }

        // One leader per room: demote the current leader, then promote the new one
        DB::transaction(function () use ($room, $member) {
            $room->activeMembers()
                ->where('role', 'room_leader')
                ->when($member, fn ($q) => $q->where('id', '!=', $member->id))
                ->update(['role' => 'resident']);

            if ($member) {
                $member->update(['role' => 'room_leader']);
            }
        });

        return redirect()->route('rooms.index')->with('success', $member
            ? "{$member->user->name} is now leader of room {$room->name}."
            : "Room {$room->name} has no leader now.");
    }

    // A manager may only touch rooms of their own mess
    private function ensureOwnRoom(Request $request, Room $room): void
    {
        if ($room->mess_id != $request->user()->activeMembership->mess_id) {
            abort(404);
        }
    }
}