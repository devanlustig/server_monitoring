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
        Schema::table('monitored_servers', function (Blueprint $table) {

            $table->string('uptime')->nullable()->after('last_error');

            $table->string('load_average')->nullable()->after('uptime');

        });
    }

    public function down(): void
    {
        Schema::table('monitored_servers', function (Blueprint $table) {

            $table->dropColumn([
                'uptime',
                'load_average'
            ]);

        });
    }

    
};
