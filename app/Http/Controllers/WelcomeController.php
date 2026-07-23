<?php

namespace App\Http\Controllers;

use App\Models\Promocion;
use App\Models\WebSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class WelcomeController extends Controller
{
    public function index()
    {
        $settings = WebSetting::getSettings();

        $promociones = Promocion::query()
            ->active()
            ->vigente()
            ->orderBy('created_at', 'desc')
            ->take(6)
            ->get()
            ->map(fn ($p) => [
                'nombre' => $p->nombre,
                'tipo' => $p->tipo,
                'valor' => $p->valor,
                'descripcion' => $p->descripcion,
                'categoria' => $p->categoria?->nombre,
            ]);

        $config = [
            'canRegister' => Features::enabled(Features::registration()),
            'web_settings' => [
                'app_name' => $settings->app_name,
                'app_logo' => $settings->app_logo,
                'app_title' => $settings->app_title,
                'app_description' => $settings->app_description,
                'app_keywords' => $settings->app_keywords,
            ],
            'nav' => [
                'app_brand_visible' => $settings->nav_app_brand_visible ?? true,
                'quienes_somos_visible' => $settings->nav_quienes_somos_visible ?? true,
                'quienes_somos' => $settings->nav_quienes_somos_label ?? 'Quiénes Somos',
                'feedback_visible' => $settings->nav_feedback_visible ?? true,
                'feedback' => $settings->nav_feedback_label ?? 'Feedback',
                'fundacion_visible' => $settings->nav_fundacion_visible ?? true,
                'fundacion' => $settings->nav_fundacion_label ?? 'Nuestra Fundación',
            ],
            'hero' => [
                'titulo' => $settings->hero_titulo ?? 'Gestiona tu negocio como un experto',
                'subtitulo' => $settings->hero_subtitulo ?? 'La plataforma todo-en-uno que necesitas para hacer crecer tu empresa. Desde inventario hasta facturación, todo en un solo lugar.',
                'boton_principal' => $settings->hero_boton_principal ?? 'Comenzar gratis',
                'boton_secundario' => $settings->hero_boton_secundario ?? 'Ver demo',
                'badge' => $settings->hero_badge ?? '¡Nuevo! IA integrada para predicción de inventario',
            ],
            'promociones' => $promociones,
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
        ];

        return Inertia::render('WelcomeNew', $config);
    }

    public function quienesSomos(): RedirectResponse|Response
    {
        $settings = WebSetting::getSettings();

        if (! ($settings->nav_quienes_somos_visible ?? true)) {
            return redirect()->route('home');
        }

        $nav = $this->buildNav($settings);

        return Inertia::render('Frontend/QuienesSomos', [
            'title' => $settings->nav_quienes_somos_label ?? 'Quiénes Somos',
            'subtitle' => $settings->nav_quienes_somos_subtitle ?? 'Sobre Nosotros',
            'content' => $settings->nav_quienes_somos_content ?? 'Contenido en construcción.',
            'image' => $settings->nav_quienes_somos_image ?? '/images/about_us_team.png',
            'nav_extra' => $settings->nav_extra ?? [],
            'web_settings' => [
                'app_name' => $settings->app_name,
                'app_logo' => $settings->app_logo,
            ],
            'nav' => $nav,
        ]);
    }

    public function feedback(): RedirectResponse|Response
    {
        $settings = WebSetting::getSettings();

        if (! ($settings->nav_feedback_visible ?? true)) {
            return redirect()->route('home');
        }

        $nav = $this->buildNav($settings);

        return Inertia::render('Frontend/Feedback', [
            'title' => $settings->nav_feedback_label ?? 'Feedback',
            'subtitle' => $settings->nav_feedback_subtitle ?? 'Tu opinión moldea el futuro',
            'content' => $settings->nav_feedback_content ?? 'Contenido en construcción.',
            'image' => $settings->nav_feedback_image ?? '/images/feedback_hero.png',
            'nav_extra' => $settings->nav_extra ?? [],
            'web_settings' => [
                'app_name' => $settings->app_name,
                'app_logo' => $settings->app_logo,
            ],
            'nav' => $nav,
        ]);
    }

    public function fundacion(): RedirectResponse|Response
    {
        $settings = WebSetting::getSettings();

        if (! ($settings->nav_fundacion_visible ?? true)) {
            return redirect()->route('home');
        }

        $nav = $this->buildNav($settings);

        return Inertia::render('Frontend/Fundacion', [
            'title' => $settings->nav_fundacion_label ?? 'Nuestra Fundación',
            'subtitle' => $settings->nav_fundacion_subtitle ?? 'Transformando vidas mediante la educación y ayuda',
            'content' => $settings->nav_fundacion_content ?? 'Contenido en construcción.',
            'image' => $settings->nav_fundacion_image ?? '/images/ngo_foundation.png',
            'nav_extra' => $settings->nav_extra ?? [],
            'web_settings' => [
                'app_name' => $settings->app_name,
                'app_logo' => $settings->app_logo,
            ],
            'nav' => $nav,
        ]);
    }

    private function buildNav(WebSetting $settings): array
    {
        return [
            'app_brand_visible' => $settings->nav_app_brand_visible ?? true,
            'quienes_somos_visible' => $settings->nav_quienes_somos_visible ?? true,
            'quienes_somos' => $settings->nav_quienes_somos_label ?? 'Quiénes Somos',
            'feedback_visible' => $settings->nav_feedback_visible ?? true,
            'feedback' => $settings->nav_feedback_label ?? 'Feedback',
            'fundacion_visible' => $settings->nav_fundacion_visible ?? true,
            'fundacion' => $settings->nav_fundacion_label ?? 'Nuestra Fundación',
        ];
    }
}
