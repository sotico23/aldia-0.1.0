<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_integrations', function (Blueprint $table) {
            $table->string('whatsapp_phone_number_id')->nullable()->after('webhook_url');
            $table->string('whatsapp_access_token')->nullable()->after('whatsapp_phone_number_id');
            $table->string('whatsapp_business_id')->nullable()->after('whatsapp_access_token');
            $table->string('whatsapp_api_version')->nullable()->default('v22.0')->after('whatsapp_business_id');
        });
    }

    public function down(): void
    {
        Schema::table('system_integrations', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_phone_number_id',
                'whatsapp_access_token',
                'whatsapp_business_id',
                'whatsapp_api_version',
            ]);
        });
    }
};
