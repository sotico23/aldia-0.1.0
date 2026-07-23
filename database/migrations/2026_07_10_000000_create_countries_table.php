<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique();
            $table->string('name', 100);
            $table->string('currency_code', 3);
            $table->string('currency_symbol', 10);
            $table->unsignedTinyInteger('currency_decimals')->default(0);
            $table->string('locale', 10);
            $table->string('timezone', 50);
            $table->string('phone_code', 5);
            $table->string('tax_name', 20);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->string('fiscal_id_label', 20);
            $table->string('fiscal_id_pattern', 50)->nullable();
            $table->string('date_format', 20)->default('DD/MM/YYYY');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
