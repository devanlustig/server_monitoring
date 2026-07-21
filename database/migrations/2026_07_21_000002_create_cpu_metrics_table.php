<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpu_metrics', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('server_id')->nullable()->constrained('monitored_servers')->nullOnDelete();
            $table->string('hostname');
            $table->decimal('usage_percent', 5, 2)->nullable();
            $table->decimal('load_1', 8, 2)->nullable();
            $table->decimal('load_5', 8, 2)->nullable();
            $table->decimal('load_15', 8, 2)->nullable();
            $table->unsignedSmallInteger('core_count')->nullable();
            $table->timestampTz('collected_at');

            $table->index(['server_id', 'collected_at']);
            $table->index('collected_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpu_metrics');
    }
};
