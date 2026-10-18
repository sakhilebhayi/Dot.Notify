<?php

namespace Tests\Feature\Notify;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotifySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_hot_paths_have_indexes(): void
    {
        $this->assertTrue(Schema::hasIndex('notify_logs', ['status', 'next_retry_at']), 'sweeper scan');
        $this->assertTrue(Schema::hasIndex('notify_logs', ['rule_id', 'created_at']), 'rate ceiling count');
        $this->assertTrue(Schema::hasIndex('notify_inbound_events', ['notify_webhook_id']), 'webhook -> events');
        $this->assertTrue(Schema::hasIndex('notify_inbound_events', ['team_id', 'platform', 'idempotency_key'], 'unique'), 'idempotency');
    }

    public function test_delivery_attempt_columns_exist(): void
    {
        foreach (['attempts', 'last_error', 'next_retry_at', 'last_attempt_at', 'sent_at', 'notification_type', 'body'] as $column) {
            $this->assertTrue(Schema::hasColumn('notify_logs', $column), $column);
        }
        foreach (['platform', 'idempotency_key'] as $column) {
            $this->assertTrue(Schema::hasColumn('notify_inbound_events', $column), $column);
        }
    }
}
