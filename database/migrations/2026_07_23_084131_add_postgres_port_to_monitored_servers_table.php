<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitored_servers', function (Blueprint $table) {

            $table->unsignedSmallInteger('postgres_port')
                  ->default(5432)
                  ->after('ssh_port');

        });
    }

    public function down(): void
    {
        Schema::table('monitored_servers', function (Blueprint $table) {

            $table->dropColumn('postgres_port');

        });
    }
};
