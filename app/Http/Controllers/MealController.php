<?php

namespace App\Http\Controllers;

use App\Models\GuestMeal;
use App\Models\MealEntry;
use App\Models\Membership;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MealController extends Controller
{
    public function index(Request $request)
    {
        $membership = Membership::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->firstOrFail();

        $selectedDate = $request->query('date', now()->toDateString());
        $isPastDate = Carbon::parse($selectedDate)->lt(now()->startOfDay());

        $myEntries = MealEntry::where('membership_id', $membership->id)
            ->whereDate('date', $selectedDate)
            ->get()
            ->keyBy('slot');

        $myGuestMeals = GuestMeal::where('membership_id', $membership->id)
            ->whereDate('date', $selectedDate)
            ->latest()
            ->get();

        // Daily mess-wide meal counts (active members default to ON unless turned OFF)
        $activeMemberIds = Membership::where('mess_id', $membership->mess_id)
            ->where('status', 'active')
            ->pluck('id');

        $totalActiveMembers = $activeMemberIds->count();

        $offCounts = MealEntry::whereIn('membership_id', $activeMemberIds)
            ->whereDate('date', $selectedDate)
            ->where('is_on', false)
            ->selectRaw('slot, count(*) as total')
            ->groupBy('slot')
            ->pluck('total', 'slot');

        $guestCounts = GuestMeal::whereIn('membership_id', $activeMemberIds)
            ->whereDate('date', $selectedDate)
            ->selectRaw('slot, sum(count) as total')
            ->groupBy('slot')
            ->pluck('total', 'slot');

        $slotSummary = [];
        foreach (MealEntry::SLOTS as $slot) {
            $residentsOn = $totalActiveMembers - ($offCounts[$slot] ?? 0);
            $guests = (int) ($guestCounts[$slot] ?? 0);
            $slotSummary[$slot] = [
                'residents_on' => $residentsOn,
                'guests' => $guests,
                'total' => $residentsOn + $guests,
            ];
        }

        return view('meals.index', [
            'membership' => $membership,
            'selectedDate' => $selectedDate,
            'isPastDate' => $isPastDate,
            'slots' => MealEntry::SLOTS,
            'myEntries' => $myEntries,
            'myGuestMeals' => $myGuestMeals,
            'slotSummary' => $slotSummary,
        ]);
    }

    public function toggle(Request $request)
    {
        $membership = Membership::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->firstOrFail();

        $validated = $request->validate([
            'date' => 'required|date',
            'slot' => 'required|in:' . implode(',', MealEntry::SLOTS),
        ]);

        if (Carbon::parse($validated['date'])->lt(now()->startOfDay())) {
            return back()->with('error', 'Past meals are locked and cannot be changed.');
        }

        $entry = MealEntry::where('membership_id', $membership->id)
            ->whereDate('date', $validated['date'])
            ->where('slot', $validated['slot'])
            ->first();

        if ($entry) {
            if ($entry->isLocked()) {
                return back()->with('error', 'This meal is locked and cannot be changed.');
            }
            $entry->toggle();
        } else {
            // Meals are ON by default, so the first toggle turns it OFF
            MealEntry::create([
                'membership_id' => $membership->id,
                'date' => $validated['date'],
                'slot' => $validated['slot'],
                'is_on' => false,
            ]);
        }

        return redirect()->route('meals.index', ['date' => $validated['date']])
            ->with('success', ucfirst($validated['slot']) . ' meal status updated.');
    }

    public function storeGuest(Request $request)
    {
        $membership = Membership::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->firstOrFail();

        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'slot' => 'required|in:' . implode(',', MealEntry::SLOTS),
            'count' => 'required|integer|min:1|max:20',
        ]);

        GuestMeal::create([
            'membership_id' => $membership->id,
            'date' => $validated['date'],
            'slot' => $validated['slot'],
            'count' => $validated['count'],
        ]);

        return redirect()->route('meals.index', ['date' => $validated['date']])
            ->with('success', 'Guest meal booked successfully.');
    }

    public function cancelGuest(Request $request, GuestMeal $guestMeal)
    {
        $membership = Membership::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->firstOrFail();

        if ($guestMeal->membership_id !== $membership->id) {
            abort(403);
        }

        if ($guestMeal->isLocked()) {
            return back()->with('error', 'Past guest meals cannot be canceled.');
        }

        $date = $guestMeal->date->toDateString();
        $guestMeal->cancel();

        return redirect()->route('meals.index', ['date' => $date])
            ->with('success', 'Guest meal canceled.');
    }
}