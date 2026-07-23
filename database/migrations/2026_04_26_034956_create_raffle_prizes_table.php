<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raffle_prizes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('raffle_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->integer('quantity')->default(1);
            $table->integer('position')->default(1);
            $table->decimal('estimated_value', 12, 2)->nullable();
            $table->enum('status', ['pending', 'awarded', 'cancelled'])->default('pending');
            $table->timestamps();

            $table->foreign('raffle_id')->references('id')->on('raffles')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raffle_prizes');
    }
};
