<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitored_servers', function (Blueprint $table) {

            $table->boolean('is_online')
                ->default(false)
                ->after('is_active');

            $table->timestampTz('last_checked_at')
                ->nullable()
                ->after('last_successful_connection_at');

            $table->text('last_error')
                ->nullable()
                ->after('last_checked_at');

        });
    }

    public function down(): void
    {
        Schema::table('monitored_servers', function (Blueprint $table) {

            $table->dropColumn([
                'is_online',
                'last_checked_at',
                'last_error',
            ]);

        });
    }
};