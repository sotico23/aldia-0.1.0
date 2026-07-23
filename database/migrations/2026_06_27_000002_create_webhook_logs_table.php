<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('gateway'); // paypal, mercadopago
            $table->string('event_type')->nullable();
            $table->string('event_id')->nullable()->unique();
            $table->string('status'); // received, failed, duplicate, processed
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();

            $table->index(['gateway', 'status']);
            $table->index('status');
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
