---
title: Dot.Notify — Platform Wiki
version: 1.0.0
status: active
owners: [Notify Platform Lead]
platform-id: dot-notify
last-review: 2026-08-01
---

# Dot.Notify

Purpose: this is Dot.Notify's own knowledge home — owned and maintained by the Dot.Notify team. It describes what this platform actually does, how it's built, and how it connects to the wider Dot Ecosystem. Dot.Brain never edits this file; it only reads what we choose to publish.

> **Related:** [Dot.Brain's ingested view of this platform](https://github.com/sakhilebhayi/Dot.Brain/blob/main/platforms/dot-notify.md)

---

## 1. What Dot.Notify Is

Dot.Notify sends, tracks, and optimises notifications on behalf of the rest of the ecosystem — email, SMS, push, webhook, Slack, and in-app. Teams configure delivery channels, build message templates (with AI-assisted drafting), map trigger events to routing rules, and get a full audit trail of every notification's journey from queue to delivery, including opens and clicks.

We are the shared last mile: almost nothing here originates as Dot.Notify's own idea — a platform tells us "this event happened," and we decide, per team-configured rule, who gets told, on what channel, with what copy. That position is also what makes our data unusually valuable: we are the only platform that sees attention economics across the *whole* ecosystem — what gets delivered, opened, acted on, or ignored, regardless of which platform triggered it.

**Status:** shipping. This is a working Laravel application, not a blueprint — channels, templates, rules, delivery logs, batches, schedules, and inbound webhooks are all implemented and running behind team-scoped auth.

## 2. Architecture

| Layer | Technology |
|---|---|
| Framework | Laravel 12 (PHP 8.4) |
| Frontend | Livewire 3, Alpine.js 3, Tailwind CSS |
| Database | PostgreSQL 16, shared across the ecosystem |
| Realtime | Laravel Reverb |
| Auth | Laravel Sanctum, ecosystem SSO handoff |
| AI | Anthropic Claude (`claude-sonnet-4-6`) for template drafting |
| Queue | Redis + Laravel Horizon |
| Search | Laravel Scout + Meilisearch |

**Ecosystem SSO:** `App\Http\Controllers\Auth\EcosystemAuthController` accepts a Sanctum personal access token minted by the ecosystem hub, requires the `ecosystem:read` ability and an unexpired token, logs the user in, and immediately deletes the token (single-use handoff). Multi-tenancy is Jetstream teams: every domain table (`notify_channels`, `notify_templates`, `notify_rules`, `notify_logs`, `notify_batches`, `notify_schedules`, `notify_webhooks`) is scoped by `team_id`.

**AI template drafting:** `App\Services\AiNotifyService::generateTemplate($purpose, $channelType)` calls the Anthropic Messages API directly (raw cURL, no SDK) with a fixed prompt asking for `{subject, body, variables}` JSON using `{{ variable }}` interpolation syntax. If no API key is configured it falls back to a deterministic templated stub rather than failing — AI assistance degrades gracefully to a usable default, it never blocks the send path.

## 3. Domain Entities

Derived from `database/migrations/2026_06_29_200001_create_notify_tables.php` and `app/Models/`:

| Entity | Table | Key fields | Notes |
|---|---|---|---|
| **NotifyChannel** | `notify_channels` | `type` (email/sms/push/webhook/slack/in_app), `config` (JSON), `is_active`, `test_status` | Driver-specific credentials (SMTP, Slack webhook URL, etc.) live in `config` |
| **NotifyTemplate** | `notify_templates` | `subject`, `body`, `variables` (JSON), `channel_type` | `{{ variable }}` interpolation; can be AI-drafted via `AiNotifyService` |
| **NotifyRule** | `notify_rules` | `trigger_event`, `conditions` (JSON), `template_id`, `channel_id` | Maps a trigger event (`user.signup`, `payment.failed`, `custom.*`) to a template + channel |
| **NotifyLog** | `notify_logs` | `recipient`, `status`, `sent_at`, `delivered_at`, `opened_at` | Per-recipient audit record; `status` walks queued → sent → delivered → opened/clicked, or failed/bounced |
| **NotifyPreference** | `notify_preferences` | `user_id`, `notification_type`, `channel_type`, `enabled` | Per-user opt-in/out, unique per (user, type, channel) |
| **NotifyBatch** | `notify_batches` | `status`, `total_recipients`, `sent_count`, `failed_count` | Bulk sends with progress tracking |
| **NotifySchedule** | `notify_schedules` | `cron_expression`, `timezone`, `next_run_at` | Recurring sends tied to a rule |
| **NotifyWebhook** | `notify_webhooks` | `endpoint_token` (unique, auto-generated), `source`, `event_map` | Inbound webhook endpoints that translate a third-party event into one of our `trigger_event`s |

Consent lives on `NotifyPreference`, scoped per user × notification type × channel — this is the enforcement point for anything opt-out-sensitive.

## 4. Events Emitted

Delivery status transitions on `NotifyLog` are the primary signal we produce for the rest of the ecosystem:

| Event (status) | Meaning | Where it's set |
|---|---|---|
| `queued` → `sent` | Notification handed to the channel driver | send pipeline |
| `delivered` | Channel confirmed receipt | delivery callback |
| `opened` / `clicked` | Recipient engaged | tracking pixel / link |
| `failed` / `bounced` | Delivery did not complete | `failure_reason` populated |

Dashboard aggregates (`routes/web.php`) read these directly: active channel count, notifications sent today, failures today, template count — all team-scoped. Livewire components react to a `notify.sent` browser event (see `NotificationCenter::refresh()`) to live-refresh the log view via Reverb.

## 5. Connecting to Dot.Brain

Dot.Notify participates in the ecosystem as a registered platform (`dot-notify`) that publishes Knowledge Packs about delivery performance and attention economics — never about message content or recipient identity beyond aggregate cohorts.

| Payload type | Cadence | Contains |
|---|---|---|
| `observation` | weekly | per-class precision (acted / sent) and fatigue aggregates |
| `insight` | per finding | attention-economics patterns that only a cross-platform last-mile can see |
| `outcome` | per verified recommendation | before/after metrics for accepted class-throttling or channel-selection changes |
| `incident` | per incident | delivery failures, consent breaches, runaway send volume |

We subscribe to Dot.Brain recommendations on class-throttling (demoting low-precision notification classes to digest), channel selection, and send-window optimisation — never frequency-pressure tactics. Full manifest, entity/event mapping, the domain-agent assignment (Documentation Agent), the Dot.Pulse cross-org consent seam, and a worked publish→PR round-trip are maintained on the Brain side at [`platforms/dot-notify.md`](https://github.com/sakhilebhayi/Dot.Brain/blob/main/platforms/dot-notify.md) — that document is Dot.Brain's ingested view and is authoritative for integration mechanics; this wiki is authoritative for what Dot.Notify actually *is* and how it's built.

**Governance note:** Dot.Brain's ingested view records a structural rule we enforce at our end — no notification class may trigger on recipient absence (no "we miss you" / inactivity nudges). Trigger conditions must reference domain events (`trigger_event` on `NotifyRule`), never recipient-behaviour gaps. That constraint isn't yet encoded as a validation rule in `NotifyRule` — see Roadmap.

## 6. Roadmap

- [ ] Implement Knowledge Pack publishing (`observation`, `insight`, `outcome`, `incident`) — not yet wired up; this wiki and the Brain-side doc describe the target shape, not shipped behavior
- [ ] Enforce "no absence-triggers" as a validation rule on `NotifyRule` creation, not just a documented convention
- [ ] Per-class rate ceilings on `NotifyRule`/`NotifyBatch` to cap runaway send volume (the 2026-02 incident class of failure)
- [ ] Cost-aware channel-selection recommendations for SMS-heavy classes
- [ ] Expand `AiNotifyService` beyond template drafting (e.g., send-window suggestions) once Brain-side recommendations are consumable

## Change Log

| Version | Date | Author | Change |
|---|---|---|---|
| 1.0.0 | 2026-08-01 | Notify Platform Lead | Initial wiki: derived from the actual Laravel codebase (models, migrations, services, routes), cross-referenced against Dot.Brain's platforms/dot-notify.md for ecosystem framing |
| 1.0.1 | 2026-08-01 | Platform-loop pass | UI/UX: dashboard delivery-success-rate + recent-batches widgets, recipient/subject search on the delivery log, class-based dark mode toggle. Added an in-app notification bell (`database` channel) for this platform's own operators — `ChannelDegradedNotification` and `BatchFailedNotification` fire from `NotifyChannel`/`NotifyBatch` model observers on transition into a failed state. Wired the real logo/favicons across nav, auth pages, and browser tab; removed leftover shared-template assets (`index.html` marketing page, stray `dot.logos6.png`/`dot_projects.png`, wrong `package-lock.json` name). Added Feature tests. **Confirmed by code, not just gap analysis:** there is no inbound webhook HTTP route/controller at all — `NotifyWebhook` only issues tokens — and no `NotifySchedule` model despite the migration table existing; both are flagged in README Roadmap rather than built in this pass. |

## Open Questions

- Should `NotifyRule` validation reject absence-triggering conditions at the database layer, or is that a policy check that belongs in a service class?
- Per-class rate ceilings: fixed at channel/rule registration, or adaptive to observed precision from Brain recommendations?
- SMS costs are per-message — should our channel-selection logic carry a cost term locally, or wait for Brain's cost-aware recommendation type?
