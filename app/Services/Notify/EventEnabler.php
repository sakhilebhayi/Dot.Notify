<?php

namespace App\Services\Notify;

use App\Models\NotifyChannel;
use App\Models\NotifyRule;
use App\Models\NotifyTemplate;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Switches catalogued events on and off for ONE company, using that company's own
 * rules and templates (nothing new is invented for ingest):
 *
 *   enable   makes sure the company has an active NotifyRule for the event. If it
 *            has none, one is created from the catalogue default: the company's own
 *            copy of the default template (slug "catalogue.<event>"), pointed at the
 *            company's first active email channel. If a rule already exists (made by
 *            hand, or switched off earlier) it is switched back on as it is. A
 *            template the company edited is never overwritten, and a channel it
 *            chose is never replaced.
 *   disable  switches every active rule for the event off. Nothing is deleted.
 *
 * States, as the "Connected apps" page shows them:
 *   off         no active rule
 *   on          an active rule with an active channel and an active template
 *   no_channel  an active rule with no channel at all: the company has no email
 *               channel yet, so events arrive and nothing is sent (the ingest API
 *               records them as failed). Enabling again once a channel exists
 *               attaches it.
 *   blocked     an active rule whose channel or template is switched off or gone
 *
 * Who may call this is the caller's business (the page and the command check it).
 */
class EventEnabler
{
    public const OFF = 'off';

    public const ON = 'on';

    public const NO_CHANNEL = 'no_channel';

    public const BLOCKED = 'blocked';

    public function __construct(private readonly NotifyEventCatalogue $catalogue) {}

    /**
     * @return string the resulting state
     *
     * @throws InvalidArgumentException for an event the catalogue does not hold under that platform
     */
    public function enable(Team $team, string $platform, string $event): string
    {
        $definition = $this->catalogue->find($event, $platform)
            ?? throw new InvalidArgumentException("Event {$event} is not catalogued for {$platform}.");

        Cache::lock("notify-enable:{$team->id}:{$event}", 10)->block(5, function () use ($team, $definition, $event) {
            DB::transaction(function () use ($team, $definition, $event) {
                $rules = $this->rules($team, $event);
                $channel = $this->firstEmailChannel($team);

                if ($rules->isEmpty()) {
                    NotifyRule::create([
                        'team_id' => $team->id,
                        'template_id' => $this->defaultTemplate($team, $definition)->id,
                        'channel_id' => $channel?->id,
                        'trigger_event' => $event,
                        'is_active' => true,
                    ]);

                    return;
                }

                $active = $rules->where('is_active', true);
                $targets = $active->isNotEmpty() ? $active : collect([$rules->first()]);

                foreach ($targets as $rule) {
                    $rule->is_active = true;

                    // Fill only what is missing; what the company chose stays as it is.
                    if ($rule->channel_id === null && $channel !== null) {
                        $rule->channel_id = $channel->id;
                    }

                    if ($rule->template_id === null || ! NotifyTemplate::withoutGlobalScopes()->whereKey($rule->template_id)->exists()) {
                        $rule->template_id = $this->defaultTemplate($team, $definition)->id;
                    }

                    if ($rule->isDirty()) {
                        $rule->save();
                    }
                }
            });
        });

        return $this->state($team, $event);
    }

    /**
     * @return array<string, string> event => resulting state
     */
    public function enablePlatform(Team $team, string $platform): array
    {
        $events = $this->catalogue->platforms()[$platform]['events'] ?? throw new InvalidArgumentException("Unknown platform {$platform}.");
        $states = [];

        foreach (array_keys($events) as $event) {
            $states[$event] = $this->enable($team, $platform, $event);
        }

        return $states;
    }

    /**
     * @return int how many rules were switched off
     */
    public function disable(Team $team, string $event): int
    {
        return NotifyRule::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('trigger_event', $event)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    public function state(Team $team, string $event): string
    {
        return $this->states($team)[$event] ?? self::OFF;
    }

    /**
     * The state of every catalogued event for the company.
     *
     * @return array<string, string> event => state
     */
    public function states(Team $team): array
    {
        $events = array_keys($this->catalogue->all());

        $active = NotifyRule::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereIn('trigger_event', $events)
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->groupBy('trigger_event');

        $channels = NotifyChannel::withoutGlobalScopes()->where('team_id', $team->id)->get()->keyBy('id');
        $templates = NotifyTemplate::withoutGlobalScopes()->where('team_id', $team->id)->get()->keyBy('id');

        $states = [];

        foreach ($events as $event) {
            $rules = $active->get($event);
            $states[$event] = $rules === null ? self::OFF : $this->ruleState($rules, $channels, $templates);
        }

        return $states;
    }

    /**
     * Ingest sends through the oldest active rule, so that is the one that decides.
     *
     * @param  Collection<int, NotifyRule>  $rules
     * @param  Collection<int, NotifyChannel>  $channels
     * @param  Collection<int, NotifyTemplate>  $templates
     */
    private function ruleState($rules, $channels, $templates): string
    {
        $rule = $rules->first();

        if ($rule->channel_id === null) {
            return self::NO_CHANNEL;
        }

        $channel = $channels->get($rule->channel_id);
        $template = $rule->template_id ? $templates->get($rule->template_id) : null;

        return $channel?->is_active && $template?->is_active ? self::ON : self::BLOCKED;
    }

    /** The company's first active email channel, oldest first, or null when it has none. */
    public function firstEmailChannel(Team $team): ?NotifyChannel
    {
        return NotifyChannel::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('type', 'email')
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, NotifyRule> */
    private function rules(Team $team, string $event)
    {
        return NotifyRule::withoutGlobalScopes()->where('team_id', $team->id)->where('trigger_event', $event)->orderBy('id')->get();
    }

    /**
     * The company's own copy of the default template: found by its slug, so an
     * edited copy is reused as it is; created from the default only when missing.
     * Switched back on if it had been switched off.
     *
     * @param  array<string, mixed>  $definition
     */
    private function defaultTemplate(Team $team, array $definition): NotifyTemplate
    {
        $slug = 'catalogue.'.$definition['event'];
        $template = NotifyTemplate::withoutGlobalScopes()->where('team_id', $team->id)->where('slug', $slug)->orderBy('id')->first();

        if ($template === null) {
            $default = $this->catalogue->defaultTemplate($definition['event']);

            return NotifyTemplate::create([
                'team_id' => $team->id,
                'name' => $default['name'],
                'slug' => $slug,
                'subject' => $default['subject'],
                'body' => $default['body'],
                'variables' => $default['variables'],
                'channel_type' => $definition['channel'],
                'is_active' => true,
            ]);
        }

        if (! $template->is_active) {
            $template->update(['is_active' => true]);
        }

        return $template;
    }
}
