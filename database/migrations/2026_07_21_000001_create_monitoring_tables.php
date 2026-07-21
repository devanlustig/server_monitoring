<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('server_id')->constrained('monitored_servers')->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 32); // http, tcp, icmp, dns, or a future custom probe.
            $table->string('endpoint')->nullable();
            $table->unsignedSmallInteger('port')->nullable();
            $table->unsignedInteger('interval_seconds')->default(60);
            $table->unsignedInteger('timeout_seconds')->default(10);
            $table->jsonb('configuration')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_checked_at')->nullable();
            $table->string('last_status', 16)->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'name']);
            $table->index(['is_active', 'last_checked_at']);
            $table->index(['server_id', 'type']);
        });

        Schema::create('monitoring_check_results', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('check_id')->constrained('monitoring_checks')->cascadeOnDelete();
            $table->string('status', 16); // up, down, degraded, timeout, or unknown.
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->text('message')->nullable();
            $table->jsonb('metrics')->nullable();
            $table->timestampTz('checked_at');

            $table->index(['check_id', 'checked_at']);
            $table->index(['status', 'checked_at']);
        });

        Schema::create('alert_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('check_id')->constrained('monitoring_checks')->cascadeOnDelete();
            $table->string('name');
            $table->string('condition', 32); // status, response_time, status_code, or custom metric.
            $table->string('operator', 8);
            $table->string('threshold')->nullable();
            $table->unsignedInteger('for_seconds')->default(0);
            $table->unsignedInteger('cooldown_seconds')->default(300);
            $table->jsonb('channels')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['check_id', 'is_active']);
        });

        Schema::create('incidents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('check_id')->constrained('monitoring_checks')->cascadeOnDelete();
            $table->foreignId('alert_rule_id')->nullable()->constrained('alert_rules')->nullOnDelete();
            $table->string('status', 16)->default('open'); // open, acknowledged, resolved.
            $table->string('severity', 16)->default('warning');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('acknowledged_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'started_at']);
            $table->index(['check_id', 'status']);
        });

        Schema::create('incident_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->string('event_type', 32); // opened, notified, acknowledged, resolved, or note.
            $table->text('message')->nullable();
            $table->jsonb('context')->nullable();
            $table->timestampTz('occurred_at');

            $table->index(['incident_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_events');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('alert_rules');
        Schema::dropIfExists('monitoring_check_results');
        Schema::dropIfExists('monitoring_checks');
    }
};
