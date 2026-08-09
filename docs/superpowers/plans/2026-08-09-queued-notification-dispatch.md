# Queued Notification Dispatch Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dispatch every `queued` `NotifyLog` to its channel's real driver — this platform's own
named Level 1 gap — with genuine delivery logic per channel type and an honest, specific
`failure_reason` wherever real delivery isn't possible in this environment today.

**Architecture:** One service, `App\Services\NotifyDispatcher`, with one public `dispatch()` method
branching on the log's channel type. A new scheduled command runs it every minute against every
`queued` log. No new tables — every field this touches already exists on `NotifyLog`.

**Tech Stack:** Laravel (per repo's existing conventions), Laravel's `Mail`/`Http`/`Notification`
facades, PHPUnit.

## Global Constraints

- Every failure path sets a specific, honest `failure_reason` — never generic, never silent, never
  a fabricated success. Exact strings (spec §1): `"No channel configured for this notification."`,
  `"SMS delivery has no configured provider."`, `"Push delivery has no configured provider."`,
  `"Unknown channel type."`, `"No matching user found for in-app delivery."`, `"Webhook channel has
  no configured URL."`, `"Slack channel has no configured webhook URL."`.
- `in_app` delivery must match the recipient to a `User` who belongs to the *same team* as the
  channel — never a cross-team match.
- `webhook`/`slack` never attempt an HTTP call when their config is missing — the missing-config
  check happens before any `Http::post()`.
- `sms`/`push` never attempt real delivery under any config — deterministic failure regardless of
  `channel.config` contents, since no provider SDK exists anywhere in this codebase.
- The scheduled command runs `->everyMinute()`, not daily — spec §3.
- No new migrations — `NotifyLog.status`/`.failure_reason`/`.sent_at` already exist.

---

## Task 1: `NotifyDispatcher` service — skeleton, email, sms/push, missing-channel/unknown-type

**Files:**
- Create: `app/Services/NotifyDispatcher.php`
- Test: `tests/Feature/Notify/NotifyDispatcherEmailTest.php`

**Interfaces:**
- Consumes: `App\Models\NotifyLog` (existing, unmodified — `channel()` relation, `recipient`,
  `subject`, `body_preview`, `status`, `failure_reason`, `sent_at` fields).
- Produces: `App\Services\NotifyDispatcher::dispatch(NotifyLog $log): void`. Task 2 and Task 3 add
  further `match` arms to this same method's body (both modify this file, not create a new one).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\Notify;

use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\User;
use App\Services\NotifyDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotifyDispatcherEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_queued_email_log_is_sent_and_marked_sent(): void
    {
        Mail::fake();

        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'email',
            'name' => 'Primary Email',
        ]);

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => 'someone@example.com',
            'subject' => 'Test subject',
            'body_preview' => 'Test body.',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        Mail::assertSent(function ($mailable) {
            return true;
        });

        $log->refresh();
        $this->assertSame('sent', $log->status);
        $this->assertNotNull($log->sent_at);
    }

    public function test_a_log_with_no_channel_fails_cleanly(): void
    {
        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => null,
            'recipient' => 'someone@example.com',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame('No channel configured for this notification.', $log->failure_reason);
    }

    public function test_an_sms_log_fails_with_no_configured_provider(): void
    {
        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'sms',
            'name' => 'SMS Channel',
        ]);

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => '+15555550100',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame('SMS delivery has no configured provider.', $log->failure_reason);
    }

    public function test_a_push_log_fails_with_no_configured_provider(): void
    {
        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'push',
            'name' => 'Push Channel',
        ]);

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => 'device-token-abc',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame('Push delivery has no configured provider.', $log->failure_reason);
    }

    public function test_an_unknown_channel_type_fails_cleanly(): void
    {
        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'carrier_pigeon',
            'name' => 'Unsupported Channel',
        ]);

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => 'someone@example.com',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame('Unknown channel type.', $log->failure_reason);
    }

    public function test_a_mail_exception_is_caught_and_recorded_as_a_failure(): void
    {
        Mail::shouldReceive('raw')->andThrow(new \RuntimeException('Connection refused'));

        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'email',
            'name' => 'Primary Email',
        ]);

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => 'someone@example.com',
            'subject' => 'Test subject',
            'body_preview' => 'Test body.',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame('Connection refused', $log->failure_reason);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Notify/NotifyDispatcherEmailTest.php`
Expected: FAIL — class `App\Services\NotifyDispatcher` not found.

- [ ] **Step 3: Write the service**

```php
<?php

