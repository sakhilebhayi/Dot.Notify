<?php

namespace App\Services\Analytics;

use App\Models\NotifyLog;
use App\Models\Team;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * The daily numbers this app publishes to Dot.Analytics, for one company at a time, taken from its delivery
 * log (notify_logs). Pure queries: nothing is written, and only counts come out (never a recipient, subject,
 * body, event name or error text).
 *
 * The keys are exactly Dot.Analytics' catalogue for platform `notify` (config/metrics.php there):
 * sent_7d, failed_7d, suppressed_7d. None is money, so there is no `currency`.
 *
 * Company: a non-personal team linked to InfoDot (teams.infodot_team_uuid); a log belongs to the company in
 * notify_logs.team_id. The query names the team and bypasses HasTeamScope explicitly (that scope depends on
 * who is signed in), so the numbers are the same whoever, or nobody, is authenticated.
 *
 * Windows are UTC and rolling: the 7 days ending at $asOf, both ends inclusive. A message counts when it
 * reached its outcome, not when it was queued:
 *  - sent_7d: status sent (or the legacy delivered/opened/clicked, as NotifyLog::wasSent() reads them) with
 *    sent_at in the window. A sent row with no sent_at is not guessed into any window.
 *  - failed_7d: status failed (or legacy bounced, as NotifyLog::hasFailed()) whose last write was in the
 *    window. There is no failed_at column; failed is terminal, so updated_at is when it failed.
 *  - suppressed_7d: status suppressed, same reasoning (suppressed is terminal; updated_at is when the
 *    recipient's opt-out stopped it).
 * Queued, sending and retrying messages have no outcome yet and are in none of the three.
 */
class MetricsCollector
{
    private const SENT = ['sent', 'delivered', 'opened', 'clicked'];

    private const FAILED = ['failed', 'bounced'];

    /** @return Collection<int, Team> companies Analytics can be told about, optionally just one uuid */
    public function companies(?string $uuid = null): Collection
    {
        return Team::query()
            ->where('personal_team', false)
            ->whereNotNull('infodot_team_uuid')
            ->where('infodot_team_uuid', '!=', '')
            ->when($uuid !== null, fn ($q) => $q->where('infodot_team_uuid', $uuid))
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{metrics: array<string, int>, omitted: array<string, string>}
     */
    public function collect(Team $team, ?CarbonInterface $asOf = null): array
    {
        $end = ($asOf ?? now())->copy()->utc();
        $start = $end->copy()->subDays(7);

        $in = fn (array $values) => implode(',', array_fill(0, count($values), '?'));

        $row = NotifyLog::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->toBase()
            ->selectRaw('COALESCE(SUM(CASE WHEN status IN ('.$in(self::SENT).') AND sent_at >= ? AND sent_at <= ? THEN 1 ELSE 0 END), 0) as sent', [...self::SENT, $start, $end])
            ->selectRaw('COALESCE(SUM(CASE WHEN status IN ('.$in(self::FAILED).') AND updated_at >= ? AND updated_at <= ? THEN 1 ELSE 0 END), 0) as failed', [...self::FAILED, $start, $end])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? AND updated_at >= ? AND updated_at <= ? THEN 1 ELSE 0 END), 0) as suppressed', ['suppressed', $start, $end])
            ->first();

        return [
            'metrics' => [
                'sent_7d' => (int) $row->sent,
                'failed_7d' => (int) $row->failed,
                'suppressed_7d' => (int) $row->suppressed,
            ],
            'omitted' => [],
        ];
    }
}
