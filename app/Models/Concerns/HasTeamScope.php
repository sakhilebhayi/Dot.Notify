<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Dot.Notify is Jetstream-teams-scoped. Every model that owns a team_id
 * column applies this trait so a query against it is scoped to the
 * authenticated user's current team by default, mirroring Dot.Mines'
 * HasTeamFilters — the goal is that a forgotten where('team_id', ...)
 * call in a future controller/Livewire component can no longer leak
 * another team's rows, because the model itself never returns unscoped
 * results while a user is authenticated with a current team.
 *
 * NotifyPreference is deliberately NOT scoped by this trait — it's
 * genuinely per-user, not per-team (see wiki.md §3). WebhookInboundController
 * queries NotifyRule outside any authenticated session (an unauthenticated
 * inbound webhook receiver, scoped explicitly by the webhook's own
 * team_id) — Auth::check() is false there, so this scope correctly does
 * not apply and the controller's explicit where('team_id', ...) stays
 * load-bearing.
 */
trait HasTeamScope
{
    protected static function bootHasTeamScope(): void
    {
        static::addGlobalScope('team', function (Builder $builder): void {
            if (Auth::check() && Auth::user()->currentTeam) {
                $builder->where($builder->getModel()->getTable().'.team_id', Auth::user()->currentTeam->id);
            }
        });
    }
}
