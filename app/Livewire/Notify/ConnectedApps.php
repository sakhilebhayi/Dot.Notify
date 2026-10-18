<?php

namespace App\Livewire\Notify;

use App\Models\NotifyInboundEvent;
use App\Models\NotifyRule;
use App\Models\Team;
use App\Services\Notify\EventEnabler;
use App\Services\Notify\NotifyEventCatalogue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * "Connected apps": the events each Dot app can send, whether this company has
 * switched each on, and when each last arrived. Everyone on the team may look;
 * switching an event is owner/admin only (the same standing as creating a rule),
 * checked again on every action because a Livewire call can be crafted.
 */
class ConnectedApps extends Component
{
    /** Said after the last switch, for screen readers and sighted users alike. */
    public string $notice = '';

    private function team(): Team
    {
        return Auth::user()?->currentTeam ?? abort(403, 'No active team selected.');
    }

    /**
     * @return array<string, array<string, mixed>> platform key => platform with its events, each carrying state and last-received
     */
    #[Computed]
    public function apps(): array
    {
        $team = $this->team();
        $catalogue = app(NotifyEventCatalogue::class);
        $states = app(EventEnabler::class)->states($team);
        $last = $this->lastReceived();
        $apps = [];

        foreach ($catalogue->platforms() as $key => $platform) {
            foreach ($platform['events'] as $event => $definition) {
                $platform['events'][$event] = $definition + [
                    'state' => $states[$event] ?? EventEnabler::OFF,
                    'last' => $last[$key.'|'.$event] ?? null,
                ];
            }

            $platform['on'] = collect($platform['events'])->where('state', '!=', EventEnabler::OFF)->count();
            $platform['total'] = count($platform['events']);
            // A blank ingest secret means the app cannot call Notify at all (the ingest API answers 401).
            $platform['connected'] = (string) (config('notify.ingest.secrets')[$key] ?? '') !== '';
            $apps[$key] = $platform;
        }

        return $apps;
    }

    /**
     * The newest event this company received per platform + event name.
     *
     * @return array<string, array{at: Carbon, status: string}>
     */
    private function lastReceived(): array
    {
        // HasTeamScope limits this to the signed-in user's current company.
        $newest = NotifyInboundEvent::query()
            ->whereNotNull('platform')
            ->selectRaw('max(id) as id')
            ->groupBy('platform', 'trigger_event')
            ->pluck('id');

        $last = [];

        foreach (NotifyInboundEvent::query()->whereIn('id', $newest)->get() as $row) {
            $last[$row->platform.'|'.$row->trigger_event] = ['at' => $row->created_at, 'status' => $row->status];
        }

        return $last;
    }

    public function toggle(string $event): void
    {
        $team = $this->team();
        Gate::authorize('create', [NotifyRule::class, $team]);

        $definition = app(NotifyEventCatalogue::class)->find($event) ?? abort(404);
        $enabler = app(EventEnabler::class);
        $label = $definition['label'];

        if ($enabler->state($team, $event) !== EventEnabler::OFF) {
            $enabler->disable($team, $event);
            $this->notice = "{$label} is off.";
        } else {
            $this->notice = match ($enabler->enable($team, $definition['platform'], $event)) {
                EventEnabler::NO_CHANNEL => "{$label} is on, but nothing will be sent until you add an email channel.",
                EventEnabler::BLOCKED => "{$label} is on, but its channel or template is switched off, so nothing will be sent.",
                default => "{$label} is on.",
            };
        }

        unset($this->apps);
    }

    /**
     * An event switched on before the company had an email channel: attach the
     * channel it has now (enabling again fills in only what is missing).
     */
    public function attachChannel(string $event): void
    {
        $team = $this->team();
        Gate::authorize('create', [NotifyRule::class, $team]);

        $definition = app(NotifyEventCatalogue::class)->find($event) ?? abort(404);
        $enabler = app(EventEnabler::class);
        $channel = $enabler->firstEmailChannel($team);

        if ($channel === null) {
            $this->notice = 'There is still no active email channel. Add one under Channels on the dashboard first.';

            return;
        }

        $enabler->enable($team, $definition['platform'], $event);
        $this->notice = "{$definition['label']} now sends through {$channel->name}.";

        unset($this->apps);
    }

    public function render()
    {
        return view('livewire.notify.connected-apps', ['team' => $this->team()]);
    }
}
