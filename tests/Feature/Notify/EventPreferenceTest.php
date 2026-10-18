<?php

namespace Tests\Feature\Notify;

use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\NotifyPreference;
use App\Models\Team;
use App\Models\User;
use App\Services\NotifyDispatcher;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Recipients can switch one event type off (e.g. task.due_soon) without losing
 * the rest. Security notices can never be suppressed.
 */
class EventPreferenceTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $recipient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = User::factory()->withPersonalTeam()->create()->currentTeam;
        $this->recipient = User::factory()->create(['email' => 'amara@example.test']);
    }

    private function log(?string $event, string $type = 'transactional', string $recipient = 'amara@example.test'): NotifyLog
    {
        $channel = NotifyChannel::firstOrCreate(['team_id' => $this->team->id, 'type' => 'email', 'name' => 'Primary Email']);

        return NotifyLog::create(['team_id' => $this->team->id, 'channel_id' => $channel->id, 'recipient' => $recipient, 'subject' => 'S', 'body_preview' => 'B', 'status' => 'queued', 'notification_type' => $type, 'event' => $event]);
    }

    public function test_opting_out_of_one_event_suppresses_that_event_only(): void
    {
        NotifyPreference::setForEvent($this->recipient, 'task.due_soon', 'email', false);

        $blocked = $this->log('task.due_soon');
        $other = $this->log('task.assigned');
        app(NotifyDispatcher::class)->dispatch($blocked);
        app(NotifyDispatcher::class)->dispatch($other);

        $this->assertSame('suppressed', $blocked->refresh()->status);
        $this->assertSame('Recipient opted out of task.due_soon notifications on the email channel.', $blocked->failure_reason);
        $this->assertSame('sent', $other->refresh()->status);
        $this->assertCount(1, $this->sentMessages());
    }

    public function test_an_event_opt_out_is_per_channel_type_and_per_person(): void
    {
        $someoneElse = User::factory()->create(['email' => 'bongani@example.test']);
        NotifyPreference::setForEvent($this->recipient, 'task.due_soon', 'sms', false);
        NotifyPreference::setForEvent($someoneElse, 'task.due_soon', 'email', false);

        $log = $this->log('task.due_soon');
        app(NotifyDispatcher::class)->dispatch($log);

        $this->assertSame('sent', $log->refresh()->status);
    }

    public function test_opting_back_in_lets_the_event_through_again(): void
    {
        NotifyPreference::setForEvent($this->recipient, 'task.due_soon', 'email', false);
        NotifyPreference::setForEvent($this->recipient, 'task.due_soon', 'email', true);

        $log = $this->log('task.due_soon');
        app(NotifyDispatcher::class)->dispatch($log);

        $this->assertSame('sent', $log->refresh()->status);
        $this->assertSame(1, NotifyPreference::where('user_id', $this->recipient->id)->count(), 'Toggling reuses the one row.');
    }

    public function test_a_category_wide_opt_out_still_suppresses_every_event_of_that_category(): void
    {
        NotifyPreference::create(['user_id' => $this->recipient->id, 'notification_type' => 'transactional', 'channel_type' => 'email', 'enabled' => false]);

        $log = $this->log('task.assigned');
        app(NotifyDispatcher::class)->dispatch($log);

        $this->assertSame('suppressed', $log->refresh()->status);
        $this->assertSame('Recipient opted out of transactional notifications on the email channel.', $log->failure_reason);
    }

    public function test_a_category_opt_out_and_an_event_opt_out_can_both_exist(): void
    {
        NotifyPreference::create(['user_id' => $this->recipient->id, 'notification_type' => 'transactional', 'channel_type' => 'email', 'enabled' => true]);
        NotifyPreference::setForEvent($this->recipient, 'task.due_soon', 'email', false);

        $this->assertSame(2, NotifyPreference::where('user_id', $this->recipient->id)->count());
        $this->assertTrue(NotifyPreference::blocks('amara@example.test', 'transactional', 'email', 'task.due_soon'));
        $this->assertFalse(NotifyPreference::blocks('amara@example.test', 'transactional', 'email', 'task.assigned'));
        $this->assertFalse(NotifyPreference::blocks('amara@example.test', 'transactional', 'email'));
    }

    public function test_security_notices_are_never_suppressed_even_with_an_event_opt_out(): void
    {
        // Rows written around the helper, as a stale or hand-edited row could be.
        NotifyPreference::create(['user_id' => $this->recipient->id, 'notification_type' => 'security', 'channel_type' => 'email', 'enabled' => false, 'event' => 'task.assigned']);
        NotifyPreference::create(['user_id' => $this->recipient->id, 'notification_type' => 'transactional', 'channel_type' => 'email', 'enabled' => false, 'event' => 'task.assigned']);

        $log = $this->log('task.assigned', 'security');
        app(NotifyDispatcher::class)->dispatch($log);

        $this->assertSame('sent', $log->refresh()->status);
    }

    public function test_the_helper_refuses_to_opt_out_of_a_security_event(): void
    {
        $platforms = config('notify_events.platforms');
        $platforms['tasks']['task.assigned']['notification_type'] = 'security';
        config(['notify_events.platforms' => $platforms]);

        $this->expectException(InvalidArgumentException::class);

        NotifyPreference::setForEvent($this->recipient, 'task.assigned', 'email', false);
    }

    public function test_the_helper_only_knows_catalogued_events(): void
    {
        $this->expectException(InvalidArgumentException::class);

        NotifyPreference::setForEvent($this->recipient, 'task.unheard_of', 'email', false);
    }

    public function test_the_database_keeps_one_row_per_person_type_channel_and_event(): void
    {
        $row = ['user_id' => $this->recipient->id, 'notification_type' => 'transactional', 'channel_type' => 'email', 'enabled' => false, 'event' => 'task.due_soon'];
        NotifyPreference::create($row);

        $this->expectException(UniqueConstraintViolationException::class);
        NotifyPreference::create($row);
    }

    public function test_the_database_still_keeps_one_category_row_per_person_type_and_channel(): void
    {
        $row = ['user_id' => $this->recipient->id, 'notification_type' => 'marketing', 'channel_type' => 'email', 'enabled' => false];
        NotifyPreference::create($row);

        $this->expectException(UniqueConstraintViolationException::class);
        NotifyPreference::create($row);
    }

    public function test_the_event_match_ignores_email_case_like_the_category_match(): void
    {
        NotifyPreference::setForEvent($this->recipient, 'task.due_soon', 'email', false);

        $this->assertTrue(NotifyPreference::blocks('AMARA@Example.TEST', 'transactional', 'email', 'task.due_soon'));
    }
}
