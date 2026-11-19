@extends('layouts.app')

@section('title', 'Expenses — Mess-Manager')

@section('content')
    <h1>Utility &amp; Bazar Expenses ({{ $period->month }}/{{ $period->year }})</h1>

    @if ($errors->any())
        <div class="flash flash-error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <strong>Approved Bazar Total (Feeds Meal Rate):</strong> {{ number_format($approvedBazarTotal, 2) }} BDT<br>
        <strong>Approved Utility Total (Shared Expenses):</strong> {{ number_format($approvedUtilityTotal, 2) }} BDT
    </div>

    <div class="card">
        <h2>Submit Expense Request</h2>
        <form method="POST" action="{{ route('expenses.store') }}">
            @csrf

            <label for="category">Category</label>
            <select name="category" id="category" required>
                @foreach ($categories as $category)
                    <option value="{{ $category }}" {{ old('category') === $category ? 'selected' : '' }}>
                        {{ $category }}
                    </option>
                @endforeach
            </select>

            <label for="amount">Amount (BDT)</label>
            <input type="number" name="amount" id="amount" step="0.01" min="1" value="{{ old('amount') }}" required>

            <label for="date">Date</label>
            <input type="date" name="date" id="date" value="{{ old('date', now()->toDateString()) }}" required>

            <label for="note">Note (Optional)</label>
            <input type="text" name="note" id="note" value="{{ old('note') }}">

            <label for="receipt_url">Receipt URL (Optional)</label>
            <input type="url" name="receipt_url" id="receipt_url" value="{{ old('receipt_url') }}">

            <label style="font-weight: normal;">
                <input type="checkbox" name="paid_out_of_pocket" value="1" {{ old('paid_out_of_pocket') ? 'checked' : '' }}>
                Paid out of pocket
            </label>

            <button type="submit" class="btn">Submit Expense</button>
        </form>
    </div>

    <div class="card">
        <h2>Submitted Expenses</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>By</th>
                    <th>Category</th>
                    <th>Amount</th>
                    <th>Status</th>
                    @if (strtolower($membership->role) === 'manager')
                        <th>Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($expenses as $expense)
                    <tr>
                        <td>{{ $expense->date->format('Y-m-d') }}</td>
                        <td>{{ $expense->membership->user->name }}</td>
                        <td>
                            {{ $expense->category }}
                            @if ($expense->note)
                                <br><small>{{ $expense->note }}</small>
                            @endif
                            @if ($expense->receipt_url)
                                <br><small><a href="{{ $expense->receipt_url }}" target="_blank">Receipt</a></small>
                            @endif
                        </td>
                        <td>
                            {{ number_format($expense->amount, 2) }}
                            @if ($expense->paid_out_of_pocket)
                                <br><small>(Out of pocket)</small>
                            @endif
                        </td>
                        <td>
                            {{ ucfirst($expense->status) }}
                            @if ($expense->status === 'rejected' && $expense->rejection_reason)
                                <br><small class="error">Reason: {{ $expense->rejection_reason }}</small>
                            @endif
                        </td>

                        @if (strtolower($membership->role) === 'manager')
                            <td>
                                @if ($expense->status === 'pending')
                                    <form method="POST" action="{{ route('expenses.approve', $expense) }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-sm">Approve</button>
                                    </form>

                                    <form method="POST" action="{{ route('expenses.reject', $expense) }}" style="margin-top: 6px;">
                                        @csrf
                                        <input type="text" name="rejection_reason" placeholder="Rejection reason" required>
                                        <button type="submit" class="btn btn-sm">Reject</button>
                                    </form>
                                @else
                                    -
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No expenses submitted for this billing period yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection