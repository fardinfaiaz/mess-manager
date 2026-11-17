<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;
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

        return view('rooms.index', compact('mess', 'rooms', 'unassigned'));
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

    // A manager may only touch rooms of their own mess
    private function ensureOwnRoom(Request $request, Room $room): void
    {
        if ($room->mess_id != $request->user()->activeMembership->mess_id) {
            abort(404);
        }
    }
}