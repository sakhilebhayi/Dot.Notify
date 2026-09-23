<?php

namespace App\Models;

use App\Models\Concerns\HasTeamScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'rate_ceiling_per_hour',
        'is_active',
    ];

    protected $casts = [
        'conditions' => 'array',
        'is_active' => 'boolean',
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

    public function logs(): HasMany
    {
        return $this->hasMany(NotifyLog::class, 'rule_id');
    }

    /**
     * Governance hardening (wiki.md §6, citing the 2026-02 incident: "a
     * platform's alert class fired on a misconfigured threshold, sending
     * 40x normal volume in one day"). Opt-in — a rule with no
     * rate_ceiling_per_hour set is never capped, matching pre-existing
     * behavior for every rule created before this pass.
     */
    public function isOverRateCeiling(): bool
    {
        if (! $this->rate_ceiling_per_hour) {
            return false;
        }

        return $this->logs()->where('created_at', '>=', now()->subHour())->count() >= $this->rate_ceiling_per_hour;
    }
}
