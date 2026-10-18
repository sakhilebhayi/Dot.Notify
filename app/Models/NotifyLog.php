<?php

namespace App\Models;

use App\Models\Concerns\HasTeamScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One notification to one recipient. Statuses this app really produces:
 *
 *   queued     waiting for a worker
 *   sending    a worker claimed it and is mid-send
 *   retrying   a transient failure; next_retry_at says when it is tried again
 *   sent       the channel accepted it (SMTP / HTTP 2xx / bell row). Not proof of
 *              inbox delivery, opens or clicks: nothing reports those back
 *   failed     permanent failure or attempts exhausted; failure_reason says why
 *   suppressed the recipient opted out; failure_reason says which preference
 *
 * delivered / opened / clicked / bounced exist only on rows written before
 * this change; no code produces them.
 */
class NotifyLog extends Model
{
    use HasTeamScope;

    protected $table = 'notify_logs';

    protected $fillable = [
        'team_id',
        'channel_id',
        'template_id',
        'rule_id',
        'recipient',
        'subject',
        'body_preview',
        'body',
        'notification_type',
        'event',
        'status',
        'failure_reason',
        'attempts',
        'last_error',
        'next_retry_at',
        'last_attempt_at',
        'sent_at',
        'delivered_at',
        'opened_at',
    ];

    protected $hidden = [
        'body',
    ];

    protected $casts = [
        'body' => 'encrypted',
        'attempts' => 'integer',
        'next_retry_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(NotifyChannel::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(NotifyRule::class, 'rule_id');
    }

    /** Legacy rows only: nothing produces delivered/opened/clicked any more. Prefer wasSent(). */
    public function wasDelivered(): bool
    {
        return in_array($this->status, ['delivered', 'opened', 'clicked'], true);
    }

    public function wasSent(): bool
    {
        return in_array($this->status, ['sent', 'delivered', 'opened', 'clicked'], true);
    }

    public function hasFailed(): bool
    {
        return in_array($this->status, ['failed', 'bounced'], true);
    }
}
