<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('platform'); // dot.files, dot.agents, etc.
            $table->string('display_name');
            $table->string('base_url')->nullable();
            $table->string('status')->default('pending'); // pending, connected, error
            $table->timestamp('last_synced_at')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();
            $table->unique(['team_id', 'platform']);
        });

        Schema::create('analytics_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('data_source_id')->constrained('data_sources')->cascadeOnDelete();
            $table->string('snapshot_type'); // daily, hourly, manual
            $table->json('payload');
            $table->timestamp('captured_at');
            $table->timestamps();
            $table->index(['team_id', 'data_source_id', 'captured_at']);
        });

        Schema::create('intelligence_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type'); // customer, employee, asset, product, etc.
            $table->string('entity_id');
            $table->string('label');
            $table->string('source_platform');
            $table->json('attributes')->nullable();
            $table->timestamps();
            $table->index(['team_id', 'entity_type', 'entity_id']);
        });

        Schema::create('intelligence_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_node_id')->constrained('intelligence_nodes')->cascadeOnDelete();
            $table->foreignId('to_node_id')->constrained('intelligence_nodes')->cascadeOnDelete();
            $table->string('relationship'); // owns, causes, correlates_with, etc.
            $table->float('weight')->default(1.0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('business_dna_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('industry')->nullable();
            $table->json('operational_patterns')->nullable();
            $table->json('seasonal_trends')->nullable();
            $table->json('risk_tolerance')->nullable();
            $table->json('growth_signals')->nullable();
            $table->timestamp('last_computed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('metric_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('source_platform');
            $table->string('engine'); // business, financial, people, etc.
            $table->string('aggregation')->default('sum'); // sum, avg, count, latest
            $table->string('unit')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('computed_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('metric_definition_id')->constrained()->cascadeOnDelete();
            $table->decimal('value', 20, 4);
            $table->string('period'); // daily, weekly, monthly
            $table->date('period_date');
            $table->timestamps();
            $table->unique(['team_id', 'metric_definition_id', 'period', 'period_date']);
        });

        Schema::create('analytics_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('metric_definition_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('severity')->default('info'); // info, warning, critical
            $table->string('status')->default('open'); // open, acknowledged, resolved
            $table->json('context')->nullable();
            $table->timestamp('triggered_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['team_id', 'status', 'severity']);
        });

        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('engine'); // decision, predictive, risk, etc.
            $table->string('title');
            $table->text('rationale');
            $table->string('action_label')->nullable();
            $table->string('action_url')->nullable();
            $table->string('priority')->default('medium'); // low, medium, high, critical
            $table->string('status')->default('pending'); // pending, actioned, dismissed
            $table->json('supporting_data')->nullable();
            $table->timestamps();
            $table->index(['team_id', 'status', 'priority']);
        });

        Schema::create('analytics_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('ad_hoc'); // ad_hoc, scheduled
            $table->string('cron_expression')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('report_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analytics_report_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('queued'); // queued, running, completed, failed
            $table->json('output')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('analytics_dashboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_default')->default(false);
            $table->json('layout')->nullable();
            $table->timestamps();
        });

        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analytics_dashboard_id')->constrained()->cascadeOnDelete();
            $table->string('widget_type'); // metric_card, chart, alert_feed, recommendation_feed
            $table->string('title')->nullable();
            $table->json('config')->nullable();
            $table->unsignedInteger('col')->default(0);
            $table->unsignedInteger('row')->default(0);
            $table->unsignedInteger('width')->default(4);
            $table->unsignedInteger('height')->default(2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_widgets');
        Schema::dropIfExists('analytics_dashboards');
        Schema::dropIfExists('report_runs');
        Schema::dropIfExists('analytics_reports');
        Schema::dropIfExists('recommendations');
        Schema::dropIfExists('analytics_alerts');
        Schema::dropIfExists('computed_metrics');
        Schema::dropIfExists('metric_definitions');
        Schema::dropIfExists('business_dna_profiles');
        Schema::dropIfExists('intelligence_edges');
        Schema::dropIfExists('intelligence_nodes');
        Schema::dropIfExists('analytics_snapshots');
        Schema::dropIfExists('data_sources');
    }
};
