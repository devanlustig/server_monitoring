<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postgresql_database_size_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('monitored_servers')->onDelete('cascade');
            $table->string('database_oid');
            $table->string('database_name');
            $table->bigInteger('size_bytes');
            $table->timestamp('snapshot_at');

            $table->index(['server_id', 'snapshot_at']);
            $table->index(['server_id', 'database_oid', 'snapshot_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postgresql_database_size_snapshots');
    }
};
