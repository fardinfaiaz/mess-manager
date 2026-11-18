<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class EmergencyAlert extends Model
{
    public const TYPES = ['water', 'electricity', 'gas', 'safety', 'other'];

    protected $fillable = ['mess_id', 'created_by', 'type', 'message', 'status', 'expires_at', 'resolved_at'];

    protected $casts = [
        'expires_at'  => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function mess()
    {
        return $this->belongsTo(Mess::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acknowledgements()
    {
        return $this->hasMany(AlertAcknowledgement::class);
    }

    // An alert is "active" until the manager resolves it or its expiry time passes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function stateLabel(): string
    {
        if ($this->status === 'resolved') {
            return 'Resolved';
        }

        return $this->isActive() ? 'Active' : 'Expired';
    }

    public function resolve(): void
    {
        $this->update(['status' => 'resolved', 'resolved_at' => now()]);
    }

    public function isAcknowledgedBy(Membership $membership): bool
    {
        return $this->acknowledgements()->where('membership_id', $membership->id)->exists();
    }

    // Records the acknowledgement once; repeating it changes nothing
    public function acknowledgeBy(Membership $membership): AlertAcknowledgement
    {
        return $this->acknowledgements()->firstOrCreate(
            ['membership_id' => $membership->id],
            ['acknowledged_at' => now()]
        );
    }

    // Residents (not the manager) who have not acknowledged yet
    public function pendingAcknowledgements(): Collection
    {
        return $this->mess->activeMembers()
            ->where('role', '!=', 'manager')
            ->whereNotIn('id', $this->acknowledgements()->pluck('membership_id'))
            ->with('user')
            ->get();
    }

    // Alerts shown in the top banner: the manager sees every active alert,
    // everyone else only the active alerts they have not acknowledged yet
    public static function bannerFor(Membership $membership): Collection
    {
        $alerts = static::where('mess_id', $membership->mess_id)->active()->latest()->get();

        if ($membership->isManager()) {
            return $alerts;
        }

        $done = AlertAcknowledgement::where('membership_id', $membership->id)->pluck('emergency_alert_id');

        return $alerts->reject(fn ($alert) => $done->contains($alert->id))->values();
    }
}
