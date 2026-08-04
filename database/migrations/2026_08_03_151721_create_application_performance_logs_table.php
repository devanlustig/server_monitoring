<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('application_performance_logs', function (Blueprint $table) {
        $table->id();
        $table->string('application',100);
        $table->string('environment',30)->default('production');
        $table->string('server_name',100)->nullable();
        $table->string('method',10);
        $table->string('endpoint');
        $table->string('route_name')->nullable();
        $table->integer('status_code');
        $table->decimal('response_time_ms',10,2);
        $table->decimal('memory_usage_mb',10,2)->nullable();
        $table->decimal('peak_memory_mb',10,2)->nullable();
        $table->string('ip_address')->nullable();
        $table->string('request_id')->nullable();
        $table->json('extra')->nullable();
        $table->timestamp('requested_at');
        $table->timestamps();
        $table->index(['application']);
        $table->index(['endpoint']);
        $table->index(['requested_at']);
        $table->index(['status_code']);

    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_performance_logs');
    }
};
