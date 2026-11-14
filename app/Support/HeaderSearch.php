<?php

namespace App\Support;

use App\Models\NotifyBatch;
use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\NotifyRule;
use App\Models\NotifyTemplate;
use App\Models\User;
use App\Services\Notify\NotifyEventCatalogue;
use Illuminate\Support\Str;

/**
 * The header search (R-52): one question asked of this company's own messages log, rules, channels, templates, batches and the
 * events the connected apps can send.
 *
 * Scope: every query carries an explicit team_id of the signed-in person's CURRENT team (HasTeamScope also applies, but a
 * search must not depend on it). A person with no current team gets nothing, never an unscoped read.
 *
 * What it reads: the fields the log and the manager screens already print: recipient, subject, event, status, names, slugs, template
 * subjects. NEVER a message body, a channel's config (credentials) or a failure reason: those can hold personal data or secrets
 * and are not searched or returned. A log result is the log row as the log shows it: recipient, subject, event, status.
 *
 * Each result links to a screen this person already has: the log row (?log=ID, the existing selection), or the section of the
 * dashboard that manages the kind (#rules, #channels, #templates, #batches), or Connected apps.
 */
class HeaderSearch
{
    public const MIN_LENGTH = 2;

    public const MAX_LENGTH = 80;

    /** The words the log's status column can hold; a query that is one of them also matches that status. */
    private const STATUSES = ['queued', 'sending', 'retrying', 'sent', 'failed', 'suppressed'];

    /** What a person typed, trimmed to something safe to put in a LIKE. */
    public static function clean(?string $query): string
    {
        $query = trim((string) preg_replace('/\s+/u', ' ', (string) $query));
        // % and \ are the characters a LIKE gives a meaning to that a person did not intend; _ is harmless (one character).
        $query = str_replace(['%', '\\'], '', $query);

        return Str::limit($query, self::MAX_LENGTH, '');
    }

