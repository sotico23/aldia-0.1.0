<?php

namespace App\Http\Controllers\Backend;

use App\Events\ChannelConfigurationUpdated;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\MailTemplate;
use App\Models\User;
use App\Models\WebSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class WebSettingController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:admin.web-settings.edit', only: ['edit', 'update', 'disconnectTelegramLogin']),
        ];
    }

    public function index(): Response
    {
        $settings = WebSetting::getSettings();

        $defaults = [
            'hero' => [
                'titulo' => $settings->hero_titulo ?? 'Gestiona tu negocio como un experto',
                'subtitulo' => $settings->hero_subtitulo ?? 'La plataforma todo-en-uno que necesitas para hacer crecer tu empresa. Desde inventario hasta facturación, todo en un solo lugar.',
                'boton_principal' => $settings->hero_boton_principal ?? 'Comenzar gratis',
                'boton_secundario' => $settings->hero_boton_secundario ?? 'Ver demo',
                'badge' => $settings->hero_badge ?? '¡Nuevo! IA integrada para predicción de inventario',
            ],
            'caracteristicas' => $settings->caracteristicas ?? [
                ['icono' => '📊', 'titulo' => 'Dashboard Inteligente', 'descripcion' => 'Visualiza tus métricas en tiempo real con gráficos interactivos'],
                ['icono' => '👥', 'titulo' => 'Gestión de Clientes', 'descripcion' => 'CRM completo para gestionar prospectos, oportunidades y clientes'],
                ['icono' => '📦', 'titulo' => 'Control de Inventario', 'descripcion' => 'Gestiona tu stock con alertas automáticas y múltiples almacenes'],
                ['icono' => '💰', 'titulo' => 'Facturación Electrónica', 'descripcion' => 'Emite facturas, cotizaciones y gestiona tu tesorería'],
                ['icono' => '📈', 'titulo' => 'Reportes Avanzados', 'descripcion' => 'Toma decisiones basadas en datos con análisis detallados'],
                ['icono' => '🔗', 'titulo' => 'Integraciones', 'descripcion' => 'Conecta con pasarelas de pago, envíos y más'],
            ],
            'planes' => $settings->planes ?? [
                [
                    'nombre' => 'Gratuito',
                    'precio' => '$0',
                    'periodo' => '/mes',
                    'descripcion' => 'Perfecto para comenzar',
                    'popular' => false,
                    'caracteristicas' => ['Hasta 10 clientes', 'Gestión básica de inventario', '1 usuario administrador', 'Reportes simples', 'Soporte por email'],
                ],
                [
                    'nombre' => 'Vendedor Independiente',
                    'precio' => '$29',
                    'periodo' => '/mes',
                    'descripcion' => 'Para vendedores individuales',
                    'popular' => false,
                    'caracteristicas' => ['Hasta 100 clientes', 'Gestión completa de inventario', '3 usuarios', 'Facturación electrónica', 'Reportes avanzados', 'Soporte prioritario'],
                ],
                [
                    'nombre' => 'Premium',
                    'precio' => '$99',
                    'periodo' => '/mes',
                    'descripcion' => 'Para pequeñas empresas',
                    'popular' => true,
                    'caracteristicas' => ['Clientes ilimitados', 'Gestión de proveedores', '10 usuarios', 'Facturación electrónica completa', 'Reportes detallados', 'Múltiples almacenes', 'Integraciones', 'Soporte por chat'],
                ],
                [
                    'nombre' => 'Enterprise',
                    'precio' => '$299',
                    'periodo' => '/mes',
                    'descripcion' => 'Para empresas en crecimiento',
                    'popular' => false,
                    'caracteristicas' => ['Todo del plan Premium', 'Usuarios ilimitados', 'Múltiples sucursales', 'Gestión de empleados', 'Control de acceso avanzado', 'Auditoría completa', 'Personalización completa', 'Soporte telefónico'],
                ],
                [
                    'nombre' => 'Corporativo',
                    'precio' => 'Custom',
                    'periodo' => '',
                    'descripcion' => 'Para grandes organizaciones',
                    'popular' => false,
                    'caracteristicas' => ['Todo del plan Enterprise', 'Servidor dedicado', '部署 local', 'Personalización de marca', 'Capacitación dedicada', 'Gerente de cuenta', 'SLA garantizado', 'Soporte 24/7'],
                ],
            ],
            'cta' => [
                'titulo' => $settings->cta_titulo ?? '¿Listo para transformar tu negocio?',
                'descripcion' => $settings->cta_descripcion ?? 'Únete a miles de empresas que ya están creciendo con GrowERP',
                'boton' => $settings->cta_boton ?? 'Crear cuenta gratis',
            ],
            'general' => [
                'nombre_sitio' => $settings->app_name ?? 'GrowERP',
                'logo_letra' => substr($settings->app_name ?? 'G', 0, 1),
            ],
            'nav' => [
                'app_brand_visible' => $settings->nav_app_brand_visible ?? true,
                'quienes_somos_visible' => $settings->nav_quienes_somos_visible ?? true,
                'quienes_somos_label' => $settings->nav_quienes_somos_label ?? 'Quiénes Somos',
                'quienes_somos_subtitle' => $settings->nav_quienes_somos_subtitle ?? '',
                'quienes_somos_content' => $settings->nav_quienes_somos_content ?? '',
                'quienes_somos_image' => $settings->nav_quienes_somos_image ?? '',
                'feedback_visible' => $settings->nav_feedback_visible ?? true,
                'feedback_label' => $settings->nav_feedback_label ?? 'Feedback',
                'feedback_subtitle' => $settings->nav_feedback_subtitle ?? '',
                'feedback_content' => $settings->nav_feedback_content ?? '',
                'feedback_image' => $settings->nav_feedback_image ?? '',
                'fundacion_visible' => $settings->nav_fundacion_visible ?? true,
                'fundacion_label' => $settings->nav_fundacion_label ?? 'Nuestra Fundación',
                'fundacion_subtitle' => $settings->nav_fundacion_subtitle ?? '',
                'fundacion_content' => $settings->nav_fundacion_content ?? '',
                'fundacion_image' => $settings->nav_fundacion_image ?? '',
            ],
        ];

        $parsedLogs = [];

        // Consultar usuarios en línea
        $latestSessions = DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', now()->subMinutes(15)->getTimestamp())
            ->pluck('user_id')
            ->unique();

        $onlineUsers = User::whereIn('id', $latestSessions)
            ->select('id', 'name', 'email')
            ->get();

        // Obtener plantillas de email de sistema
        $ownerId = Auth::user()->getOwnerId();
        $templates = MailTemplate::where('owner_id', $ownerId)
            ->where('type', 'system')
            ->orderBy('name')
            ->get();

        $availableSlugs = MailTemplate::getAvailableSlugs();
        $templatesBySlug = $templates->keyBy('slug');

        $templatesWithDefaults = collect($availableSlugs)->map(function ($name, $slug) use ($templatesBySlug) {
            $template = $templatesBySlug[$slug] ?? null;

            return [
                'id' => $template?->id,
                'slug' => $slug,
                'name' => $name,
                'subject' => $template?->subject ?? '',
                'content' => $template?->content ?? '',
                'is_active' => $template?->is_active ?? true,
                'is_default' => true,
                'type' => 'system',
            ];
        });

        $customTemplates = $templates->filter(function ($template) use ($availableSlugs) {
            return ! array_key_exists($template->slug, $availableSlugs);
        })->map(function ($template) {
            return [
                'id' => $template->id,
                'slug' => $template->slug,
                'name' => $template->name,
                'subject' => $template->subject,
                'content' => $template->content,
                'is_active' => $template->is_active,
                'is_default' => false,
                'type' => 'system',
            ];
        });

        $allTemplates = $templatesWithDefaults->merge($customTemplates)->values();

        $countries = Country::all();

        return Inertia::render('Backend/ConfiguracionWeb/Index', array_merge([
            'settings' => $settings,
            'logs' => $parsedLogs,
            'onlineUsers' => $onlineUsers,
            'templates' => $allTemplates,
            'countries' => $countries,
            'type' => 'system',
        ], $defaults));
    }

    public function update(Request $request, WebSetting $configuracion_web): RedirectResponse
    {
        $validated = $request->validate([
            'app_name' => 'required|string|max:255',
            'app_title' => 'required|string|max:255',
            'app_description' => 'nullable|string',
            'app_keywords' => 'nullable|string|max:500',
            'app_author' => 'nullable|string|max:255',
            'timezone' => 'required|string|max:100',
            'locale' => 'required|string|max:10',
            'currency' => 'required|string|max:10',
            'currency_symbol' => 'required|string|max:5',
            'maintenance_mode' => 'boolean',
            'google_client_id' => 'nullable|string|max:255',
            'google_client_secret' => 'nullable|string',
            'google_redirect_uri' => 'nullable|string|max:255',
            'facebook_client_id' => 'nullable|string|max:255',
            'facebook_client_secret' => 'nullable|string',
            'facebook_redirect_uri' => 'nullable|string|max:255',
            'telegram_login_bot_name' => 'nullable|string|max:255|required_with:telegram_login_bot_token,telegram_login_redirect_uri',
            'telegram_login_bot_token' => 'nullable|string|required_with:telegram_login_bot_name,telegram_login_redirect_uri',
            'telegram_login_redirect_uri' => 'nullable|string|max:255|required_with:telegram_login_bot_name,telegram_login_bot_token',
            'global_telegram_bot_username' => 'nullable|string|max:255',
            'global_telegram_bot_token' => 'nullable|string|max:255',
            'whatsapp_webhook_url' => 'nullable|string|max:255',
            'whatsapp_phone_number_id' => 'nullable|string|max:255',
            'whatsapp_access_token' => 'nullable|string',
            'whatsapp_business_id' => 'nullable|string|max:255',
            'whatsapp_api_version' => 'nullable|string|max:20',

            'hero.titulo' => 'nullable|string|max:255',
            'hero.subtitulo' => 'nullable|string|max:500',
            'hero.boton_principal' => 'nullable|string|max:255',
            'hero.boton_secundario' => 'nullable|string|max:255',
            'hero.badge' => 'nullable|string|max:255',

            'caracteristicas' => 'nullable|array',
            'caracteristicas.*.icono' => 'required|string|max:10',
            'caracteristicas.*.titulo' => 'required|string|max:255',
            'caracteristicas.*.descripcion' => 'required|string|max:500',

            'planes' => 'nullable|array',
            'planes.*.nombre' => 'required|string|max:255',
            'planes.*.precio' => 'required|string|max:50',
            'planes.*.periodo' => 'nullable|string|max:50',
            'planes.*.descripcion' => 'nullable|string|max:500',
            'planes.*.popular' => 'boolean',
            'planes.*.caracteristicas' => 'nullable|array',
            'planes.*.caracteristicas.*' => 'string|max:500',

            'cta.titulo' => 'nullable|string|max:255',
            'cta.descripcion' => 'nullable|string|max:500',
            'cta.boton' => 'nullable|string|max:255',

            'nav' => 'nullable|array',
            'nav.app_brand_visible' => 'nullable|boolean',
            'nav.quienes_somos_visible' => 'nullable|boolean',
            'nav.quienes_somos_label' => 'nullable|string|max:255',
            'nav.quienes_somos_subtitle' => 'nullable|string|max:500',
            'nav.quienes_somos_content' => 'nullable|string|max:10000',
            'nav.quienes_somos_image' => 'nullable|string|max:500',
            'nav.feedback_visible' => 'nullable|boolean',
            'nav.feedback_label' => 'nullable|string|max:255',
            'nav.feedback_subtitle' => 'nullable|string|max:500',
            'nav.feedback_content' => 'nullable|string|max:10000',
            'nav.feedback_image' => 'nullable|string|max:500',
            'nav.fundacion_visible' => 'nullable|boolean',
            'nav.fundacion_label' => 'nullable|string|max:255',
            'nav.fundacion_subtitle' => 'nullable|string|max:500',
            'nav.fundacion_content' => 'nullable|string|max:10000',
            'nav.fundacion_image' => 'nullable|string|max:500',

            'nav_extra' => 'nullable|array',
            'nav_extra.*' => 'nullable|string|max:1000',

            'footer' => 'nullable|array',
            'footer.*' => 'nullable|string|max:1000',
        ]);

        // Sanitize Telegram Login credentials: strip the leading @ from the
        // bot username and never persist empty values.
        $telegramLoginFields = [
            'telegram_login_bot_name',
            'telegram_login_bot_token',
            'telegram_login_redirect_uri',
        ];

        foreach ($telegramLoginFields as $telegramLoginField) {
            $value = trim((string) ($validated[$telegramLoginField] ?? ''));

            if ($telegramLoginField === 'telegram_login_bot_name') {
                $value = ltrim($value, '@');
            }

            $validated[$telegramLoginField] = $value !== '' ? $value : null;
        }

        // Handle logo file upload
        if ($request->hasFile('app_logo_file')) {
            if ($configuracion_web->app_logo && str_starts_with($configuracion_web->app_logo, '/storage/branding/')) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $configuracion_web->app_logo));
            }
            $path = $request->file('app_logo_file')->store('branding', 'public');
            $validated['app_logo'] = '/storage/'.$path;
        }

        // Handle favicon file upload
        if ($request->hasFile('app_favicon_file')) {
            if ($configuracion_web->app_favicon && str_starts_with($configuracion_web->app_favicon, '/storage/branding/')) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $configuracion_web->app_favicon));
            }
            $path = $request->file('app_favicon_file')->store('branding', 'public');
            $validated['app_favicon'] = '/storage/'.$path;
        }

        // Extraer campos de configuración de página de inicio
        $hero = $request->input('hero');
        if (is_array($hero)) {
            $validated['hero_titulo'] = $hero['titulo'] ?? null;
            $validated['hero_subtitulo'] = $hero['subtitulo'] ?? null;
            $validated['hero_boton_principal'] = $hero['boton_principal'] ?? null;
            $validated['hero_boton_secundario'] = $hero['boton_secundario'] ?? null;
            $validated['hero_badge'] = $hero['badge'] ?? null;
        }

        $caracteristicas = $request->input('caracteristicas');
        if (is_array($caracteristicas)) {
            $validated['caracteristicas'] = $caracteristicas;
        }

        $planes = $request->input('planes');
        if (is_array($planes)) {
            $validated['planes'] = $planes;
        }

        $cta = $request->input('cta');
        if (is_array($cta)) {
            $validated['cta_titulo'] = $cta['titulo'] ?? null;
            $validated['cta_descripcion'] = $cta['descripcion'] ?? null;
            $validated['cta_boton'] = $cta['boton'] ?? null;
        }

        $nav = $request->input('nav');
        if (is_array($nav)) {
            $validated['nav_app_brand_visible'] = $nav['app_brand_visible'] ?? true;
            $validated['nav_quienes_somos_visible'] = $nav['quienes_somos_visible'] ?? true;
            $validated['nav_quienes_somos_label'] = $nav['quienes_somos_label'] ?? null;
            $validated['nav_quienes_somos_content'] = $nav['quienes_somos_content'] ?? null;
            $validated['nav_quienes_somos_subtitle'] = $nav['quienes_somos_subtitle'] ?? null;
            $validated['nav_quienes_somos_image'] = $nav['quienes_somos_image'] ?? null;

            $validated['nav_feedback_visible'] = $nav['feedback_visible'] ?? true;
            $validated['nav_feedback_label'] = $nav['feedback_label'] ?? null;
            $validated['nav_feedback_content'] = $nav['feedback_content'] ?? null;
            $validated['nav_feedback_subtitle'] = $nav['feedback_subtitle'] ?? null;
            $validated['nav_feedback_image'] = $nav['feedback_image'] ?? null;

            $validated['nav_fundacion_visible'] = $nav['fundacion_visible'] ?? true;
            $validated['nav_fundacion_label'] = $nav['fundacion_label'] ?? null;
            $validated['nav_fundacion_content'] = $nav['fundacion_content'] ?? null;
            $validated['nav_fundacion_subtitle'] = $nav['fundacion_subtitle'] ?? null;
            $validated['nav_fundacion_image'] = $nav['fundacion_image'] ?? null;
        } else {
            $validated['nav_quienes_somos_visible'] = $request->input('nav_quienes_somos_visible', true);
            $validated['nav_quienes_somos_label'] = $request->input('nav_quienes_somos_label');
            $validated['nav_quienes_somos_content'] = $request->input('nav_quienes_somos_content');
            $validated['nav_quienes_somos_subtitle'] = $request->input('nav_quienes_somos_subtitle');

            $validated['nav_feedback_visible'] = $request->input('nav_feedback_visible', true);
            $validated['nav_feedback_label'] = $request->input('nav_feedback_label');
            $validated['nav_feedback_content'] = $request->input('nav_feedback_content');
            $validated['nav_feedback_subtitle'] = $request->input('nav_feedback_subtitle');

            $validated['nav_fundacion_visible'] = $request->input('nav_fundacion_visible', true);
            $validated['nav_fundacion_label'] = $request->input('nav_fundacion_label');
            $validated['nav_fundacion_content'] = $request->input('nav_fundacion_content');
            $validated['nav_fundacion_subtitle'] = $request->input('nav_fundacion_subtitle');
        }

        $navExtra = $request->input('nav_extra');
        if (is_array($navExtra)) {
            $validated['nav_extra'] = $navExtra;
        }

        // Handle navigation pages file uploads (flat and nested keys)
        $navImageFields = [
            'nav_quienes_somos_image_file' => 'nav_quienes_somos_image',
            'nav_feedback_image_file' => 'nav_feedback_image',
            'nav_fundacion_image_file' => 'nav_fundacion_image',
        ];

        foreach ($navImageFields as $fileInput => $dbColumn) {
            $file = null;
            if ($request->hasFile($fileInput)) {
                $file = $request->file($fileInput);
            } elseif ($request->hasFile("nav.{$fileInput}")) {
                $file = $request->file("nav.{$fileInput}");
            }

            if ($file) {
                if ($configuracion_web->$dbColumn && str_starts_with($configuracion_web->$dbColumn, '/storage/branding/')) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $configuracion_web->$dbColumn));
                }
                $path = $file->store('branding', 'public');
                $validated[$dbColumn] = '/storage/'.$path;
            }
        }

        $configuracion_web->update($validated);
        WebSetting::clearCache();

        $channelFields = [
            'global_telegram_bot_username',
            'global_telegram_bot_token',
            'whatsapp_webhook_url',
            'whatsapp_phone_number_id',
            'whatsapp_access_token',
            'whatsapp_business_id',
            'whatsapp_api_version',
        ];

        $hasChannelUpdates = false;
        foreach ($channelFields as $field) {
            if ($request->has($field)) {
                $hasChannelUpdates = true;
                break;
            }
        }

        // Configure the webhook for the global Telegram bot so that
        // /start payloads (deep links) are delivered to Laravel, which is
        // what persists the user's chat_id and the linking token's used_at.
        $globalBotToken = $configuracion_web->global_telegram_bot_token ?? null;
        if ($globalBotToken) {
            $webhookUrl = route('webhooks.telegram');

            try {
                $setWebhook = Http::timeout(10)
                    ->connectTimeout(5)
                    ->withOptions(['verify' => false])
                    ->post("https://api.telegram.org/bot{$globalBotToken}/setWebhook", [
                        'url' => $webhookUrl,
                        'drop_pending_updates' => true,
                    ]);

                $setResult = $setWebhook->json();

                if (! $setWebhook->successful() || ! ($setResult['ok'] ?? false)) {
                    Log::warning('WebSettingController: failed to set global Telegram webhook', [
                        'status' => $setWebhook->status(),
                        'error' => $setResult['description'] ?? 'Unknown error',
                    ]);
                }
            } catch (ConnectionException $e) {
                Log::error('WebSettingController: connection error setting global Telegram webhook', [
                    'error' => $e->getMessage(),
                ]);
            } catch (\Exception $e) {
                Log::error('WebSettingController: error setting global Telegram webhook', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($hasChannelUpdates) {
            event(new ChannelConfigurationUpdated(
                Auth::user()->getOwnerId(),
                Auth::id(),
                'global'
            ));
        }

        return redirect()->route('configuracion-web.index')->with('success', 'Configuración guardada.');
    }

    public function disconnectTelegramLogin(): JsonResponse
    {
        $settings = WebSetting::getSettings();

        $settings->update([
            'telegram_login_bot_name' => null,
            'telegram_login_bot_token' => null,
            'telegram_login_redirect_uri' => null,
        ]);

        WebSetting::clearCache();

        return response()->json([
            'success' => true,
            'message' => 'Bot de Telegram desvinculado correctamente. El inicio de sesión con Telegram ha sido desactivado.',
        ]);
    }

    public function testTelegramLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bot_token' => 'required|string',
            'bot_username' => 'nullable|string|max:255',
        ]);

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withOptions(['verify' => false])
                ->get('https://api.telegram.org/bot'.$validated['bot_token'].'/getMe');

            if ($response->successful() && $response->json('ok')) {
                $botInfo = $response->json('result');

                if (($botInfo['is_bot'] ?? false) !== true) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El Token de Telegram ingresado no es válido o expiró.',
                    ], 422);
                }

                $username = $botInfo['username'] ?? '';

                if (($validated['bot_username'] ?? '') !== '' && ltrim($validated['bot_username'], '@') !== $username) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El username del bot no coincide con el token proporcionado.',
                    ], 422);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Conexión con Telegram exitosa. El bot es válido y está activo.',
                    'bot_username' => $username,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'El Token de Telegram ingresado no es válido o expiró.',
            ], 422);
        } catch (ConnectionException $e) {
            Log::error('Telegram login test connection exception', [
                'error' => $e->getMessage(),
                'type' => 'connection',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo conectar con la API de Telegram. Verifica tu conexión e inténtalo nuevamente.',
            ], 422);
        } catch (\Exception $e) {
            Log::error('Telegram login test exception', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'El Token de Telegram ingresado no es válido o expiró.',
            ], 422);
        }
    }

    public function testSocialConnection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => 'required|string|in:google,facebook',
            'client_id' => 'required|string|max:255',
            'client_secret' => 'required|string',
            'redirect_uri' => 'required|string|max:255',
        ]);

        $provider = $validated['provider'];
        $clientId = $validated['client_id'];
        $clientSecret = $validated['client_secret'];
        $redirectUri = $validated['redirect_uri'];

        try {
            if ($provider === 'google') {
                $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'authorization_code',
                    'code' => 'test_connection_invalid_code',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect_uri' => $redirectUri,
                ]);

                $body = $response->json();

                if ($response->successful() && isset($body['access_token'])) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Conexión exitosa con Google. Las credenciales son válidas.',
                    ]);
                }

                $error = $body['error'] ?? null;

                if ($error === 'invalid_client') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Credenciales de Google inválidas. Verifica el Client ID y Client Secret.',
                    ], 422);
                }

                Log::warning('Google OAuth test failed', ['error' => $error, 'error_description' => $body['error_description'] ?? null]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error al conectar con Google. Verifica tus credenciales.',
                ], 422);
            }

            if ($provider === 'facebook') {
                $appAccessToken = $clientId.'|'.$clientSecret;

                $response = Http::get('https://graph.facebook.com/v19.0/me', [
                    'access_token' => $appAccessToken,
                    'fields' => 'id,name',
                ]);

                $body = $response->json();

                if ($response->successful() && isset($body['id'])) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Conexión exitosa con Facebook. Las credenciales son válidas.',
                    ]);
                }

                Log::warning('Facebook OAuth test failed', ['response' => $body]);

                return response()->json([
                    'success' => false,
                    'message' => 'Credenciales de Facebook inválidas. Verifica tus credenciales.',
                ], 422);
            }

            return response()->json([
                'success' => false,
                'message' => 'Proveedor no soportado.',
            ], 422);
        } catch (\Exception $e) {
            Log::error('Social connection test exception', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error de conexión con el proveedor. Intenta nuevamente.',
            ], 500);
        }
    }

    public function testTelegramConnection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bot_token' => 'required|string',
            'bot_username' => 'required|string',
        ]);

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withOptions(['verify' => false])
                ->get('https://api.telegram.org/bot'.$validated['bot_token'].'/getMe');

            if ($response->successful() && $response->json('ok')) {
                $botInfo = $response->json('result');

                if (($botInfo['is_bot'] ?? false) !== true) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El Token de Telegram ingresado no es válido o expiró.',
                    ], 422);
                }

                if (($botInfo['username'] ?? '') !== ltrim($validated['bot_username'], '@')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El username del bot no coincide con el token proporcionado.',
                    ], 422);
                }

                // Point the global bot webhook to Laravel so /start deep links
                // are forwarded here (this is what closes the linking cycle).
                $webhookResult = Http::timeout(10)
                    ->connectTimeout(5)
                    ->withOptions(['verify' => false])
                    ->post('https://api.telegram.org/bot'.$validated['bot_token'].'/setWebhook', [
                        'url' => route('webhooks.telegram'),
                        'drop_pending_updates' => true,
                    ]);

                $webhookBody = $webhookResult->json();

                return response()->json([
                    'success' => true,
                    'message' => 'Conexión con Telegram exitosa. El bot está activo y el webhook está configurado.',
                    'webhook_configured' => ($webhookResult->successful() && ($webhookBody['ok'] ?? false)),
                    'webhook_url' => route('webhooks.telegram'),
                    'bot_username' => $botInfo['username'],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'El Token de Telegram ingresado no es válido o expiró.',
            ], 422);
        } catch (ConnectionException $e) {
            Log::error('Telegram connection test exception', [
                'error' => $e->getMessage(),
                'type' => 'connection',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo conectar con la API de Telegram. Verifica tu conexión e inténtalo nuevamente.',
            ], 422);
        } catch (\Exception $e) {
            Log::error('Telegram connection test exception', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'El Token de Telegram ingresado no es válido o expiró.',
            ], 422);
        }
    }

    public function testWhatsAppConnection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'webhook_url' => 'required|string|url',
        ]);

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withOptions(['verify' => false])
                ->get($validated['webhook_url']);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Conexión con WhatsApp Webhook exitosa.',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No se pudo conectar con la URL del webhook. Verifica la URL.',
            ], 422);
        } catch (ConnectionException $e) {
            Log::error('WhatsApp connection test exception', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo conectar con la URL del webhook. Verifica la URL e inténtalo nuevamente.',
            ], 422);
        } catch (\Exception $e) {
            Log::error('WhatsApp connection test exception', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'El webhook de WhatsApp ingresado no es válido o no está disponible.',
            ], 422);
        }
    }

    public function setTelegramWebhook(Request $request): JsonResponse
    {
        $settings = WebSetting::getSettings();
        $botToken = $request->input('bot_token') ?: $settings->global_telegram_bot_token;

        if (! $botToken) {
            return response()->json([
                'success' => false,
                'message' => 'No hay un token de bot de Telegram configurado. Completa el formulario y guárdalo o envía el token.',
            ], 422);
        }

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withOptions(['verify' => false])
                ->post('https://api.telegram.org/bot'.$botToken.'/setWebhook', [
                    'url' => route('webhooks.telegram'),
                    'drop_pending_updates' => true,
                ]);

            $body = $response->json();

            if ($response->successful() && ($body['ok'] ?? false)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Webhook registrado exitosamente con Telegram.',
                    'webhook_url' => route('webhooks.telegram'),
                    'webhook_configured' => true,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $body['description'] ?? 'Telegram rechazó la configuración del webhook. Verifica el token.',
            ], 422);
        } catch (ConnectionException $e) {
            Log::error('Telegram setWebhook connection exception', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo conectar con la API de Telegram. Verifica tu conexión e inténtalo nuevamente.',
            ], 422);
        } catch (\Exception $e) {
            Log::error('Telegram setWebhook exception', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el webhook de Telegram.',
            ], 422);
        }
    }

    public function setWhatsAppWebhook(Request $request): JsonResponse
    {
        $settings = WebSetting::getSettings();

        $accessToken = $request->input('access_token') ?: $settings->whatsapp_access_token;
        $businessId = $request->input('business_id') ?: $settings->whatsapp_business_id;
        $apiVersion = $request->input('api_version') ?: $settings->whatsapp_api_version ?: 'v22.0';
        $webhookUrl = $request->input('webhook_url') ?: $settings->whatsapp_webhook_url;

        if (! $accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'No hay un Access Token de WhatsApp configurado. Completa el formulario y guárdalo o envía el token.',
            ], 422);
        }

        if (! $businessId) {
            return response()->json([
                'success' => false,
                'message' => 'No hay un Business ID de WhatsApp configurado. Completa el formulario y guárdalo o envía el ID.',
            ], 422);
        }

        if (! $webhookUrl) {
            return response()->json([
                'success' => false,
                'message' => 'No hay una URL de webhook de WhatsApp configurada. Completa el formulario y guárdalo o envía la URL.',
            ], 422);
        }

        try {
            $webhookCheck = Http::timeout(10)
                ->connectTimeout(5)
                ->withOptions(['verify' => false])
                ->get($webhookUrl);

            if (! $webhookCheck->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo conectar con la URL del webhook. Verifica la URL.',
                ], 422);
            }
        } catch (ConnectionException $e) {
            Log::error('WhatsApp setWebhook connection exception (url)', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo conectar con la URL del webhook. Verifica la URL e inténtalo nuevamente.',
            ], 422);
        } catch (\Exception $e) {
            Log::error('WhatsApp setWebhook exception (url)', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'El webhook de WhatsApp ingresado no es válido o no está disponible.',
            ], 422);
        }

        try {
            $response = Http::timeout(15)
                ->connectTimeout(5)
                ->withToken($accessToken)
                ->post("https://graph.facebook.com/{$apiVersion}/{$businessId}/subscribed_apps");

            $result = $response->json();

            if ($response->successful() && ($result['success'] ?? false)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Webhook registrado exitosamente con WhatsApp.',
                    'webhook_url' => $webhookUrl,
                ]);
            }

            $errorMsg = $result['error']['message'] ?? 'La API de WhatsApp rechazó la suscripción del webhook.';

            return response()->json([
                'success' => false,
                'message' => "Error: {$errorMsg}",
            ], 422);
        } catch (ConnectionException $e) {
            Log::error('WhatsApp setWebhook connection exception', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo conectar con la API de WhatsApp. Verifica tu conexión e inténtalo nuevamente.',
            ], 422);
        } catch (\Exception $e) {
            Log::error('WhatsApp setWebhook exception', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el webhook de WhatsApp.',
            ], 422);
        }
    }
}
