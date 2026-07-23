<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitored_site_id')->constrained()->onDelete('cascade');
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->string('status', 20)->default('down');
            $table->string('cause')->nullable();
            $table->text('details')->nullable();
            $table->boolean('notified')->default(false);
            $table->timestamps();

            $table->index(['monitored_site_id', 'status']);
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
