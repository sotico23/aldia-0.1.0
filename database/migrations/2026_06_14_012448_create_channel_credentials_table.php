<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('telegram_bot_token')->nullable();
            $table->string('telegram_bot_username')->nullable();
            $table->string('whatsapp_phone_number_id', 50)->nullable();
            $table->text('whatsapp_access_token')->nullable();
            $table->string('whatsapp_business_id', 50)->nullable();
            $table->string('whatsapp_api_version', 20)->default('v22.0');
            $table->timestamps();

            $table->unique('owner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_credentials');
    }
};
