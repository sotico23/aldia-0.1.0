<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('wallet_payment_requests', function (Blueprint $table): void {
            $table->foreignId('payer_wallet_id')->nullable()->change();
            $table->string('share_token', 64)->nullable()->unique();
        });
        DB::table('wallet_payment_requests')->orderBy('id')->chunkById(200, function ($requests): void {
            foreach ($requests as $request) {
                DB::table('wallet_payment_requests')->where('id', $request->id)->update(['share_token' => Str::random(64)]);
            }
        });
        Schema::table('wallet_payment_requests', function (Blueprint $table): void {
            $table->string('share_token', 64)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_payment_requests', function (Blueprint $table): void {
            $table->dropUnique(['share_token']);
            $table->dropColumn('share_token');
        });
    }
};
