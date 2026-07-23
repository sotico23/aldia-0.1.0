<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uptime_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitored_site_id')->constrained()->onDelete('cascade');
            $table->timestamp('checked_at');
            $table->string('status', 20);
            $table->integer('response_time_ms');
            $table->integer('http_status_code')->nullable();
            $table->text('response_body')->nullable();
            $table->string('error_message')->nullable();
            $table->string('dns_time_ms')->nullable();
            $table->string('connect_time_ms')->nullable();
            $table->string('ssl_expiry_days')->nullable();
            $table->timestamps();

            $table->index(['monitored_site_id', 'checked_at']);
            $table->index('checked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uptime_checks');
    }
};
