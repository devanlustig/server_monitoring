<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitored_servers', function (Blueprint $table): void {
            $table->string('system_hostname')->nullable()->after('hostname');
            $table->text('operating_system')->nullable()->after('system_hostname');
            $table->string('kernel_version')->nullable()->after('operating_system');
            $table->text('cpu_model')->nullable()->after('kernel_version');
            $table->unsignedSmallInteger('cpu_core_count')->nullable()->after('cpu_model');
            $table->unsignedBigInteger('total_ram_bytes')->nullable()->after('cpu_core_count');
            $table->unsignedBigInteger('total_disk_bytes')->nullable()->after('total_ram_bytes');
            $table->timestampTz('last_successful_connection_at')->nullable()->after('total_disk_bytes');
        });
    }

    public function down(): void
    {
        Schema::table('monitored_servers', function (Blueprint $table): void {
            $table->dropColumn([
                'system_hostname', 'operating_system', 'kernel_version', 'cpu_model', 'cpu_core_count',
                'total_ram_bytes', 'total_disk_bytes', 'last_successful_connection_at',
            ]);
        });
    }
};
