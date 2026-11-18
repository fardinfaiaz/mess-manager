<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

class EmergencyAlert extends Model
{
    // Allowed alert types (shown in the form and used for validation)
    public const TYPES = [
        'water'       => 'Water shortage',
        'electricity' => 'Electricity problem',
        'gas'         => 'Gas problem',
        'safety'      => 'Safety / security',
        'other'       => 'Other',
    ];

    // How long an alert stays visible, chosen by the manager (hours => label)
    public const DURATIONS = [
        1  => '1 hour',
        3  => '3 hours',
        6  => '6 hours',
        12 => '12 hours',
        24 => '24 hours',
        48 => '2 days',
        72 => '3 days',
    ];

    protected $fillable = [
        'mess_id', 'created_by', 'type', 'message',
        'status', 'expires_at', 'resolved_at',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function mess()
    {
        return $this->belongsTo(Mess::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acknowledgements()
    {
        return $this->hasMany(AlertAcknowledgement::class);
    }

    // Alerts that are still open and not past their expiry time
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')->where('expires_at', '>', now());
    }

    // Alerts that were resolved or have expired
    public function scopeClosed(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('status', 'resolved')->orWhere('expires_at', '<=', now());
        });
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expires_at->isFuture();
    }

    // Label shown to users: Active, Resolved or Expired
    public function statusLabel(): string
    {
        if ($this->status === 'resolved') {
            return 'Resolved';
        }

        return $this->expires_at->isPast() ? 'Expired' : 'Active';
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    // Manager closes the alert before it expires
    public function resolve(): void
    {
        $this->update([
            'status'      => 'resolved',
            'resolved_at' => now(),
        ]);
    }

    public function isAcknowledgedBy(Membership $membership): bool
    {
        return $this->acknowledgements()->where('membership_id', $membership->id)->exists();
    }

    // Residents of the mess who have not acknowledged this alert yet
    public function pendingAcknowledgements(): Collection
    {
        $done = $this->acknowledgements()->pluck('membership_id');

        return Membership::where('mess_id', $this->mess_id)
            ->where('status', 'active')
            ->where('role', '!=', 'manager')   // the manager writes alerts, so is not expected to acknowledge
            ->whereNotIn('id', $done)
            ->with('user')
            ->get();
    }
}