namespace App\Services;

use App\Models\NotifyLog;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Dispatches a queued NotifyLog to its channel's real driver — this
 * platform's own named Level 1 gap (Dot.Brain audit, dot-notify.md,
 * 2026-08-08). See
 * docs/superpowers/specs/2026-08-09-queued-notification-dispatch-design.md.
 *
 * Every failure path sets a specific, honest failure_reason — never a
 * generic message, never a fabricated success.
 */
class NotifyDispatcher
{
    public function dispatch(NotifyLog $log): void
    {
        $channel = $log->channel;

        if (! $channel) {
            $this->fail($log, 'No channel configured for this notification.');

            return;
        }

        match ($channel->type) {
            'email' => $this->dispatchEmail($log),
            'sms' => $this->fail($log, 'SMS delivery has no configured provider.'),
            'push' => $this->fail($log, 'Push delivery has no configured provider.'),
            default => $this->fail($log, 'Unknown channel type.'),
        };
    }

    private function dispatchEmail(NotifyLog $log): void
    {
        try {
            Mail::raw($log->body_preview ?? '', function ($message) use ($log) {
                $message->to($log->recipient)->subject($log->subject ?? 'Notification');
            });

            $this->succeed($log);
        } catch (Throwable $e) {
            $this->fail($log, $e->getMessage());
        }
    }

    private function succeed(NotifyLog $log): void
    {
        $log->update(['status' => 'sent', 'sent_at' => now()]);
    }

    private function fail(NotifyLog $log, string $reason): void
    {
        $log->update(['status' => 'failed', 'failure_reason' => $reason]);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Notify/NotifyDispatcherEmailTest.php`
Expected: 6 passed.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/NotifyDispatcher.php tests/Feature/Notify/NotifyDispatcherEmailTest.php
git commit -m "feat(notify): add NotifyDispatcher — email, sms/push honest failures, unknown-type handling"
```

---

## Task 2: `in_app` channel + `QueuedNotificationDelivered` notification

**Files:**
- Create: `app/Notifications/QueuedNotificationDelivered.php`
- Modify: `app/Services/NotifyDispatcher.php`
- Test: `tests/Feature/Notify/NotifyDispatcherInAppTest.php`

**Interfaces:**
- Consumes: `App\Models\User::belongsToTeam(Team $team): bool` (Jetstream-provided, existing).
- Produces: `App\Notifications\QueuedNotificationDelivered::__construct(NotifyLog $notifyLog)`,
  `via()` returns `['database']`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\Notify;

use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\Team;
use App\Models\User;
use App\Services\NotifyDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotifyDispatcherInAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_queued_in_app_log_notifies_the_matching_team_member(): void
    {
        $recipientUser = User::factory()->withPersonalTeam()->create(['email' => 'member@example.com']);
        $team = $recipientUser->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'in_app',
            'name' => 'In-App',
        ]);

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => 'member@example.com',
            'subject' => 'Test subject',
            'body_preview' => 'Test body.',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        $log->refresh();
        $this->assertSame('sent', $log->status);
        $this->assertSame(1, $recipientUser->fresh()->unreadNotifications()->count());
    }

    public function test_an_in_app_log_with_no_matching_user_fails_cleanly(): void
    {
        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'in_app',
            'name' => 'In-App',
        ]);

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => 'nobody@example.com',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame('No matching user found for in-app delivery.', $log->failure_reason);
    }

    public function test_an_in_app_log_does_not_match_a_user_from_a_different_team(): void
    {
        $outsider = User::factory()->withPersonalTeam()->create(['email' => 'outsider@example.com']);
        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'in_app',
            'name' => 'In-App',
        ]);

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => 'outsider@example.com',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame(0, $outsider->fresh()->unreadNotifications()->count());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Notify/NotifyDispatcherInAppTest.php`
Expected: FAIL — `in_app` type falls through to `default` and fails with "Unknown channel type."
instead of the expected behavior.

- [ ] **Step 3: Write the notification**

```php
<?php

namespace App\Notifications;

use App\Models\NotifyLog;
use Illuminate\Notifications\Notification;

/**
 * In-app (database channel) delivery of a queued NotifyLog whose channel
 * type is "in_app" — see App\Services\NotifyDispatcher.
 */
