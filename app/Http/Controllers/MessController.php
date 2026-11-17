<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\Mess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessController extends Controller
{
    public function create(Request $request)
    {
        if ($request->user()->activeMembership) {
            return redirect()->route('dashboard')->with('error', 'You already belong to a mess.');
        }

        return view('messes.create');
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->activeMembership) {
            return redirect()->route('dashboard')->with('error', 'You already belong to a mess.');
        }

        $data = $request->validate([
            'name'    => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'area'    => ['required', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($data, $user) {
            $mess = Mess::create($data + ['created_by' => $user->id]);

            Membership::create([
                'user_id'   => $user->id,
                'mess_id'   => $mess->id,
                'role'      => 'manager',
                'status'    => 'active',
                'joined_at' => now()->toDateString(),
            ]);
        });

        return redirect()->route('dashboard')->with('success', 'Mess created. You are its manager.');
    }
}