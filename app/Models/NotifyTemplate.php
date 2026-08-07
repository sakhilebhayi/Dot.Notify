<?php

namespace App\Models;

use App\Models\Concerns\HasTeamScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotifyTemplate extends Model
{
    use HasTeamScope;

    protected $table = 'notify_templates';

    protected $fillable = [
        'team_id',
        'name',
        'slug',
        'subject',
        'body',
        'variables',
        'channel_type',
        'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function render(array $vars = []): string
    {
        $body = $this->body;
        foreach ($vars as $key => $value) {
            $body = str_replace('{{ '.$key.' }}', $value, $body);
        }

        return $body;
    }
}
