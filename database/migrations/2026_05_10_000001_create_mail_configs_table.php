<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');

            $table->string('name', 100);
            $table->enum('driver', ['smtp', 'sendmail', 'mailgun', 'postmark', 'ses'])->default('smtp');

            $table->string('host')->nullable();
            $table->smallInteger('port')->nullable();
            $table->enum('encryption', ['tls', 'ssl', null])->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->text('secret')->nullable();
            $table->string('domain')->nullable();
            $table->string('endpoint')->nullable();
            $table->string('region', 50)->nullable();

            $table->string('from_address');
            $table->string('from_name');

            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);

            $table->timestamps();

            $table->index(['owner_id', 'is_active']);
            $table->index(['owner_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_configs');
    }
};
