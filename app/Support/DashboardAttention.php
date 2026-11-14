<?php

namespace App\Support;

use App\Models\NotifyBatch;
use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\NotifyRule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The "Needs attention" list on the dashboard: only things that are really wrong right now, each read from
 * a record this app writes. Every model here carries HasTeamScope, so each query is the current company's.
 * Nothing is invented: a kind with no rows adds no row.
 */
class DashboardAttention
{
    public const LIMIT = 6;

    /**
     * @return Collection<int, array{kind: string, mark: string, label: string, title: string, meta: string, href: string, code: ?string}>
     */
    public static function items(int $failedToday): Collection
    {
        $items = collect();

        if ($failedToday > 0) {
            $items->push([
                'kind' => 'failed', 'mark' => 'failed', 'label' => 'Failed', 'code' => null,
                'title' => $failedToday.' '.str('message')->plural($failedToday).' failed today',
                'meta' => $failedToday === 1
                    ? 'The channel refused it or could not be reached, and it will not be tried again.'
                    : 'The channel refused them or could not be reached, and they will not be tried again.',
                'href' => route('dashboard', ['status' => 'failed']).'#log',
            ]);
        }

        // New query: channels whose last test failed.
        NotifyChannel::where('test_status', 'failed')->orderBy('name')->limit(3)->get()->each(function (NotifyChannel $channel) use ($items) {
            $items->push([
                'kind' => 'channel_test', 'mark' => 'failed', 'label' => 'Test failed', 'code' => null,
                'title' => $channel->name.' failed its last test',
                'meta' => ($channel->last_tested_at ? 'Tested '.$channel->last_tested_at->diffForHumans().'. ' : '').'Messages through it may not get out.',
                'href' => route('dashboard').'#channels',
            ]);
        });

        // New query: rules that are on but route through a channel that is off. The channel is eager loaded,
        // so the list costs two queries however many rules there are.
        NotifyRule::with('channel')
            ->where('is_active', true)
            ->whereHas('channel', fn ($q) => $q->where('is_active', false))
            ->orderBy('trigger_event')
            ->limit(3)
            ->get()
            ->each(function (NotifyRule $rule) use ($items) {
                $items->push([
                    'kind' => 'rule_channel_off', 'mark' => 'waiting', 'label' => 'Not sending', 'code' => $rule->trigger_event,
                    'title' => ' sends through '.$rule->channel->name.', which is switched off',
                    'meta' => 'Nothing is sent for this event until the channel is switched back on.',
                    'href' => route('dashboard').'#rules',
                ]);
            });

        NotifyBatch::where('status', 'failed')->orderByDesc('created_at')->limit(2)->get()->each(function (NotifyBatch $batch) use ($items) {
            $items->push([
                'kind' => 'batch_failed', 'mark' => 'failed', 'label' => 'Failed', 'code' => null,
                'title' => $batch->name.' failed',
                'meta' => $batch->sent_count.' of '.$batch->total_recipients.' sent, '.$batch->created_at->diffForHumans().'.',
                'href' => route('dashboard').'#batches',
            ]);
        });

        $retrying = NotifyLog::where('status', 'retrying');
        $retryCount = (clone $retrying)->count();
        if ($retryCount > 0) {
            $next = (clone $retrying)->min('next_retry_at');
            $items->push([
                'kind' => 'retrying', 'mark' => 'retrying', 'label' => 'Retrying', 'code' => null,
                'title' => $retryCount.' '.str('message')->plural($retryCount).($retryCount === 1 ? ' is' : ' are').' being retried',
                'meta' => $next ? 'The next attempt is '.Carbon::parse($next)->diffForHumans().'.' : 'An attempt did not get through. They will be tried again automatically.',
                'href' => route('dashboard', ['status' => 'retrying']).'#log',
            ]);
        }

        return $items->take(self::LIMIT)->values();
    }
}
