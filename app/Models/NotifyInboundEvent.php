<?php

namespace App\Models;

use App\Models\Concerns\HasTeamScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A signature-verified request: either a third party calling
 * POST /webhooks/{token} (App\Http\Controllers\WebhookInboundController,
 * notify_webhook_id set) or a Dot app calling POST /api/ingest/events
 * (App\Http\Controllers\Api\IngestEventController, platform and
 * idempotency_key set, notify_webhook_id null). Only ever created for
 * requests that passed HMAC verification. Rejected requests (bad signature,
 * missing signature, unknown token) are never persisted here.
 *
 * Ingest statuses: routed (a NotifyLog was queued), no_rule (the company has
 * no active rule for the event), ignored (rule rate ceiling), failed (the
 * rule is unusable, see `note`).
 */
class NotifyInboundEvent extends Model
{
    use HasTeamScope;

    protected $table = 'notify_inbound_events';

    protected $fillable = [
        'team_id',
        'notify_webhook_id',
        'notify_log_id',
        'platform',
        'idempotency_key',
        'payload',
        'source_event',
        'trigger_event',
        'verified_at',
        'status',
        'note',
    ];

    protected $casts = [
        'payload' => 'array',
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
