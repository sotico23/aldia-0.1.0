<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitored_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('url');
            $table->string('method', 10)->default('GET');
            $table->string('expected_status', 10)->default('200');
            $table->string('expected_string')->nullable();
            $table->string('type', 20)->default('http');
            $table->integer('check_interval')->default(5);
            $table->integer('timeout')->default(30);
            $table->integer('response_time_threshold')->default(1000);
            $table->boolean('is_active')->default(true);
            $table->boolean('ssl_expiry_check')->default(false);
            $table->integer('ssl_days_before_expiry')->default(30);
            $table->timestamp('last_check_at')->nullable();
            $table->string('last_status', 20)->nullable();
            $table->integer('last_response_time')->nullable();
            $table->timestamp('last_down_at')->nullable();
            $table->timestamp('last_recovered_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index('last_check_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitored_sites');
    }
};
