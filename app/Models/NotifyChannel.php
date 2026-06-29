<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotifyChannel extends Model
{
    protected $table = 'notify_channels';

    protected $fillable = [
        'team_id',
        'type',
        'name',
        'config',
        'is_active',
        'last_tested_at',
        'test_status',
    ];

    protected $casts = [
        'config'         => 'array',
        'is_active'      => 'boolean',
        'last_tested_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isHealthy(): bool
    {
        return $this->is_active && $this->test_status === 'ok';
    }
}
