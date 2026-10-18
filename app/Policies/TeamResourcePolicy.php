<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Who may touch a team's Notify configuration (channels, rules, templates,
 * webhooks, schedules): anyone on the team may read it, but only the team
 * owner or an admin may create, change or delete it. Editors, and anyone else
 * on the team, are read-only; people outside the team can do nothing.
 *
 * Channels hold credentials and rules decide what gets sent to whom, so
 * "can edit" here deliberately means owner/admin, not just "is a member".
 */
abstract class TeamResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $resource): bool
    {
        return $this->belongsTo($user, $resource);
    }

    /**
     * Gate::allows('create', [NotifyChannel::class, $team]); with no team given,
     * the user's current team is used.
     */
    public function create(User $user, ?Team $team = null): bool
    {
        return $this->canManage($user, $team ?? $user->currentTeam);
    }

    public function update(User $user, Model $resource): bool
    {
        return $this->canManage($user, $resource->team);
    }

    public function delete(User $user, Model $resource): bool
    {
        return $this->canManage($user, $resource->team);
    }

    private function belongsTo(User $user, Model $resource): bool
    {
        return $resource->team !== null && $user->belongsToTeam($resource->team);
    }

    private function canManage(User $user, ?Team $team): bool
    {
        return $team !== null && ($user->ownsTeam($team) || $user->hasTeamRole($team, 'admin'));
    }
}
