<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class NotifyWebhook extends Model
{
    protected $table = 'notify_webhooks';

    protected $fillable = [
        'team_id',
        'name',
        'endpoint_token',
        'signing_secret',
        'source',
        'event_map',
        'is_active',
        'last_received_at',
    ];

    protected $hidden = [
        'signing_secret',
    ];

    protected $casts = [
        'event_map'        => 'array',
        'is_active'        => 'boolean',
        'last_received_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function inboundEvents(): HasMany
    {
        return $this->hasMany(NotifyInboundEvent::class);
    }

    /**
     * Resolve a third-party event name (e.g. Stripe's `invoice.payment_failed`)
     * to one of this team's `trigger_event` values via `event_map`, so an
     * inbound webhook can be routed into the existing NotifyRule pipeline
     * instead of a parallel one. Returns null when there's no mapping, in
     * which case the inbound event is recorded but not routed.
     */
    public function resolveTriggerEvent(?string $sourceEvent): ?string
    {
        if ($sourceEvent === null) {
            return null;
        }

        $map = $this->event_map ?? [];

        return $map[$sourceEvent] ?? null;
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->endpoint_token ??= Str::random(48);
            $model->signing_secret ??= Str::random(64);
        });
    }
}
