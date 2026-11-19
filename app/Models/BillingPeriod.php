<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingPeriod extends Model
{
    protected $fillable = [
        'mess_id',
        'month',
        'year',
        'status',
        'closed_at',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
    ];

    public function mess()
    {
        return $this->belongsTo(Mess::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}