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

    /**
     * Fill the {{ variable }} slots of the body. Values are put in as plain text
     * in one pass, so a value that itself looks like a slot is not filled again.
     *
     * With $dropEmptyLines (used for catalogued events) a line whose slots are all
     * empty is left out, so an optional field that was not sent leaves no empty
     * "Due:" label behind, and trailing spaces and runs of blank lines are tidied.
     *
     * @param  array<string, string>  $vars
     */
    public function render(array $vars = [], bool $dropEmptyLines = false): string
    {
        $body = (string) $this->body;

        if (! $dropEmptyLines) {
            return self::fillSlots($body, $vars);
        }

        $lines = [];

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $slots = 0;
            $filled = 0;
            $text = self::fillSlots($line, $vars, $slots, $filled);

            if ($slots > 0 && $filled === 0) {
                continue;
            }

            $lines[] = rtrim($text);
        }

        return trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
    }

    /**
     * The subject line with its slots filled, on one line.
     *
     * @param  array<string, string>  $vars
     */
    public function renderSubject(array $vars = []): string
    {
        return trim((string) preg_replace('/\s+/', ' ', self::fillSlots((string) $this->subject, $vars)));
    }

    /**
     * @param  array<string, mixed>  $vars
     * @param  int  $slots  out: how many known slots the text has
     * @param  int  $filled  out: how many of those got a non-empty value
     */
    private static function fillSlots(string $text, array $vars, int &$slots = 0, int &$filled = 0): string
    {
        return (string) preg_replace_callback('/\{\{ (.+?) \}\}/', function (array $match) use ($vars, &$slots, &$filled): string {
            if (! array_key_exists($match[1], $vars)) {
                return $match[0];
            }

            $value = (string) $vars[$match[1]];
            $slots++;
            $filled += $value !== '' ? 1 : 0;

            return $value;
        }, $text);
    }
}