    /**
     * @return array{query: string, ready: bool, total: int, groups: array<int, array{key: string, label: string, items: array<int, array{title: string, meta: string, code: ?string, tag: string, href: string}>, more: int}>}
     */
    public static function run(?User $user, ?string $query, int $limit = 5): array
    {
        $q = self::clean($query);
        $empty = ['query' => $q, 'ready' => false, 'total' => 0, 'groups' => []];
        $team = $user?->currentTeam;

        if ($team === null || mb_strlen($q) < self::MIN_LENGTH) {
            return $empty;
        }

        $like = '%'.$q.'%';
        $groups = [];

        // The log: recipient, subject, event, status. Newest first.
        $logs = NotifyLog::query()->where('team_id', $team->id)
            ->where(function ($w) use ($like, $q) {
                $w->whereLike('recipient', $like)->orWhereLike('subject', $like)->orWhereLike('event', $like);

                if (in_array(mb_strtolower($q), self::STATUSES, true)) {
                    $w->orWhere('status', mb_strtolower($q));
                }
            })
            ->orderByDesc('created_at')->orderByDesc('id');
        $groups[] = self::group('log', 'Messages', $logs, $limit, fn (NotifyLog $l) => [
            'title' => (string) $l->recipient,
            'meta' => (string) ($l->subject ?: 'No subject'),
            'code' => $l->event ?: null,
            'tag' => ucfirst((string) $l->status),
            'href' => route('dashboard', ['log' => $l->id]).'#slip',
        ]);

        // Rules: the event they answer, or the template / channel they use.
        $rules = NotifyRule::query()->where('team_id', $team->id)->with(['template:id,name', 'channel:id,name'])
            ->where(function ($w) use ($like) {
                $w->whereLike('trigger_event', $like)
                    ->orWhereHas('template', fn ($t) => $t->whereLike('name', $like))
                    ->orWhereHas('channel', fn ($c) => $c->whereLike('name', $like));
            })
            ->orderBy('trigger_event');
        $groups[] = self::group('rules', 'Rules', $rules, $limit, fn (NotifyRule $r) => [
            'title' => (string) $r->trigger_event,
            'meta' => trim(($r->template?->name ?? 'No template').' through '.($r->channel?->name ?? 'no channel')),
            'code' => null,
            'tag' => $r->is_active ? 'On' : 'Off',
            'href' => route('dashboard').'#rules',
        ]);

        // Channels: name and type. Never the config.
        $channels = NotifyChannel::query()->where('team_id', $team->id)
            ->where(fn ($w) => $w->whereLike('name', $like)->orWhereLike('type', $like))
            ->orderBy('name');
        $groups[] = self::group('channels', 'Channels', $channels, $limit, fn (NotifyChannel $c) => [
            'title' => (string) $c->name,
            'meta' => ucfirst((string) $c->type),
            'code' => null,
            'tag' => $c->is_active ? 'On' : 'Off',
            'href' => route('dashboard').'#channels',
        ]);

        // Templates: name, slug, subject. Never the body.
        $templates = NotifyTemplate::query()->where('team_id', $team->id)
            ->where(fn ($w) => $w->whereLike('name', $like)->orWhereLike('slug', $like)->orWhereLike('subject', $like))
            ->orderBy('name');
        $groups[] = self::group('templates', 'Templates', $templates, $limit, fn (NotifyTemplate $t) => [
            'title' => (string) $t->name,
            'meta' => (string) ($t->subject ?: 'No subject'),
            'code' => $t->slug ?: null,
            'tag' => $t->is_active ? 'On' : 'Off',
            'href' => route('dashboard').'#templates',
        ]);

        // Batches: name and status.
        $batches = NotifyBatch::query()->where('team_id', $team->id)
            ->where(function ($w) use ($like, $q) {
                $w->whereLike('name', $like);

                if (in_array(mb_strtolower($q), ['pending', 'processing', 'completed', 'failed', 'scheduled'], true)) {
                    $w->orWhere('status', mb_strtolower($q));
                }
            })
            ->orderByDesc('created_at');
        $groups[] = self::group('batches', 'Batches', $batches, $limit, fn (NotifyBatch $b) => [
            'title' => (string) $b->name,
            'meta' => $b->sent_count.' of '.$b->total_recipients.' sent',
            'code' => null,
            'tag' => ucfirst((string) $b->status),
            'href' => route('dashboard').'#batches',
        ]);

        // Events the connected apps can send: the static catalogue, so nothing is queried.
        $needle = mb_strtolower($q);
        $events = [];

        foreach (app(NotifyEventCatalogue::class)->all() as $key => $event) {
            if (str_contains(mb_strtolower($key.' '.($event['label'] ?? '')), $needle)) {
                $events[] = [
                    'title' => (string) ($event['label'] ?? $key),
                    'meta' => (string) ($event['platform_name'] ?? ''),
                    'code' => (string) $key,
                    'tag' => 'Event',
                    'href' => route('connected-apps'),
                ];
            }
        }

        if ($events !== []) {
            $groups[] = ['key' => 'events', 'label' => 'Events apps can send', 'items' => array_slice($events, 0, $limit), 'more' => max(0, count($events) - $limit)];
        }

        $groups = array_values(array_filter($groups, fn (array $g) => $g['items'] !== []));

        return [
            'query' => $q,
            'ready' => true,
            'total' => array_sum(array_map(fn (array $g) => count($g['items']) + $g['more'], $groups)),
            'groups' => $groups,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $query
     * @return array{key: string, label: string, items: array<int, array<string, mixed>>, more: int}
     */
    private static function group(string $key, string $label, $query, int $limit, callable $row): array
    {
        $total = (clone $query)->reorder()->count();
        $rows = $query->limit($limit)->get();

        return [
            'key' => $key,
            'label' => $label,
            'items' => $rows->map($row)->all(),
            'more' => max(0, $total - $limit),
        ];
    }
}
