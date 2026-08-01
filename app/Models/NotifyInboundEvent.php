<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A signature-verified request received at POST /webhooks/{token}. Only
 * ever created for requests that passed HMAC verification — see
 * App\Http\Controllers\WebhookInboundController. Rejected requests (bad
 * signature, missing signature, unknown token) are never persisted here.
 */
class NotifyInboundEvent extends Model
{
    protected $table = 'notify_inbound_events';

    protected $fillable = [
        'team_id',
        'notify_webhook_id',
        'notify_log_id',
        'payload',
        'source_event',
        'trigger_event',
        'verified_at',
        'status',
        'note',
    ];

    protected $casts = [
        'payload'     => 'array',
        'verified_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(NotifyWebhook::class, 'notify_webhook_id');
    }

    public function log(): BelongsTo
    {
        return $this->belongsTo(NotifyLog::class, 'notify_log_id');
    }

    public function wasRouted(): bool
    {
        return $this->status === 'routed';
    }
}
