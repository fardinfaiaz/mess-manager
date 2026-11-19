<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    public const CATEGORIES = [
        'BAZAR',
        'ELECTRICITY',
        'GAS',
        'WATER',
        'INTERNET',
        'REPAIR',
        'OTHER',
    ];

    protected $fillable = [
        'billing_period_id',
        'membership_id',
        'category',
        'amount',
        'date',
        'note',
        'receipt_url',
        'paid_out_of_pocket',
        'status',
        'rejection_reason',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'paid_out_of_pocket' => 'boolean',
    ];

    public function billingPeriod()
    {
        return $this->belongsTo(BillingPeriod::class);
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    public function isBazar(): bool
    {
        return $this->category === 'BAZAR';
    }

    public function approve(): void
    {
        $this->update([
            'status' => 'approved',
            'rejection_reason' => null,
        ]);
    }

    public function reject(string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ]);
    }
}