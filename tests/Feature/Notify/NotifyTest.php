<?php

namespace Tests\Feature\Notify;

use App\Models\NotifyBatch;
use App\Models\NotifyChannel;
use App\Models\NotifyLog;
use App\Models\NotifyTemplate;
use App\Models\NotifyWebhook;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotifyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->withPersonalTeam()->create();
        $this->team = $this->user->currentTeam;
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $this->actingAs($this->user)->get('/dashboard')->assertOk()->assertViewIs('dashboard');
    }

    public function test_ecosystem_auth_rejects_missing_token(): void
    {
        $this->get('/auth/ecosystem')->assertStatus(403);
    }

    public function test_notify_channel_can_be_created(): void
    {
        $channel = NotifyChannel::create([
            'team_id' => $this->team->id,
            'type' => 'email',
            'name' => 'Primary Email',
        ]);
        $this->assertDatabaseHas('notify_channels', ['type' => 'email', 'name' => 'Primary Email']);
        $this->assertTrue($channel->team->is($this->team));
    }

    public function test_channel_healthy_detection(): void
    {
        $channel = NotifyChannel::create([
            'team_id' => $this->team->id,
            'type' => 'email',
            'name' => 'Test',
            'test_status' => 'ok',
            'is_active' => true,
        ]);
        $this->assertTrue($channel->isHealthy());
    }

    public function test_channel_unhealthy_when_inactive(): void
    {
        $channel = NotifyChannel::create([
            'team_id' => $this->team->id,
            'type' => 'email',
            'name' => 'Test',
            'is_active' => false,
        ]);
        $this->assertFalse($channel->isHealthy());
    }

    public function test_notify_template_can_be_created(): void
    {
        NotifyTemplate::create([
            'team_id' => $this->team->id,
            'name' => 'Welcome Email',
            'body' => 'Hi {{ name }}, welcome to InfoDot!',
            'channel_type' => 'email',
        ]);
        $this->assertDatabaseHas('notify_templates', ['name' => 'Welcome Email']);
    }

    public function test_template_renders_variables(): void
    {
        $tpl = NotifyTemplate::create([
            'team_id' => $this->team->id,
            'name' => 'Test',
            'body' => 'Hello {{ name }}!',
            'channel_type' => 'email',
        ]);
        $this->assertEquals('Hello Sakhile!', $tpl->render(['name' => 'Sakhile']));
    }

    public function test_notify_log_delivery_detection(): void
    {
        $log = NotifyLog::create([
            'team_id' => $this->team->id,
            'recipient' => 'user@example.com',
            'status' => 'delivered',
            'sent_at' => now(),
        ]);
        $this->assertTrue($log->wasDelivered());
        $this->assertFalse($log->hasFailed());
    }

    public function test_failed_log_detection(): void
    {
        $log = NotifyLog::create([
            'team_id' => $this->team->id,
            'recipient' => 'user@example.com',
            'status' => 'failed',
            'failure_reason' => 'Invalid email',
        ]);
        $this->assertTrue($log->hasFailed());
    }

    public function test_notify_batch_delivery_rate(): void
    {
        $batch = NotifyBatch::create([
            'team_id' => $this->team->id,
            'name' => 'Campaign 1',
            'status' => 'completed',
            'total_recipients' => 100,
            'sent_count' => 85,
        ]);
        $this->assertEquals(85.0, $batch->deliveryRate());
    }

    public function test_webhook_auto_generates_token(): void
    {
        $webhook = NotifyWebhook::create([
            'team_id' => $this->team->id,
            'name' => 'Stripe Events',
            'source' => 'Stripe',
        ]);
        $this->assertNotEmpty($webhook->endpoint_token);
        $this->assertEquals(48, strlen($webhook->endpoint_token));
    }

    public function test_team_has_notify_relationships(): void
    {
        NotifyChannel::create(['team_id' => $this->team->id, 'type' => 'sms', 'name' => 'SMS']);
        NotifyTemplate::create(['team_id' => $this->team->id, 'name' => 'Tpl', 'body' => 'Test', 'channel_type' => 'sms']);
        $this->assertCount(1, $this->team->notifyChannels);
        $this->assertCount(1, $this->team->notifyTemplates);
    }
}
