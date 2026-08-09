# Queued Notification Dispatch — Design Spec

**Status:** Approved by user, ready for implementation planning.
**Platform:** Dot.Notify
**Date:** 2026-08-09

## Context

`Dot.Brain/platforms/dot-notify.md`'s Autonomy Classification audit (§Level 1, §Level 2,
2026-08-08) confirmed zero automation in this codebase — no `app/Console/Commands`, no
`Schedule::` entries, no `app/Jobs`. It names its own suggested Level 1 candidate precisely: "a
queue worker or scheduled job that actually dispatches a `queued` `NotifyLog` to its channel
driver (email/SMS/push/webhook/Slack/in-app) end-to-end without a human click."

Verified directly against the real code before proceeding:

- `app/Http/Controllers/WebhookInboundController.php` is a fully real, working pipeline: it
  verifies an inbound webhook's HMAC signature, resolves a `NotifyRule` for the trigger event,
  renders the matched `NotifyTemplate`, and creates a `NotifyLog` — but only ever in `queued`
  status. The controller's own comment states dispatching to a channel driver "is the pre-existing
  send pipeline gap noted in wiki.md, not something introduced here."
- No Level 2 candidate exists for this gap — the audit checked `AiNotifyService`/`TemplateEditor`
  and correctly classified AI template drafting as Level 3 (synchronous, human-operated tooling
  inside one request), not a system-prepared proposal.
