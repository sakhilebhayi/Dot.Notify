<?php

namespace App\Livewire\Notify;

use App\Models\NotifyChannel;
use App\Models\NotifyRule;
use App\Models\NotifyTemplate;
use App\Models\Team;
use App\Rules\NoAbsenceTriggerConditions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Management UI for NotifyRule — the model/relationships have existed
 * since this platform's first migration, but nothing let a team create or
 * edit one outside tinker (README/wiki.md §6 both flag this). Trigger
 * events are arbitrary strings other platforms emit (`user.signup`,
 * `payment.failed`, `custom.*` — README §"Core Features"), not a fixed
 * enum, so it's a free-text field rather than a select.
 */
class RuleManager extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $triggerEvent = '';

    public string $conditionsJson = '';

    public string $templateId = '';

    public string $channelId = '';

    public string $rateCeilingPerHour = '';

    public bool $isActive = true;

    /**
     * currentTeam is null for a user with no team (e.g. a freshly
     * registered user who hasn't created/joined one, or one removed from
     * their last team) — see NotifyDashboardTest's no-team coverage and
     * the platform-loop pass notes in wiki.md.
     */
    private function resolveCurrentTeam(): ?Team
    {
        return Auth::user()?->currentTeam;
    }

    /**
     * No explicit team_id filter needed: NotifyRule's HasTeamScope trait
     * applies it automatically to every query against this model.
     */
    #[Computed]
    public function rules()
    {
        return NotifyRule::with(['template', 'channel'])->orderByDesc('created_at')->get();
    }

    #[Computed]
    public function templateOptions()
    {
        return NotifyTemplate::orderBy('name')->get();
    }

    #[Computed]
    public function channelOptions()
    {
        return NotifyChannel::orderBy('name')->get();
    }

    public function edit(int $id): void
    {
        $rule = NotifyRule::findOrFail($id);

        $this->editingId = $rule->id;
        $this->triggerEvent = $rule->trigger_event;
        $this->conditionsJson = $rule->conditions ? json_encode($rule->conditions, JSON_PRETTY_PRINT) : '';
        $this->templateId = (string) $rule->template_id;
        $this->channelId = (string) $rule->channel_id;
        $this->rateCeilingPerHour = $rule->rate_ceiling_per_hour !== null ? (string) $rule->rate_ceiling_per_hour : '';
        $this->isActive = $rule->is_active;
        $this->showForm = true;
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingId', 'triggerEvent', 'conditionsJson', 'templateId', 'channelId', 'rateCeilingPerHour', 'isActive', 'showForm']);
        $this->isActive = true;
    }

    public function saveRule(): void
    {
        // Wire:click-reachable action on an already-loaded page (embedded
        // in the dashboard) — abort rather than redirect if the team
        // disappeared after render, matching the ecosystem's
        // "No active team selected." convention.
        $team = $this->resolveCurrentTeam();

        if (! $team) {
            abort(403, 'No active team selected.');
        }

        $this->validate([
            'triggerEvent' => 'required|string|max:150',
            // "nullable|json" would reject Livewire's default empty-string
            // binding (empty string isn't null, and isn't valid JSON
            // either) -- validate the shape only when non-blank.
            'conditionsJson' => [Rule::when($this->conditionsJson !== '', ['json'])],
            // Rule::exists() queries the table directly and does not go
            // through NotifyTemplate/NotifyChannel's HasTeamScope global
            // scope (that's an Eloquent-query-builder feature, exists:
            // isn't one) -- a bare "exists:notify_templates,id" would let
            // a hand-crafted request reference another team's template or
            // channel id. ->where('team_id', ...) closes that gap
            // explicitly rather than relying on the model layer here.
            'templateId' => ['required', 'integer', Rule::exists('notify_templates', 'id')->where('team_id', $team->id)],
            'channelId' => ['required', 'integer', Rule::exists('notify_channels', 'id')->where('team_id', $team->id)],
            'rateCeilingPerHour' => [Rule::when($this->rateCeilingPerHour !== '', ['integer', 'min:1'])],
        ]);

        $conditions = $this->conditionsJson !== '' ? json_decode($this->conditionsJson, true) : [];

        // Structural governance rule (wiki.md §5): no notification class
        // may trigger on recipient absence. Livewire's own validate()
        // only ever validates component properties by name (no raw-data
        // override), and NoAbsenceTriggerConditions needs the *decoded*
        // conditions array to pattern-match keys against, not the raw
        // JSON string -- so this runs as a second, explicit pass via
        // Laravel's Validator facade directly.
        $absenceValidator = Validator::make(
            ['conditions' => $conditions],
            ['conditions' => [new NoAbsenceTriggerConditions]],
        );

        if ($absenceValidator->fails()) {
            $this->addError('conditionsJson', $absenceValidator->errors()->first('conditions'));

            return;
        }

        $attributes = [
            'team_id' => $team->id,
            'trigger_event' => $this->triggerEvent,
            'conditions' => $conditions,
            'template_id' => (int) $this->templateId,
            'channel_id' => (int) $this->channelId,
            'rate_ceiling_per_hour' => $this->rateCeilingPerHour !== '' ? (int) $this->rateCeilingPerHour : null,
            'is_active' => $this->isActive,
        ];

        if ($this->editingId) {
            NotifyRule::findOrFail($this->editingId)->update($attributes);
        } else {
            NotifyRule::create($attributes);
        }

        $this->cancelEdit();
        unset($this->rules);
    }

    public function toggleRule(int $id): void
    {
        $rule = NotifyRule::findOrFail($id);
        $rule->update(['is_active' => ! $rule->is_active]);
        unset($this->rules);
    }

    public function deleteRule(int $id): void
    {
        NotifyRule::findOrFail($id)->delete();
        unset($this->rules);
    }

    public function render()
    {
        return view('livewire.notify.rule-manager');
    }
}
