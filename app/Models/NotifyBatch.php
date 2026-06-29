<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotifyBatch extends Model
{
    protected $table = 'notify_batches';

    protected $fillable = [
        'team_id',
        'template_id',
        'name',
        'status',
        'total_recipients',
        'sent_count',
        'failed_count',
        'scheduled_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function deliveryRate(): float
    {
        return $this->total_recipients > 0
            ? round(($this->sent_count / $this->total_recipients) * 100, 1)
            : 0;
    }
}