- `NotifyChannel::booted()` already wires a real, working notification
  (`ChannelDegradedNotification`, fired on a channel's `test_status` transitioning to `failed`) —
  from an earlier platform-loop pass. That gap is already closed; this spec does not touch it.

A follow-up constraint surfaced during exploration: `App\Livewire\Notify\ChannelManager::addChannel()`
never sets `NotifyChannel.config` — every channel created through the real UI has empty config,
regardless of type. So a `webhook`/`slack` channel has nowhere configured to send to today, and
no SMS/push provider SDK is installed anywhere in this codebase (`composer.json` has no Twilio/FCM
dependency). Presented to the user as an explicit choice — build honest per-channel-type dispatch
logic for all six types (webhook/Slack/SMS/push failing cleanly, with a specific reason, wherever
they're unconfigured today) or narrow the build to only the two channel types that can genuinely
succeed in this environment (email, in-app). **The user chose the full six-type build**, matching
the precedent already set for Dot.Memory: build real logic per capability, and be honest — never
fabricate — about what has no real backing yet.

## Goal

Implement the platform's own named gap exactly: a scheduled job that dispatches every `queued`
`NotifyLog` to its channel's real driver, with genuine delivery logic for every channel type this
platform defines, and a specific, honest `failure_reason` wherever real delivery isn't possible in
this environment today.

## 1. `NotifyDispatcher` service

New `App\Services\NotifyDispatcher`, one method, one class — matching the "single class, no
premature driver-registry abstraction" precedent already set by Dot.Memory's
`RetrievalSlaEvaluator`.

```php
public function dispatch(NotifyLog $log): void
```

Branches on `$log->channel?->type`:

| Type | Behavior |
|---|---|
| `email` | `Mail::raw($log->body_preview, ...)` to `$log->recipient`, subject `$log->subject`. Genuinely deliverable via whatever mail driver is configured — the same mechanism this ecosystem already uses for real transactional email (team invitations, password resets) elsewhere. |
| `in_app` | Resolves `$log->recipient` as an email against a `User` who belongs to the channel's own team (`$user->belongsToTeam($channel->team)` — never a cross-team match). If found, sends a new `App\Notifications\QueuedNotificationDelivered` database notification. No match → clean failure. |
| `webhook` | `Http::post($channel->config['url'] ?? null, [...])`. Missing/empty URL → clean failure, `"Webhook channel has no configured URL."` A non-2xx response → clean failure citing the status code. |
| `slack` | Same HTTP shape, `$channel->config['webhook_url'] ?? null`, payload `['text' => ...]` (Slack's own incoming-webhook format). Missing URL → clean failure, `"Slack channel has no configured webhook URL."` |
| `sms` | Deterministic clean failure, `"SMS delivery has no configured provider."` — no attempt, no fabrication; no SMS SDK exists in this codebase. |
| `push` | Deterministic clean failure, `"Push delivery has no configured provider."` — same reasoning. |
| anything else (missing channel, unknown type) | Clean failure, `"No channel configured for this notification."` / `"Unknown channel type."` |

On success: `$log->update(['status' => 'sent', 'sent_at' => now()])`.
On any failure path: `$log->update(['status' => 'failed', 'failure_reason' => <specific reason>])`
— every failure path sets a distinct, honest reason string; none is generic or silent.

`Mail::raw()`/`Http::post()` calls are wrapped so a thrown exception (network error, invalid mail
config) also lands in the `failed` path with the exception's message as `failure_reason`, rather
than crashing the whole scan for every other queued log.

## 2. `QueuedNotificationDelivered` notification

New `App\Notifications\QueuedNotificationDelivered`, matching this repo's own existing
`ChannelDegradedNotification` shape exactly (`via()` returns `['database']` only,
`toDatabase()` returns a `type`/`title`/`message`/`url` payload) — rendered by the existing
`NotificationBell` component with no changes needed there.

## 3. `notify:dispatch-queued` command

This platform's first scheduled job. Loads every `NotifyLog` where `status = 'queued'` and calls
`NotifyDispatcher::dispatch()` on each.

Scheduled `->everyMinute()->withoutOverlapping()` — deliberately not the daily cadence used for
every periodic health-check-style job built this program (Design's drift scan, Memory's SLA scan).
A notification platform needs timely delivery, not a periodic report; email/webhook/in-app
recipients expect their notification to arrive promptly, not once a day.

## No Level 2 gate

The audit names no Level 2 candidate for this platform, and none exists: dispatching an
already-`queued` `NotifyLog` is not a system preparing a proposal for a human to approve — the
human approval already happened when the team configured the `NotifyRule`, activated the
`NotifyChannel`, and (for the inbound-webhook path) registered and enabled the `NotifyWebhook`
that produced the log in the first place. Requiring a second human click before every individual
dispatch would defeat the platform's entire purpose, matching the audit's own framing of this gap
as purely a missing "queue worker or scheduled job," not a missing approval step.

## Out of Scope

- Extending `ChannelManager`'s form to accept driver-specific config (SMTP overrides, webhook
  URLs, Slack webhook URLs, SMS/push provider credentials) — a real, separate gap (channel
  configuration management), not the dispatch-pipeline gap this spec closes. Noted honestly rather
  than silently worked around.
- Integrating a real SMS or push provider (Twilio, FCM/APNs, or similar) — a genuinely new external
  dependency this program does not add without explicit direction.
- `NotifyBatch` (bulk-send) — no code path anywhere in this codebase currently creates `NotifyLog`
  rows from a batch; this command only ever acts on whatever is `queued`, regardless of how it got
  there, matching every other scan job built this program.
- Retrying a `failed` log automatically — a failed dispatch stays `failed`; no retry/backoff logic
  is added. Matches this program's "append-only, no silent re-attempt" precedent for hand-recorded
  and system-derived state elsewhere.
- CI/CD (the `.github/workflows/` gap the same audit paragraph also names) — infrastructure/ops
  tooling, not application code this spec builds.

## Testing Notes

- `email`: a `queued` log with an `email`-type channel is sent via `Mail::raw()` (assert with
  `Mail::fake()`/`Mail::assertSent()`), ends `sent` with `sent_at` set.
- `in_app`: a recipient matching a real team member's email produces a `QueuedNotificationDelivered`
  database notification and `sent` status; a recipient with no matching user, or matching a user in
  a *different* team, ends `failed` with the specific reason.
- `webhook`/`slack`: a channel with `config['url']`/`config['webhook_url']` set results in an HTTP
  POST (assert with `Http::fake()`/`Http::assertSent()`) and `sent`; a channel with empty/missing
  config ends `failed` with the specific "no configured URL" reason, and no HTTP call is attempted
  (`Http::assertNothingSent()`).
- `sms`/`push`: always end `failed` with their specific "no configured provider" reason,
  deterministically, regardless of `config` contents.
- A `NotifyLog` with no `channel_id` (or a soft-deleted/missing channel) fails cleanly rather than
  throwing.
- `notify:dispatch-queued`: only `queued` logs are touched — a `sent`/`failed`/`delivered` log is
  left untouched by a second run; multiple queued logs across different channel types in one scan
  are each dispatched independently (one channel's failure doesn't block another's success).
