<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('route_name', 255)->nullable();
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->timestamp('visited_at');

            $table->index('visited_at');
            $table->index('user_id');
            $table->index('route_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
