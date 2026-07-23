<?php

namespace App\Services;

use App\Models\ChannelCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected ?ChannelCredential $credentials = null;

    public function __construct(?int $ownerId = null)
    {
        if ($ownerId) {
            $this->credentials = ChannelCredential::where('owner_id', $ownerId)->first();
        }
    }

    public function forOwner(int $ownerId): static
    {
        $this->credentials = ChannelCredential::where('owner_id', $ownerId)->first();

        return $this;
    }

    public function getAccessToken(): ?string
    {
        return $this->credentials?->whatsapp_access_token;
    }

    public function getPhoneNumberId(): ?string
    {
        return $this->credentials?->whatsapp_phone_number_id;
    }

    public function getApiVersion(): string
    {
        return $this->credentials?->whatsapp_api_version ?? 'v22.0';
    }

    public function validateCredentials(?string $accessToken = null, ?string $phoneNumberId = null): array
    {
        $token = $accessToken ?? $this->getAccessToken();
        $phoneId = $phoneNumberId ?? $this->getPhoneNumberId();

        if (! $token) {
            return [
                'success' => false,
                'message' => 'No hay Access Token configurado.',
            ];
        }

        if (! $phoneId) {
            return [
                'success' => false,
                'message' => 'No hay Phone Number ID configurado.',
            ];
        }

        try {
            $version = $this->getApiVersion();
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withToken($token)
                ->get("https://graph.facebook.com/{$version}/{$phoneId}");

            $result = $response->json();

            if ($response->successful() && isset($result['id'])) {
                return [
                    'success' => true,
                    'message' => 'Conexión exitosa con WhatsApp Cloud API.',
                    'business_id' => $result['business']['id'] ?? $result['id'],
                    'phone_number' => $result['display_phone'] ?? '',
                    'name' => $result['name'] ?? '',
                ];
            }

            $errorMsg = $result['error']['message'] ?? 'Credenciales inválidas';

            return [
                'success' => false,
                'message' => "Error: {$errorMsg}",
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp validation error', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'No se pudo conectar con la API de WhatsApp.',
            ];
        }
    }

    public function getBusinessInfo(): array
    {
        $phoneId = $this->getPhoneNumberId();
        if (! $phoneId) {
            return [];
        }

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withToken($this->getAccessToken())
                ->get("https://graph.facebook.com/{$this->getApiVersion()}/{$phoneId}");

            return $response->successful() ? ($response->json() ?? []) : [];
        } catch (\Exception $e) {
            Log::error('WhatsApp getBusinessInfo error', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function sendMessage(string $to, string $message): array
    {
        $phoneId = $this->getPhoneNumberId();
        if (! $phoneId) {
            return [
                'success' => false,
                'message' => 'WhatsApp Phone Number ID no configurado.',
            ];
        }

        try {
            $response = Http::timeout(15)
                ->connectTimeout(5)
                ->withToken($this->getAccessToken())
                ->post("https://graph.facebook.com/{$this->getApiVersion()}/{$phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'text',
                    'text' => [
                        'body' => $message,
                    ],
                ]);

            $result = $response->json();

            if ($response->successful() && ($result['messages'][0]['id'] ?? false)) {
                $messageId = $result['messages'][0]['id'];

                Log::info('WhatsApp sendMessage success', [
                    'to' => $to,
                    'message_id' => $messageId,
                ]);

                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'to' => $to,
                ];
            }

            $errorMsg = $result['error']['message'] ?? 'Unknown error';
            Log::warning('WhatsApp sendMessage failed', [
                'to' => $to,
                'error' => $errorMsg,
            ]);

            return [
                'success' => false,
                'message' => "Error: {$errorMsg}",
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp sendMessage error', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error de conexión con WhatsApp.',
            ];
        }
    }
}
