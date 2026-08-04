<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\HasTeamScope;

class NotifyRule extends Model
{
    use HasTeamScope;

    protected $table = 'notify_rules';

    protected $fillable = [
        'team_id',
        'template_id',
        'channel_id',
        'trigger_event',
        'conditions',
        'is_active',
    ];

    protected $casts = [
        'conditions' => 'array',
        'is_active'  => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotifyTemplate::class, 'template_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(NotifyChannel::class, 'channel_id');
    }
}
