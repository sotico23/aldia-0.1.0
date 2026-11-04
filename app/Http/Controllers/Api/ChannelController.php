<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChannelLink;
use App\Services\ChannelConfigService;
use App\Services\ChannelDataTokenService;
use App\Services\TelegramService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ChannelController extends Controller
{
    public function __construct(
        private readonly ChannelConfigService $channelConfig,
        private readonly ChannelDataTokenService $dataTokens
    ) {}

    public function resolve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel' => ['required', 'in:telegram,whatsapp'],
            'external_id' => ['required', 'string', 'max:80'],
        ]);

        $links = ChannelLink::withoutGlobalScopes()
            ->with('owner:id,name,business_name,is_active')
            ->where('channel', $validated['channel'])
            ->where('external_id', $validated['external_id'])
            ->active()
            ->orderBy('owner_id')
            ->orderBy('bot_scope')
            ->get();

        $matches = [];

        foreach ($links as $link) {
            $owner = $link->owner;

            if (! $owner || ! $owner->is_active) {
                continue;
            }

            $matches[] = [
                'tenant_id' => $owner->id,
                'tenant_name' => $owner->name,
                'business_name' => $owner->business_name ?: $owner->name,
                'bot_scope' => $link->bot_scope,
                'data_token' => $this->dataTokens->tokenForOwner((int) $owner->id),
            ];
        }

        return response()->json([
            'success' => true,
            'channel' => $validated['channel'],
            'external_id' => $validated['external_id'],
            'matches' => $matches,
        ]);
    }

    public function reply(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel' => ['required', 'in:telegram,whatsapp'],
            'external_id' => ['required', 'string', 'max:80'],
            'bot_scope' => ['required', 'string', 'max:40'],
            'message' => ['required', 'string', 'max:10000'],
        ]);
        $tenant = $request->user();
        $ownerId = (int) $tenant->getOwnerId();
        $expectedTenantScope = $this->channelConfig->botScopeForOwner($ownerId);

        if (! in_array($validated['bot_scope'], [ChannelConfigService::SCOPE_GLOBAL, $expectedTenantScope], true)) {
            return response()->json([
                'success' => false,
                'message' => 'El bot scope no corresponde al tenant autenticado.',
            ], Response::HTTP_FORBIDDEN);
        }

        $link = ChannelLink::withoutGlobalScopes()
            ->where('owner_id', $ownerId)
            ->where('channel', $validated['channel'])
            ->where('bot_scope', $validated['bot_scope'])
            ->where('external_id', $validated['external_id'])
            ->active()
            ->first();

        if (! $link) {
            return response()->json([
                'success' => false,
                'message' => 'El contacto no está vinculado a este tenant y canal.',
            ], Response::HTTP_FORBIDDEN);
        }

        $config = $validated['bot_scope'] === ChannelConfigService::SCOPE_GLOBAL
            ? $this->globalConfig($validated['channel'])
            : $this->tenantConfig($validated['channel'], $ownerId);

        $expectedMode = $validated['bot_scope'] === ChannelConfigService::SCOPE_GLOBAL
            ? ChannelConfigService::MODE_GLOBAL
            : ChannelConfigService::MODE_CUSTOM;
        $configMode = $config['mode'] ?? $config['source'] ?? null;
        $hasCredentials = $validated['channel'] === ChannelConfigService::CHANNEL_TELEGRAM
            ? filled($config['bot_token'] ?? null)
            : filled($config['access_token'] ?? null) && filled($config['phone_number_id'] ?? null);

        if ($configMode !== $expectedMode || ! ($config['is_active'] ?? false) || ! $hasCredentials) {
            return response()->json([
                'success' => false,
                'message' => 'El canal no está activo o no tiene credenciales configuradas.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = match ($validated['channel']) {
            ChannelConfigService::CHANNEL_TELEGRAM => (new TelegramService)
                ->withToken((string) $config['bot_token'])
                ->sendMessage($validated['external_id'], $validated['message']),
            ChannelConfigService::CHANNEL_WHATSAPP => (new WhatsAppService)
                ->withConfiguration([
                    'access_token' => $config['access_token'],
                    'phone_number_id' => $config['phone_number_id'],
                    'api_version' => $config['api_version'],
                ])
                ->sendMessage($validated['external_id'], $validated['message']),
        };

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'No se pudo enviar la respuesta.',
            ], Response::HTTP_BAD_GATEWAY);
        }

        return response()->json([
            'success' => true,
            'channel' => $validated['channel'],
            'external_id' => $validated['external_id'],
            'message_id' => $result['message_id'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function globalConfig(string $channel): array
    {
        return $channel === ChannelConfigService::CHANNEL_TELEGRAM
            ? $this->channelConfig->globalTelegram()
            : $this->channelConfig->globalWhatsApp();
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantConfig(string $channel, int $ownerId): array
    {
        return $channel === ChannelConfigService::CHANNEL_TELEGRAM
            ? $this->channelConfig->tenantTelegram($ownerId)
            : $this->channelConfig->tenantWhatsApp($ownerId);
    }
}
