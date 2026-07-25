<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memory_metrics', function (Blueprint $table) {

            $table->id();

            $table->foreignId('server_id')
                ->constrained('monitored_servers')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('total');

            $table->unsignedBigInteger('used');

            $table->unsignedBigInteger('free');

            $table->unsignedBigInteger('shared');

            $table->unsignedBigInteger('cache');

            $table->unsignedBigInteger('available');

            $table->decimal('usage_percent', 5, 2);

            $table->timestampTz('collected_at');

            $table->index([
                'server_id',
                'collected_at',
            ]);

            $table->index('collected_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_metrics');
    }
};