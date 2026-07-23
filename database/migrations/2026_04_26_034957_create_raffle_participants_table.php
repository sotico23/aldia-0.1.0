<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raffle_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('raffle_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('ip_address')->nullable();
            $table->integer('entries')->default(1);
            $table->boolean('is_winner')->default(false);
            $table->unsignedBigInteger('prize_id')->nullable();
            $table->timestamp('won_at')->nullable();
            $table->timestamps();

            $table->foreign('raffle_id')->references('id')->on('raffles')->onDelete('cascade');
            $table->foreign('prize_id')->references('id')->on('raffle_prizes')->onDelete('set null');

            $table->unique(['raffle_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raffle_participants');
    }
};
