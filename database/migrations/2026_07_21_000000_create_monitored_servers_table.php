<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitored_servers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('hostname');
            $table->ipAddress('ip_address')->nullable();
            $table->string('environment', 32)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->jsonb('tags')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique('hostname');
            $table->index(['is_active', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitored_servers');
    }
};
