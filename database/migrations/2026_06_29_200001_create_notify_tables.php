<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notify_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('type'); // email, sms, push, webhook, slack, in_app
            $table->string('name');
            $table->json('config')->nullable(); // driver-specific config (SMTP creds, Slack webhook URL, etc.)
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->string('test_status')->nullable(); // ok, failed
            $table->timestamps();
        });

        Schema::create('notify_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('subject')->nullable(); // for email
            $table->text('body'); // supports {{ variable }} syntax
            $table->json('variables')->nullable(); // list of expected variable names
            $table->string('channel_type')->nullable(); // email, sms, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('notify_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('notify_templates')->nullOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained('notify_channels')->nullOnDelete();
            $table->string('trigger_event'); // user.signup, payment.failed, invoice.due, custom.*
            $table->json('conditions')->nullable(); // filter conditions
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('notify_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained('notify_channels')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('notify_templates')->nullOnDelete();
            $table->string('recipient'); // email, phone, device token, etc.
            $table->string('subject')->nullable();
            $table->text('body_preview')->nullable();
            $table->string('status'); // queued, sent, delivered, failed, bounced, opened, clicked
            $table->string('failure_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();
            $table->index(['team_id', 'status', 'sent_at']);
        });

        Schema::create('notify_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('notification_type'); // marketing, transactional, security, product_updates
            $table->string('channel_type'); // email, sms, push, in_app
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'notification_type', 'channel_type']);
        });

        Schema::create('notify_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('notify_templates')->nullOnDelete();
            $table->string('name');
            $table->string('status')->default('draft'); // draft, queued, sending, completed, failed
            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notify_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('notify_rules')->nullOnDelete();
            $table->string('name');
            $table->string('cron_expression'); // e.g., "0 9 * * 1" (every Monday 9am)
            $table->string('timezone')->default('Africa/Johannesburg');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notify_webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('name');
            $table->string('endpoint_token', 64)->unique();
            $table->string('source')->nullable(); // Stripe, GitHub, etc.
            $table->json('event_map')->nullable(); // map source event → trigger_event
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_received_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notify_webhooks');
        Schema::dropIfExists('notify_schedules');
        Schema::dropIfExists('notify_batches');
        Schema::dropIfExists('notify_preferences');
        Schema::dropIfExists('notify_logs');
        Schema::dropIfExists('notify_rules');
        Schema::dropIfExists('notify_templates');
        Schema::dropIfExists('notify_channels');
    }
};
