<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\Notify\EventEnabler;
use App\Services\Notify\NotifyEventCatalogue;
use Illuminate\Console\Command;

class EnableDefaultEvents extends Command
{
    protected $signature = 'notify:enable-defaults
        {team_uuid : The InfoDot company uuid (teams.infodot_team_uuid)}
        {platform? : Only this platform\'s events (tasks, hr, auction, ehail, farms, billing); all platforms when left out}
        {--events= : Comma-separated event names to switch on instead of every event of the platform}';

    protected $description = 'Switch on catalogued Dot app events for one company, creating its default templates and rules. Safe to repeat: edited templates and existing rules are kept.';

    public function handle(NotifyEventCatalogue $catalogue, EventEnabler $enabler): int
    {
        $team = Team::where('infodot_team_uuid', (string) $this->argument('team_uuid'))->first();

        if (! $team) {
            $this->error('No company is linked to that uuid here.');

            return self::FAILURE;
        }

        $platforms = $catalogue->platforms();
        $platform = $this->argument('platform');

        if ($platform !== null && ! isset($platforms[$platform])) {
            $this->error("Unknown platform \"{$platform}\". Known: ".implode(', ', array_keys($platforms)).'.');

            return self::FAILURE;
        }

        $events = $this->events($catalogue, $platforms, $platform);

        if ($events === null) {
            return self::FAILURE;
        }

        $noChannel = false;

        foreach ($events as $event => $owner) {
            $state = $enabler->enable($team, $owner, $event);
            $noChannel = $noChannel || $state === EventEnabler::NO_CHANNEL;
            $this->line(sprintf('%-28s %s', $event, $this->describe($state)));
        }

        if ($noChannel) {
            $this->warn('This company has no email channel yet: those events are on, but nothing is sent until one is added. Add an email channel, then run this command again to attach it.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array<string, mixed>>  $platforms
     * @return array<string, string>|null event => owning platform, or null after reporting a bad name
     */
    private function events(NotifyEventCatalogue $catalogue, array $platforms, ?string $platform): ?array
    {
        $named = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('events')))));

        if ($named === []) {
            $events = [];
            foreach ($platform !== null ? [$platforms[$platform]] : $platforms as $entry) {
                foreach (array_keys($entry['events']) as $event) {
                    $events[$event] = $entry['key'];
                }
            }

            return $events;
        }

        $events = [];
        $bad = [];

        foreach ($named as $event) {
            $owner = $catalogue->owner($event);

            if ($owner === null || ($platform !== null && $owner !== $platform)) {
                $bad[] = $event;

                continue;
            }

            $events[$event] = $owner;
        }

        if ($bad !== []) {
            $this->error('Not an event'.($platform !== null ? " of {$platform}" : '').' in the catalogue: '.implode(', ', $bad).'. Nothing was changed.');

            return null;
        }

        return $events;
    }

    private function describe(string $state): string
    {
        return match ($state) {
            EventEnabler::ON => 'on',
            EventEnabler::NO_CHANNEL => 'on, no email channel yet',
            EventEnabler::BLOCKED => 'on, but its channel or template is switched off',
            default => 'off',
        };
    }
}
