<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->string('base_url')->nullable();
            $table->string('webhook_url')->nullable();
            $table->text('api_key')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_check_at')->nullable();
            $table->string('last_check_status', 20)->nullable();
            $table->timestamps();

            $table->unique('provider');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_integrations');
    }
};
