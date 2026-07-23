<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raffles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('slug')->unique();
            $table->string('image_url')->nullable();
            $table->enum('type', ['raffle', 'draw', 'competition'])->default('raffle');
            $table->enum('status', ['draft', 'published', 'active', 'completed', 'cancelled'])->default('draft');
            $table->integer('max_participants')->nullable();
            $table->integer('min_participants')->nullable();
            $table->decimal('ticket_price', 10, 2)->default(0);
            $table->boolean('allow_multiple_entries')->default(false);
            $table->integer('max_entries_per_user')->default(1);
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->boolean('show_winners')->default(true);
            $table->string('background_color')->nullable();
            $table->string('text_color')->nullable();
            $table->json('custom_settings')->nullable();
            $table->timestamps();

            $table->index('owner_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raffles');
    }
};