class QueuedNotificationDelivered extends Notification
{
    public function __construct(public NotifyLog $notifyLog) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'notification_delivered',
            'title' => $this->notifyLog->subject ?? 'Notification',
            'message' => $this->notifyLog->body_preview ?? '',
            'notify_log_id' => $this->notifyLog->id,
            'url' => route('dashboard'),
        ];
    }
}
```

- [ ] **Step 4: Add the `in_app` branch to `NotifyDispatcher`**

Read `app/Services/NotifyDispatcher.php` first (from Task 1). Add this import:

```php
use App\Models\NotifyChannel;
use App\Models\User;
use App\Notifications\QueuedNotificationDelivered;
```

Replace the `match` block inside `dispatch()`:

```php
        match ($channel->type) {
            'email' => $this->dispatchEmail($log),
            'in_app' => $this->dispatchInApp($log, $channel),
            'sms' => $this->fail($log, 'SMS delivery has no configured provider.'),
            'push' => $this->fail($log, 'Push delivery has no configured provider.'),
            default => $this->fail($log, 'Unknown channel type.'),
        };
```

Add this method, after `dispatchEmail()`:

```php
    private function dispatchInApp(NotifyLog $log, NotifyChannel $channel): void
    {
        $team = $channel->team;
        $user = $team ? User::where('email', $log->recipient)->first() : null;

        if (! $user || ! $user->belongsToTeam($team)) {
            $this->fail($log, 'No matching user found for in-app delivery.');

            return;
        }

        $user->notify(new QueuedNotificationDelivered($log));

        $this->succeed($log);
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Notify/NotifyDispatcherInAppTest.php`
Expected: 3 passed.

- [ ] **Step 6: Re-run Task 1's suite to confirm no regression**

Run: `php artisan test --compact tests/Feature/Notify/NotifyDispatcherEmailTest.php`
Expected: 6 still passing.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Notifications/QueuedNotificationDelivered.php app/Services/NotifyDispatcher.php tests/Feature/Notify/NotifyDispatcherInAppTest.php
git commit -m "feat(notify): add in_app channel delivery via QueuedNotificationDelivered"
```

---

## Task 3: `webhook` + `slack` channels

**Files:**
- Modify: `app/Services/NotifyDispatcher.php`
- Test: `tests/Feature/Notify/NotifyDispatcherWebhookTest.php`

**Interfaces:**
- Consumes: `Illuminate\Support\Facades\Http` (framework-provided).
- Produces: no new public interface — `dispatch()`'s behavior for `webhook`/`slack` types.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\Notify;

use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\User;
use App\Services\NotifyDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotifyDispatcherWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_configured_webhook_channel_posts_and_is_marked_sent(): void
    {
        Http::fake(['https://example.com/hook' => Http::response(['ok' => true], 200)]);

        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'webhook',
            'name' => 'Webhook',
            'config' => ['url' => 'https://example.com/hook'],
        ]);

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => 'https://example.com/hook',
            'subject' => 'Test subject',
            'body_preview' => 'Test body.',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        Http::assertSent(fn ($request) => $request->url() === 'https://example.com/hook');

        $log->refresh();
        $this->assertSame('sent', $log->status);
    }

    public function test_an_unconfigured_webhook_channel_fails_without_attempting_a_request(): void
    {
        Http::fake();

        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'webhook',
            'name' => 'Webhook',
        ]);

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => 'n/a',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        Http::assertNothingSent();

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame('Webhook channel has no configured URL.', $log->failure_reason);
    }

    public function test_a_failing_webhook_response_is_recorded_with_the_status_code(): void
    {
        Http::fake(['https://example.com/hook' => Http::response('', 500)]);

        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'webhook',
            'name' => 'Webhook',
            'config' => ['url' => 'https://example.com/hook'],
        ]);

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => 'https://example.com/hook',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame('Webhook returned HTTP 500.', $log->failure_reason);
    }

    public function test_a_configured_slack_channel_posts_and_is_marked_sent(): void
    {
        Http::fake(['https://hooks.slack.example/abc' => Http::response(['ok' => true], 200)]);

        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'slack',
            'name' => 'Slack',
            'config' => ['webhook_url' => 'https://hooks.slack.example/abc'],
        ]);

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => '#general',
            'subject' => 'Alert',
            'body_preview' => 'Something happened.',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        Http::assertSent(fn ($request) => $request->url() === 'https://hooks.slack.example/abc'
            && $request['text'] === 'Alert: Something happened.');

        $log->refresh();
        $this->assertSame('sent', $log->status);
    }

    public function test_an_unconfigured_slack_channel_fails_without_attempting_a_request(): void
    {
        Http::fake();

        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'slack',
            'name' => 'Slack',
        ]);

        $log = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => '#general',
            'status' => 'queued',
        ]);

        app(NotifyDispatcher::class)->dispatch($log);

        Http::assertNothingSent();

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame('Slack channel has no configured webhook URL.', $log->failure_reason);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Notify/NotifyDispatcherWebhookTest.php`
Expected: FAIL — `webhook`/`slack` types fall through to `default` and fail with "Unknown channel
type."

- [ ] **Step 3: Add the `webhook`/`slack` branches to `NotifyDispatcher`**

Read `app/Services/NotifyDispatcher.php` first (current state from Task 2). Add this import:

```php
use Illuminate\Support\Facades\Http;
```

Replace the `match` block inside `dispatch()`:

```php
        match ($channel->type) {
            'email' => $this->dispatchEmail($log),
            'in_app' => $this->dispatchInApp($log, $channel),
            'webhook' => $this->dispatchWebhook($log, $channel),
            'slack' => $this->dispatchSlack($log, $channel),
            'sms' => $this->fail($log, 'SMS delivery has no configured provider.'),
            'push' => $this->fail($log, 'Push delivery has no configured provider.'),
            default => $this->fail($log, 'Unknown channel type.'),
        };
