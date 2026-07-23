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
        Schema::create('web_settings', function (Blueprint $table) {
            $table->id();
            $table->string('app_name')->default('Laravel Starter Kit');
            $table->string('app_logo')->nullable();
            $table->string('app_favicon')->nullable();
            $table->string('app_title')->default('Laravel Starter Kit');
            $table->text('app_description')->nullable();
            $table->string('app_keywords')->nullable();
            $table->string('app_author')->nullable();
            $table->string('timezone')->default('America/Lima');
            $table->string('locale')->default('es');
            $table->string('currency')->default('PEN');
            $table->string('currency_symbol')->default('S/');
            $table->boolean('maintenance_mode')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_settings');
    }
};
