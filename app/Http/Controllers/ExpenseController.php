<?php

namespace App\Http\Controllers;

use App\Models\BillingPeriod;
use App\Models\Expense;
use App\Models\Membership;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $membership = Membership::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->firstOrFail();

        $period = BillingPeriod::firstOrCreate([
            'mess_id' => $membership->mess_id,
            'month' => now()->month,
            'year' => now()->year,
        ], [
            'status' => 'open',
        ]);

        $expenses = Expense::with('membership.user')
            ->where('billing_period_id', $period->id)
            ->latest('date')
            ->get();

        $approvedBazarTotal = $expenses
            ->where('status', 'approved')
            ->where('category', 'BAZAR')
            ->sum('amount');

        $approvedUtilityTotal = $expenses
            ->where('status', 'approved')
            ->where('category', '!=', 'BAZAR')
            ->sum('amount');

        return view('expenses.index', [
            'membership' => $membership,
            'period' => $period,
            'expenses' => $expenses,
            'categories' => Expense::CATEGORIES,
            'approvedBazarTotal' => $approvedBazarTotal,
            'approvedUtilityTotal' => $approvedUtilityTotal,
        ]);
    }

    public function store(Request $request)
    {
        $membership = Membership::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->firstOrFail();

        $period = BillingPeriod::firstOrCreate([
            'mess_id' => $membership->mess_id,
            'month' => now()->month,
            'year' => now()->year,
        ], [
            'status' => 'open',
        ]);

        if ($period->isClosed()) {
            return back()->with('error', 'This billing period is closed.');
        }

        $validated = $request->validate([
            'category' => 'required|in:' . implode(',', Expense::CATEGORIES),
            'amount' => 'required|numeric|min:1',
            'date' => 'required|date',
            'note' => 'nullable|string|max:500',
            'receipt_url' => 'nullable|url|max:255',
            'paid_out_of_pocket' => 'nullable|boolean',
        ]);

        Expense::create([
            'billing_period_id' => $period->id,
            'membership_id' => $membership->id,
            'category' => $validated['category'],
            'amount' => $validated['amount'],
            'date' => $validated['date'],
            'note' => $validated['note'] ?? null,
            'receipt_url' => $validated['receipt_url'] ?? null,
            'paid_out_of_pocket' => $request->boolean('paid_out_of_pocket'),
            'status' => 'pending',
        ]);

        return redirect()->route('expenses.index')
            ->with('success', 'Expense request submitted for manager review.');
    }

    public function approve(Request $request, Expense $expense)
    {
        $membership = Membership::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->firstOrFail();

        if ($expense->billingPeriod->mess_id !== $membership->mess_id) {
            abort(403);
        }

        if ($expense->status !== 'pending') {
            return back()->with('error', 'Only pending expenses can be approved.');
        }

        $expense->approve();

        return back()->with('success', 'Expense approved.');
    }

    public function reject(Request $request, Expense $expense)
    {
        $membership = Membership::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->firstOrFail();

        if ($expense->billingPeriod->mess_id !== $membership->mess_id) {
            abort(403);
        }

        if ($expense->status !== 'pending') {
            return back()->with('error', 'Only pending expenses can be rejected.');
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:255',
        ]);

        $expense->reject($validated['rejection_reason']);

        return back()->with('success', 'Expense rejected.');
    }
}