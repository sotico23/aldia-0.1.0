<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('modo', 20)->default('ambos');
            $table->unsignedSmallInteger('pool_timeout_min')->default(10);
            $table->unsignedSmallInteger('pool_reenvio_min')->default(30);
            $table->timestamps();

            $table->unique('owner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_configs');
    }
};
