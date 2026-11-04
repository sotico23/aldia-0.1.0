<?php

use App\Models\SystemIntegration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $legacy = DB::table('web_settings')->orderByDesc('id')->first();

        if ($legacy) {
            $telegram = SystemIntegration::firstOrNew(['provider' => 'telegram']);

            if (! $telegram->telegram_bot_token && $legacy->global_telegram_bot_token) {
                $telegram->telegram_bot_token = $legacy->global_telegram_bot_token;
            }

            if (! $telegram->telegram_bot_username && $legacy->global_telegram_bot_username) {
                $telegram->telegram_bot_username = $legacy->global_telegram_bot_username;
            }

            $telegram->is_active = $telegram->is_active || (bool) $telegram->telegram_bot_token;
            $telegram->save();

            $whatsapp = SystemIntegration::firstOrNew(['provider' => 'whatsapp']);

            $whatsapp->whatsapp_phone_number_id = $whatsapp->whatsapp_phone_number_id
                ?: $legacy->whatsapp_phone_number_id;
            $whatsapp->whatsapp_access_token = $whatsapp->whatsapp_access_token
                ?: $legacy->whatsapp_access_token;
            $whatsapp->whatsapp_business_id = $whatsapp->whatsapp_business_id
                ?: $legacy->whatsapp_business_id;
            $whatsapp->whatsapp_api_version = $whatsapp->whatsapp_api_version
                ?: ($legacy->whatsapp_api_version ?: 'v22.0');
            $whatsapp->is_active = $whatsapp->is_active || (bool) $whatsapp->whatsapp_access_token;
            $whatsapp->save();
        }

        $n8n = SystemIntegration::forProvider('n8n')->first();

        if ($n8n) {
            $whatsapp = SystemIntegration::forProvider('whatsapp')->first();

            if ($whatsapp) {
                $whatsapp->whatsapp_phone_number_id = $whatsapp->whatsapp_phone_number_id
                    ?: $n8n->whatsapp_phone_number_id;
                $whatsapp->whatsapp_access_token = $whatsapp->whatsapp_access_token
                    ?: $n8n->whatsapp_access_token;
                $whatsapp->whatsapp_business_id = $whatsapp->whatsapp_business_id
                    ?: $n8n->whatsapp_business_id;
                $whatsapp->whatsapp_api_version = $whatsapp->whatsapp_api_version
                    ?: ($n8n->whatsapp_api_version ?: 'v22.0');
                $whatsapp->is_active = $whatsapp->is_active || (bool) $whatsapp->whatsapp_access_token;
                $whatsapp->save();
            }
        }
    }

    public function down(): void
    {
        // Data migration: los valores legacy se mantienen intactos.
    }
};
