<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disk_directory_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('monitored_servers')->onDelete('cascade');
            $table->string('path', 500);
            $table->bigInteger('size_bytes');
            $table->timestamp('snapshot_at');

            $table->index(['server_id', 'snapshot_at']);
            $table->index(['server_id', 'path']);
        });

        Schema::create('disk_file_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('monitored_servers')->onDelete('cascade');
            $table->string('directory_path', 500);
            $table->string('file_path', 500);
            $table->bigInteger('size_bytes');
            $table->timestamp('snapshot_at');

            $table->index(['server_id', 'snapshot_at']);
            $table->index(['server_id', 'directory_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disk_file_snapshots');
        Schema::dropIfExists('disk_directory_snapshots');
    }
};
