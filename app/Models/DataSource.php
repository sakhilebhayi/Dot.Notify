<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataSource extends Model
{
    protected $fillable = [
        'team_id', 'platform', 'display_name', 'base_url', 'status', 'last_synced_at', 'config',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'config'         => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(AnalyticsSnapshot::class);
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }
}
