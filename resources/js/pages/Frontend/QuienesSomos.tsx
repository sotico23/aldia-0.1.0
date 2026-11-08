import { Head, Link } from '@inertiajs/react';
import DOMPurify from 'dompurify';
import { Target, Users, Zap } from 'lucide-react';

interface Props {
    title: string;
    subtitle?: string;
    content: string;
    image?: string;
    nav_extra?: Record<string, string>;
    web_settings: {
        app_name: string;
        app_logo: string | null;
    };
    nav?: any;
}

export default function QuienesSomos({
    title,
    subtitle,
    content,
    image,
    nav_extra,
    web_settings,
    nav,
}: Props) {
    const appName = web_settings?.app_name || 'GrowERP';
    const appLogo = web_settings?.app_logo || null;
    const logoLetra = appName.charAt(0).toUpperCase();

    return (
        <div className="flex min-h-screen flex-col bg-slate-50 font-sans">
            <Head title={title} />

            {/* Header */}
            <header className="sticky top-0 z-50 border-b border-gray-200 bg-white/80 backdrop-blur-md">
                <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4">
                    {(nav?.app_brand_visible ?? true) ? (
                        <Link href="/" className="flex items-center gap-2">
                            {appLogo ? (
                                <img
                                    src={appLogo}
                                    alt={appName}
                                    className="h-10 w-10 rounded-lg object-contain"
                                />
                            ) : (
                                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-2xl font-black text-white">
                                    {logoLetra}
                                </div>
                            )}
                            <span className="text-xl font-bold text-gray-900">
                                {appName}
                            </span>
                        </Link>
                    ) : (
                        <Link href="/" className="flex items-center">
                            <span className="sr-only text-xl font-bold text-gray-900">
                                {appName}
                            </span>
                        </Link>
                    )}
                    <nav className="flex items-center gap-6">
                        <div className="mr-4 hidden items-center gap-6 md:flex">
                            <Link
                                href="/quienes-somos"
                                className="text-sm font-semibold text-amber-600 transition-colors"
                            >
                                {nav?.quienes_somos || 'Quiénes Somos'}
                            </Link>
                            {(nav?.feedback_visible ?? true) && (
                                <Link
                                    href="/feedback"
                                    className="text-sm font-medium text-gray-500 transition-colors hover:text-amber-600"
                                >
                                    {nav?.feedback || 'Feedback'}
                                </Link>
                            )}
                            {(nav?.fundacion_visible ?? true) && (
                                <Link
                                    href="/fundacion"
                                    className="text-sm font-medium text-gray-500 transition-colors hover:text-amber-600"
                                >
                                    {nav?.fundacion || 'Nuestra Fundación'}
                                </Link>
                            )}
                        </div>
                        <div className="flex items-center gap-3">
                            <Link
                                href="/login"
                                className="px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900"
                            >
                                Iniciar sesión
                            </Link>
                            <Link
                                href="/register"
                                className="rounded-full bg-gray-900 px-5 py-2 text-sm font-medium text-white transition-all hover:bg-gray-800 hover:shadow-lg"
                            >
                                Regístrate gratis
                            </Link>
                        </div>
                    </nav>
                </div>
            </header>

            {/* Hero Section */}
            <section className="relative overflow-hidden bg-white pt-16 pb-32">
                <div className="absolute inset-x-0 bottom-0 h-1/2 bg-slate-50" />
                <div className="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="mx-auto mb-16 max-w-3xl text-center">
                        <h1 className="mb-6 text-4xl font-extrabold tracking-tight text-gray-900 sm:text-5xl md:text-6xl">
                            Sobre{' '}
                            <span className="bg-gradient-to-r from-amber-500 to-orange-600 bg-clip-text text-amber-500 text-transparent">
                                Nosotros
                            </span>
                        </h1>
                        <p className="text-xl leading-relaxed text-gray-500">
                            {subtitle ||
                                title ||
                                'Conoce nuestra historia y propósito.'}
                        </p>
                    </div>

                    <div className="group relative mx-auto max-w-5xl overflow-hidden rounded-3xl shadow-2xl shadow-amber-900/10">
                        <div className="absolute inset-0 z-10 bg-gradient-to-t from-gray-900/60 to-transparent" />
                        <img
                            src={image || '/images/about_us_team.png'}
                            alt="Nuestro equipo"
                            className="h-[500px] w-full object-cover transition-transform duration-700 group-hover:scale-105"
                        />
                        <div className="absolute right-0 bottom-0 left-0 z-20 translate-y-4 transform p-8 text-white transition-transform duration-300 group-hover:translate-y-0">
                            <h3 className="mb-2 text-3xl font-bold">
                                Un equipo comprometido con tu éxito
                            </h3>
                            <p className="text-gray-200">
                                Innovación y dedicación en cada proyecto
                                interactivo.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            {/* Content & Mission */}
            <section className="flex-grow bg-slate-50 py-20">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 gap-12 lg:grid-cols-3">
                        <div className="prose prose-lg prose-amber text-gray-600 lg:col-span-2">
                            <h2 className="mb-6 border-b border-gray-200 pb-4 text-3xl font-bold text-gray-900">
                                {nav_extra?.quienes_somos_vision_title ||
                                    'Nuestra Esencia'}
                            </h2>
                            <div
                                className="prose prose-lg prose-amber max-w-none leading-relaxed"
                                dangerouslySetInnerHTML={{
                                    __html: DOMPurify.sanitize(
                                        content ||
                                            'Estamos trabajando arduamente en construir nuestra historia.',
                                    ),
                                }}
                            />
                        </div>

                        <div className="space-y-8">
                            <div className="rounded-2xl border border-gray-100 bg-white p-8 shadow-sm transition-shadow hover:shadow-md">
                                <div className="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                                    <Target className="h-6 w-6" />
                                </div>
                                <h3 className="mb-3 text-xl font-bold text-gray-900">
                                    Nuestra Misión
                                </h3>
                                <p className="text-gray-600">
                                    {nav_extra?.quienes_somos_vision_content ||
                                        'Empoderar a empresas e individuos con herramientas de gestión de primer nivel, democratizando el acceso a tecnología avanzada.'}
                                </p>
                            </div>

                            <div className="rounded-2xl border border-gray-100 bg-white p-8 shadow-sm transition-shadow hover:shadow-md">
                                <div className="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                                    <Zap className="h-6 w-6" />
                                </div>
                                <h3 className="mb-3 text-xl font-bold text-gray-900">
                                    Innovación Constante
                                </h3>
                                <p className="text-gray-600">
                                    Buscamos siempre la optimización de procesos
                                    y el desarrollo de nuevas formas de
                                    facilitar el trabajo diario de nuestros
                                    usuarios.
                                </p>
                            </div>

                            <div className="rounded-2xl border border-gray-100 bg-white p-8 shadow-sm transition-shadow hover:shadow-md">
                                <div className="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                                    <Users className="h-6 w-6" />
                                </div>
                                <h3 className="mb-3 text-xl font-bold text-gray-900">
                                    Comunidad Primero
                                </h3>
                                <p className="text-gray-600">
                                    Nuestros usuarios son el núcleo de lo que
                                    hacemos. Escuchamos y evolucionamos junto
                                    con sus necesidades reales.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* Footer */}
            <footer className="border-t border-gray-200 bg-white py-12">
                <div className="mx-auto max-w-7xl px-4 text-center">
                    <img
                        src={appLogo || ''}
                        className="mx-auto mb-6 h-12 opacity-60 grayscale"
                        alt="Logo"
                        style={{ display: appLogo ? 'block' : 'none' }}
                    />
                    <p className="text-sm font-medium text-gray-500">
                        © 2026 {appName}. Construido para el futuro.
                    </p>
                </div>
            </footer>
        </div>
    );
}
