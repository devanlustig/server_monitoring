<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitored_servers', function (Blueprint $table): void {
            $table->string('authentication_method', 32)->default('ssh_password')->after('hostname');
            $table->unsignedSmallInteger('ssh_port')->default(22)->after('authentication_method');
            $table->text('ssh_username')->nullable()->after('ssh_port');
            $table->text('ssh_password')->nullable()->after('ssh_username');
        });
    }

    public function down(): void
    {
        Schema::table('monitored_servers', function (Blueprint $table): void {
            $table->dropColumn(['authentication_method', 'ssh_port', 'ssh_username', 'ssh_password']);
        });
    }
};
