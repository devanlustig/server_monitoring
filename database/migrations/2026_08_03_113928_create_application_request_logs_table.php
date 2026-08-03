<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_request_logs', function (Blueprint $table) {

            $table->id();

            $table->string('method',10);

            $table->string('url');

            $table->string('route_name')->nullable();

            $table->string('controller')->nullable();

            $table->unsignedSmallInteger('status_code');

            $table->decimal('response_time_ms',10,2);

            $table->decimal('memory_usage_mb',10,2);

            $table->decimal('peak_memory_mb',10,2);

            $table->ipAddress('ip_address')->nullable();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->boolean('is_slow')->default(false);

            $table->timestamp('created_at');

            $table->index('created_at');
            $table->index('route_name');
            $table->index('is_slow');

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_request_logs');
    }
};