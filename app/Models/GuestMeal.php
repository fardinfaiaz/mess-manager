<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuestMeal extends Model
{
    protected $fillable = [
        'membership_id',
        'date',
        'slot',
        'count',
    ];

    protected $casts = [
        'date' => 'date',
        'count' => 'integer',
    ];

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    public function isLocked(): bool
    {
        return $this->date->lt(now()->startOfDay());
    }

    public function cancel(): void
    {
        $this->delete();
    }
}