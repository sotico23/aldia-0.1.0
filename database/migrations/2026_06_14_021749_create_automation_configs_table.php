<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 20)->default('telegram');
            $table->string('frequency', 20)->default('daily');
            $table->string('execution_time', 5)->default('08:00');
            $table->boolean('enabled')->default(false);
            $table->json('selected_reports')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->string('last_run_status', 20)->nullable();
            $table->timestamps();

            $table->unique('owner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_configs');
    }
};
