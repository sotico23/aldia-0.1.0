<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uptime_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('monitored_site_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('type', 30);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('webhook_enabled')->default(false);
            $table->string('webhook_url')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uptime_alerts');
    }
};
