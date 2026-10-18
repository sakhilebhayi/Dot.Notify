<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Structural enforcement of wiki.md §5's governance note (echoing Dot.Brain's
 * ingested view §8): "no notification class may trigger on recipient
 * absence... trigger conditions must reference domain events, never
 * recipient-behaviour gaps." Previously a documented convention only, never
 * enforced (wiki.md §6 Roadmap / Open Questions both named this gap).
 *
 * Matches condition KEYS against recipient-activity-field *patterns* —
 * inactivity, dormancy, "time since recipient did X" shapes — rather than a
 * fixed list of exact field names, since a denylist of literal names would
 * miss any variant spelling (lastLoginAt vs last_login_at vs days_inactive).
 */
class NoAbsenceTriggerConditions implements ValidationRule
{
    private const DENYLIST_PATTERNS = [
        '/inactiv/i',           // inactive, inactivity, days_inactive
        '/dormant/i',           // dormant, days_dormant
        '/no.?activity/i',      // no_activity, noActivity
        '/(days|weeks|hours).*(since|without)/i', // days_since_last_login, weeks_without_login
        '/last.?(login|seen|active|activity|purchase|order|visit|open|click)/i', // last_login_at, lastSeenAt
        '/(login|activity|engagement).?streak/i', // login_streak
        '/churn/i',              // churn_risk, is_churned
        '/idle/i',                // idle_days
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        foreach (array_keys($value) as $key) {
            if (! is_string($key)) {
                continue;
            }

            foreach (self::DENYLIST_PATTERNS as $pattern) {
                if (preg_match($pattern, $key) === 1) {
                    $fail("The condition key '{$key}' reads a recipient-activity field. Notification rules may only trigger on domain events, never recipient absence (see wiki.md §5).");

                    return;
                }
            }
        }
    }
}
