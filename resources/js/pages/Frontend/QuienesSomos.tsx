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

export default function QuienesSomos({ title, subtitle, content, image, nav_extra, web_settings, nav }: Props) {
    const appName = web_settings?.app_name || 'GrowERP';
    const appLogo = web_settings?.app_logo || null;
    const logoLetra = appName.charAt(0).toUpperCase();

    return (
        <div className="min-h-screen bg-slate-50 flex flex-col font-sans">
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
                            <span className="text-xl font-bold text-gray-900 sr-only">{appName}</span>
                        </Link>
                    )}
                    <nav className="flex items-center gap-6">
                        <div className="hidden md:flex items-center gap-6 mr-4">
                            <Link href="/quienes-somos" className="text-sm font-semibold text-amber-600 transition-colors">
                                {nav?.quienes_somos || 'Quiénes Somos'}
                            </Link>
                            {(nav?.feedback_visible ?? true) && (
                                <Link href="/feedback" className="text-sm font-medium text-gray-500 hover:text-amber-600 transition-colors">
                                    {nav?.feedback || 'Feedback'}
                                </Link>
                            )}
                            {(nav?.fundacion_visible ?? true) && (
                                <Link href="/fundacion" className="text-sm font-medium text-gray-500 hover:text-amber-600 transition-colors">
                                    {nav?.fundacion || 'Nuestra Fundación'}
                                </Link>
                            )}
                        </div>
                        <div className="flex items-center gap-3">
                            <Link
                                href="/login"
                                className="text-sm font-medium text-gray-600 hover:text-gray-900 px-3 py-2"
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
                    <div className="text-center max-w-3xl mx-auto mb-16">
                        <h1 className="text-4xl font-extrabold tracking-tight text-gray-900 sm:text-5xl md:text-6xl mb-6">
                            Sobre <span className="text-amber-500 text-transparent bg-clip-text bg-gradient-to-r from-amber-500 to-orange-600">Nosotros</span>
                        </h1>
                        <p className="text-xl text-gray-500 leading-relaxed">
                            {subtitle || title || 'Conoce nuestra historia y propósito.'}
                        </p>
                    </div>

                    <div className="relative mx-auto max-w-5xl rounded-3xl overflow-hidden shadow-2xl shadow-amber-900/10 group">
                        <div className="absolute inset-0 bg-gradient-to-t from-gray-900/60 to-transparent z-10" />
                        <img 
                            src={image || "/images/about_us_team.png"}
                            alt="Nuestro equipo" 
                            className="w-full h-[500px] object-cover transition-transform duration-700 group-hover:scale-105"
                        />
                        <div className="absolute bottom-0 left-0 right-0 p-8 z-20 text-white transform translate-y-4 group-hover:translate-y-0 transition-transform duration-300">
                            <h3 className="text-3xl font-bold mb-2">Un equipo comprometido con tu éxito</h3>
                            <p className="text-gray-200">Innovación y dedicación en cada proyecto interactivo.</p>
                        </div>
                    </div>
                </div>
            </section>

            {/* Content & Mission */}
            <section className="py-20 bg-slate-50 flex-grow">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-12">
                        <div className="lg:col-span-2 prose prose-lg prose-amber text-gray-600">
                            <h2 className="text-3xl font-bold text-gray-900 mb-6 border-b border-gray-200 pb-4">
                                {nav_extra?.quienes_somos_vision_title || 'Nuestra Esencia'}
                            </h2>
                            <div
                                className="prose prose-lg prose-amber max-w-none leading-relaxed"
                                dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(content || 'Estamos trabajando arduamente en construir nuestra historia.') }}
                            />
                        </div>

                        <div className="space-y-8">
                            <div className="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                                <div className="h-12 w-12 bg-amber-100 text-amber-600 rounded-xl flex items-center justify-center mb-6">
                                    <Target className="h-6 w-6" />
                                </div>
                                <h3 className="text-xl font-bold text-gray-900 mb-3">Nuestra Misión</h3>
                                <p className="text-gray-600">
                                    {nav_extra?.quienes_somos_vision_content || 'Empoderar a empresas e individuos con herramientas de gestión de primer nivel, democratizando el acceso a tecnología avanzada.'}
                                </p>
                            </div>

                            <div className="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                                <div className="h-12 w-12 bg-emerald-100 text-emerald-600 rounded-xl flex items-center justify-center mb-6">
                                    <Zap className="h-6 w-6" />
                                </div>
                                <h3 className="text-xl font-bold text-gray-900 mb-3">Innovación Constante</h3>
                                <p className="text-gray-600">Buscamos siempre la optimización de procesos y el desarrollo de nuevas formas de facilitar el trabajo diario de nuestros usuarios.</p>
                            </div>

                            <div className="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                                <div className="h-12 w-12 bg-indigo-100 text-indigo-600 rounded-xl flex items-center justify-center mb-6">
                                    <Users className="h-6 w-6" />
                                </div>
                                <h3 className="text-xl font-bold text-gray-900 mb-3">Comunidad Primero</h3>
                                <p className="text-gray-600">Nuestros usuarios son el núcleo de lo que hacemos. Escuchamos y evolucionamos junto con sus necesidades reales.</p>
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
                        className="h-12 mx-auto mb-6 grayscale opacity-60" 
                        alt="Logo"
                        style={{ display: appLogo ? 'block' : 'none' }}
                    />
                    <p className="text-sm text-gray-500 font-medium">
                        © 2026 {appName}. Construido para el futuro.
                    </p>
                </div>
            </footer>
        </div>
    );
}
