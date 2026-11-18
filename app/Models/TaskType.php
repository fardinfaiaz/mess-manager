<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TaskType extends Model
{
    protected $fillable = [
        'mess_id',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function mess()
    {
        return $this->belongsTo(Mess::class);
    }

    public function assignments()
    {
        return $this->hasMany(TaskAssignment::class);
    }

    public function scopeForMess(Builder $query, int $messId): Builder
    {
        return $query->where('mess_id', $messId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }
}
