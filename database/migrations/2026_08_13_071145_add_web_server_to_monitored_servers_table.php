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
        Schema::table('monitored_servers', function (Blueprint $table) { $table->string('web_server')->nullable()->after('postgres_port'); });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monitored_servers', function (Blueprint $table) { $table->dropColumn('web_server'); });
    }
};