```

Add these two methods, after `dispatchInApp()`:

```php
    private function dispatchWebhook(NotifyLog $log, NotifyChannel $channel): void
    {
        $url = $channel->config['url'] ?? null;

        if (! $url) {
            $this->fail($log, 'Webhook channel has no configured URL.');

            return;
        }

        try {
            $response = Http::post($url, [
                'recipient' => $log->recipient,
                'subject' => $log->subject,
                'body' => $log->body_preview,
            ]);

            if ($response->successful()) {
                $this->succeed($log);
            } else {
                $this->fail($log, "Webhook returned HTTP {$response->status()}.");
            }
        } catch (Throwable $e) {
            $this->fail($log, $e->getMessage());
        }
    }

    private function dispatchSlack(NotifyLog $log, NotifyChannel $channel): void
    {
        $url = $channel->config['webhook_url'] ?? null;

        if (! $url) {
            $this->fail($log, 'Slack channel has no configured webhook URL.');

            return;
        }

        $text = $log->subject ? "{$log->subject}: {$log->body_preview}" : ($log->body_preview ?? '');

        try {
            $response = Http::post($url, ['text' => $text]);

            if ($response->successful()) {
                $this->succeed($log);
            } else {
                $this->fail($log, "Slack webhook returned HTTP {$response->status()}.");
            }
        } catch (Throwable $e) {
            $this->fail($log, $e->getMessage());
        }
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Notify/NotifyDispatcherWebhookTest.php`
Expected: 5 passed.

- [ ] **Step 5: Re-run Tasks 1-2's suites to confirm no regression**

Run: `php artisan test --compact tests/Feature/Notify/NotifyDispatcherEmailTest.php tests/Feature/Notify/NotifyDispatcherInAppTest.php`
Expected: 9 still passing.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/NotifyDispatcher.php tests/Feature/Notify/NotifyDispatcherWebhookTest.php
git commit -m "feat(notify): add webhook and slack channel delivery"
```

---

## Task 4: `notify:dispatch-queued` command + schedule

**Files:**
- Create: `app/Console/Commands/DispatchQueuedNotifications.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Notify/DispatchQueuedNotificationsTest.php`

**Interfaces:**
- Consumes: `App\Services\NotifyDispatcher::dispatch()` (Tasks 1-3, now complete for all 6 channel
  types).
- Produces: console command `notify:dispatch-queued`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\Notify;

use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DispatchQueuedNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_queued_logs_are_dispatched(): void
    {
        Mail::fake();

        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $channel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'email',
            'name' => 'Primary Email',
        ]);

        $queuedLog = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => 'someone@example.com',
            'subject' => 'Queued',
            'body_preview' => 'Body.',
            'status' => 'queued',
        ]);

        $alreadySentLog = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'recipient' => 'someone-else@example.com',
            'subject' => 'Already sent',
            'body_preview' => 'Body.',
            'status' => 'sent',
            'sent_at' => now()->subHour(),
        ]);

        $this->artisan('notify:dispatch-queued')->assertExitCode(0);

        $this->assertSame('sent', $queuedLog->fresh()->status);
        // Untouched — its sent_at must not move.
        $this->assertEquals($alreadySentLog->sent_at->timestamp, $alreadySentLog->fresh()->sent_at->timestamp);
    }

    public function test_multiple_queued_logs_across_channel_types_are_each_dispatched_independently(): void
    {
        Mail::fake();

        $team = User::factory()->withPersonalTeam()->create()->currentTeam;

        $emailChannel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'email',
            'name' => 'Primary Email',
        ]);

        $smsChannel = NotifyChannel::create([
            'team_id' => $team->id,
            'type' => 'sms',
            'name' => 'SMS',
        ]);

        $emailLog = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $emailChannel->id,
            'recipient' => 'someone@example.com',
            'subject' => 'Queued',
            'body_preview' => 'Body.',
            'status' => 'queued',
        ]);

        $smsLog = NotifyLog::create([
            'team_id' => $team->id,
            'channel_id' => $smsChannel->id,
            'recipient' => '+15555550100',
            'status' => 'queued',
        ]);

        $this->artisan('notify:dispatch-queued');

        $this->assertSame('sent', $emailLog->fresh()->status);
        $this->assertSame('failed', $smsLog->fresh()->status);
        $this->assertSame('SMS delivery has no configured provider.', $smsLog->fresh()->failure_reason);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Notify/DispatchQueuedNotificationsTest.php`
Expected: FAIL — command `notify:dispatch-queued` doesn't exist.

- [ ] **Step 3: Write the command**

```php
<?php

