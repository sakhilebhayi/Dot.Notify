<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessDnaProfile extends Model
{
    protected $fillable = [
        'team_id', 'industry', 'operational_patterns', 'seasonal_trends',
        'risk_tolerance', 'growth_signals', 'last_computed_at',
    ];

    protected $casts = [
        'operational_patterns' => 'array',
        'seasonal_trends'      => 'array',
        'risk_tolerance'       => 'array',
        'growth_signals'       => 'array',
        'last_computed_at'     => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
