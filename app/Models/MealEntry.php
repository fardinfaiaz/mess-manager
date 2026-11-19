<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MealEntry extends Model
{
    public const SLOTS = [
        'breakfast',
        'lunch',
        'dinner',
    ];

    protected $fillable = [
        'membership_id',
        'date',
        'slot',
        'is_on',
    ];

    protected $casts = [
        'date' => 'date',
        'is_on' => 'boolean',
    ];

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    public function isLocked(): bool
    {
        return $this->date->lt(now()->startOfDay());
    }

    public function toggle(): void
    {
        $this->update([
            'is_on' => !$this->is_on,
        ]);
    }
}