<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotifyLog extends Model
{
    protected $table = 'notify_logs';

    protected $fillable = [
        'team_id',
        'channel_id',
        'template_id',
        'recipient',
        'subject',
        'body_preview',
        'status',
        'failure_reason',
        'sent_at',
        'delivered_at',
        'opened_at',
    ];

    protected $casts = [
        'sent_at'      => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at'    => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(NotifyChannel::class);
    }

    public function wasDelivered(): bool
    {
        return in_array($this->status, ['delivered', 'opened', 'clicked']);
    }

    public function hasFailed(): bool
    {
        return in_array($this->status, ['failed', 'bounced']);
    }
}
