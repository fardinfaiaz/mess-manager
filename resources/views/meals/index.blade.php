@extends('layouts.app')

@section('title', 'Meals — Mess-Manager')

@section('content')
    <h1>Daily Meals &amp; Guest Booking</h1>

    @if ($errors->any())
        <div class="flash flash-error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Date selector --}}
    <div class="card">
        <form method="GET" action="{{ route('meals.index') }}">
            <label for="date">Select Date</label>
            <input type="date" name="date" id="date" value="{{ $selectedDate }}" required>
            <button type="submit" class="btn btn-sm">View Date</button>
        </form>
        @if ($isPastDate)
            <p class="error">This date is in the past. Meal changes and cancellations are locked.</p>
        @endif
    </div>

    {{-- Mess-wide daily meal counts --}}
    <div class="card">
        <h2>Mess Meal Summary ({{ $selectedDate }})</h2>
        <table>
            <thead>
                <tr>
                    <th>Slot</th>
                    <th>Residents ON</th>
                    <th>Guests</th>
                    <th>Total Meals</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($slots as $slot)
                    <tr>
                        <td>{{ ucfirst($slot) }}</td>
                        <td>{{ $slotSummary[$slot]['residents_on'] }}</td>
                        <td>{{ $slotSummary[$slot]['guests'] }}</td>
                        <td><strong>{{ $slotSummary[$slot]['total'] }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- My daily meal ON/OFF --}}
    <div class="card">
        <h2>My Meals ({{ $selectedDate }})</h2>
        <table>
            <thead>
                <tr>
                    <th>Slot</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($slots as $slot)
                    @php
                        $entry = $myEntries->get($slot);
                        $isOn = $entry ? $entry->is_on : true;
                    @endphp
                    <tr>
                        <td>{{ ucfirst($slot) }}</td>
                        <td><strong>{{ $isOn ? 'ON' : 'OFF' }}</strong></td>
                        <td>
                            @if ($isPastDate)
                                Locked
                            @else
                                <form method="POST" action="{{ route('meals.toggle') }}" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="date" value="{{ $selectedDate }}">
                                    <input type="hidden" name="slot" value="{{ $slot }}">
                                    <button type="submit" class="btn btn-sm">
                                        Turn {{ $isOn ? 'OFF' : 'ON' }}
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Book a guest meal --}}
    @if (!$isPastDate)
        <div class="card">
            <h2>Book Guest Meal ({{ $selectedDate }})</h2>
            <form method="POST" action="{{ route('meals.guest.store') }}">
                @csrf
                <input type="hidden" name="date" value="{{ $selectedDate }}">

                <label for="slot">Meal Slot</label>
                <select name="slot" id="slot" required>
                    @foreach ($slots as $slot)
                        <option value="{{ $slot }}" {{ old('slot') === $slot ? 'selected' : '' }}>
                            {{ ucfirst($slot) }}
                        </option>
                    @endforeach
                </select>

                <label for="count">Number of Guests</label>
                <input type="number" name="count" id="count" min="1" max="20" value="{{ old('count', 1) }}" required>

                <button type="submit" class="btn">Book Guest Meal</button>
            </form>
        </div>
    @endif

    {{-- My guest meals for selected date --}}
    <div class="card">
        <h2>My Guest Meals ({{ $selectedDate }})</h2>
        <table>
            <thead>
                <tr>
                    <th>Slot</th>
                    <th>Guests</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($myGuestMeals as $guestMeal)
                    <tr>
                        <td>{{ ucfirst($guestMeal->slot) }}</td>
                        <td>{{ $guestMeal->count }}</td>
                        <td>
                            @if ($guestMeal->isLocked())
                                Locked
                            @else
                                <form method="POST" action="{{ route('meals.guest.cancel', $guestMeal) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-sm">Cancel</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">No guest meals booked for this date.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection