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
        Schema::create('metric_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitored_server_id')->constrained('monitored_servers')->onDelete('cascade');
            $table->string('category');
            $table->string('metric_name');
            $table->double('metric_value');
            $table->string('metric_unit')->nullable();
            $table->timestamp('snapshot_at');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metric_histories');
    }
};
