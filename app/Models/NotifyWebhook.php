<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class NotifyWebhook extends Model
{
    protected $table = 'notify_webhooks';

    protected $fillable = [
        'team_id',
        'name',
        'endpoint_token',
        'source',
        'event_map',
        'is_active',
        'last_received_at',
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

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->endpoint_token ??= Str::random(48);
        });
    }
}
