<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disk_metrics', function (Blueprint $table) {

            $table->id();

            $table->foreignId('server_id')
                ->constrained('monitored_servers')
                ->cascadeOnDelete();

            $table->string('hostname');

            $table->bigInteger('total');

            $table->bigInteger('used');

            $table->bigInteger('available');

            $table->decimal('usage_percent',5,2);

            $table->timestampTz('collected_at');

            $table->index([
                'server_id',
                'collected_at'
            ]);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disk_metrics');
    }
};