namespace App\Console\Commands;

use App\Models\NotifyLog;
use App\Services\NotifyDispatcher;
use Illuminate\Console\Command;

/**
 * This platform's first scheduled job (Dot.Brain audit's own named Level
 * 1 gap, dot-notify.md). Dispatches every queued NotifyLog to its real
 * channel driver via App\Services\NotifyDispatcher — see
 * docs/superpowers/specs/2026-08-09-queued-notification-dispatch-design.md.
 *
 * No Level 2 gate: the human approval already happened when the team
 * configured the NotifyRule and activated the NotifyChannel that produced
 * this queued log.
 */
class DispatchQueuedNotifications extends Command
{
    protected $signature = 'notify:dispatch-queued';

    protected $description = 'Dispatch every queued NotifyLog to its channel driver.';

    public function handle(NotifyDispatcher $dispatcher): int
    {
        NotifyLog::where('status', 'queued')->get()->each(
            fn (NotifyLog $log) => $dispatcher->dispatch($log)
        );

        $this->info('Queued notification dispatch complete.');

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Schedule the command**

Read `routes/console.php` first, then replace its full contents:

```php
<?php

use App\Console\Commands\DispatchQueuedNotifications;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─── Scheduled Platform Jobs ──────────────────────────────────────────────────
// This platform's first scheduled process — see
// docs/superpowers/specs/2026-08-09-queued-notification-dispatch-design.md.
// Every minute, not daily: a notification platform needs timely delivery,
// not a periodic health check.
Schedule::command(DispatchQueuedNotifications::class)
    ->everyMinute()
    ->withoutOverlapping();
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Notify/DispatchQueuedNotificationsTest.php`
Expected: 2 passed.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/DispatchQueuedNotifications.php routes/console.php tests/Feature/Notify/DispatchQueuedNotificationsTest.php
git commit -m "feat(notify): add notify:dispatch-queued — this platform's first scheduled job"
```

---

## Task 5: Full regression

**Files:** none (verification only).

- [ ] **Step 1: Run the full test suite**

```bash
php artisan test --compact
```

Expected: all tests pass, including every pre-existing suite (`tests/Feature/Notify/NotifyTest.php`,
`NotifySelfNotificationTest.php`, `NotifyDashboardTest.php`, `NotifyLogSearchTest.php`,
`tests/Feature/Notify/WebhookInboundTest.php`, `tests/Feature/NotifyTeamScopeTest.php`) alongside
all four new suites from this plan.

- [ ] **Step 2: Run Pint across the whole diff one final time**

```bash
vendor/bin/pint --dirty --format agent
```

If it reformats anything, `git add -A` and amend or add a small formatting commit.

- [ ] **Step 3: Report**

Summarize the final test count and confirm this plan's scope (Tasks 1-4) is fully implemented
against `docs/superpowers/specs/2026-08-09-queued-notification-dispatch-design.md`. No commits in
this task beyond an optional Pint formatting fix — this is verification-only.
