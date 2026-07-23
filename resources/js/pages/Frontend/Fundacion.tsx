import { Head, Link } from '@inertiajs/react';
import DOMPurify from 'dompurify';
import { HeartHandshake, GraduationCap, Globe2, Sparkles } from 'lucide-react';

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

export default function Fundacion({ title, subtitle, content, image, nav_extra, web_settings, nav }: Props) {
    const appName = web_settings?.app_name || 'GrowERP';
    const appLogo = web_settings?.app_logo || null;

    return (
        <div className="min-h-screen bg-stone-50 font-sans flex flex-col">
            <Head title={title} />

            {/* Header */}
            <header className="sticky top-0 z-50 bg-white/90 backdrop-blur-md border-b border-stone-200">
                <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4">
                    {(nav?.app_brand_visible ?? true) ? (
                        <Link href="/" className="flex items-center gap-2">
                            {appLogo ? (
                                <img src={appLogo} alt={appName} className="h-10 w-10 rounded-lg object-contain" />
                            ) : (
                                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-rose-500 to-orange-500 text-2xl font-black text-white">
                                    {appName.charAt(0)}
                                </div>
                            )}
                            <span className="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-rose-600 to-orange-500">
                                Fundación {appName}
                            </span>
                        </Link>
                    ) : (
                        <Link href="/" className="flex items-center">
                            <span className="text-xl font-bold text-transparent sr-only">{appName}</span>
                        </Link>
                    )}
                    <nav className="flex items-center gap-6">
                        <div className="hidden md:flex items-center gap-6 mr-4">
                            {(nav?.quienes_somos_visible ?? true) && (
                                <Link href="/quienes-somos" className="text-sm font-medium text-stone-500 hover:text-rose-600 transition-colors">
                                    {nav?.quienes_somos || 'Quiénes Somos'}
                                </Link>
                            )}
                            {(nav?.feedback_visible ?? true) && (
                                <Link href="/feedback" className="text-sm font-medium text-stone-500 hover:text-rose-600 transition-colors">
                                    {nav?.feedback || 'Feedback'}
                                </Link>
                            )}
                            <Link href="/fundacion" className="text-sm font-semibold text-rose-600 transition-colors">
                                {nav?.fundacion || 'Nuestra Fundación'}
                            </Link>
                        </div>
                        <div className="flex items-center gap-3">
                            <Link href="/login" className="text-sm font-medium text-stone-600 hover:text-stone-900 px-3 py-2">
                                Iniciar sesión
                            </Link>
                        </div>
                    </nav>
                </div>
            </header>

            {/* Hero Section */}
            <section className="relative pt-20 pb-32 overflow-hidden">
                <div className="absolute inset-0 bg-rose-900/5 z-0" />
                
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
                    <div className="lg:grid lg:grid-cols-12 lg:gap-16 items-center">
                        <div className="lg:col-span-5 mb-12 lg:mb-0 text-center lg:text-left">
                            <div className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-rose-100 text-rose-700 font-semibold text-sm mb-6 uppercase tracking-wider">
                                <Sparkles className="w-4 h-4" /> {nav_extra?.fundacion_badge || 'Impacto Social'}
                            </div>
                            <h1 className="text-5xl font-extrabold text-stone-900 tracking-tight mb-6 leading-tight">
                                {title || <><span className="text-transparent bg-clip-text bg-gradient-to-r from-rose-500 to-orange-500">educación y ayuda</span></>}
                            </h1>
                            <p className="text-xl text-stone-600 mb-8 leading-relaxed">
                                {subtitle || 'Brindamos becas y asistencia a comunidades vulnerables para crear un futuro más brillante.'}
                            </p>
                            <div className="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
                                <button className="px-8 py-4 rounded-full bg-rose-600 text-white font-bold hover:bg-rose-700 transition shadow-lg shadow-rose-600/30">
                                    {nav_extra?.fundacion_btn_primary || 'Donar ahora'}
                                </button>
                                <button className="px-8 py-4 rounded-full bg-white border-2 border-stone-200 text-stone-700 font-bold hover:border-rose-500 hover:text-rose-600 transition">
                                    {nav_extra?.fundacion_btn_secondary || 'Conoce las Becas'}
                                </button>
                            </div>
                        </div>

                        <div className="lg:col-span-7 relative">
                            <div className="absolute -inset-4 bg-gradient-to-r from-rose-500/20 to-orange-500/20 blur-2xl rounded-[3rem] z-0" />
                            <img 
                                src={image || "/images/ngo_foundation.png"} 
                                alt="Voluntaria entregando beca" 
                                className="relative z-10 w-full rounded-[2.5rem] shadow-2xl object-cover h-[500px]"
                            />
                            
                            {/* Floating Card */}
                            <div className="absolute -bottom-8 -left-8 bg-white p-6 rounded-3xl shadow-xl z-20 max-w-xs border border-stone-100 hidden sm:block">
                                <div className="flex items-center gap-4 mb-3">
                                    <div className="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center text-orange-600">
                                        <GraduationCap className="w-6 h-6" />
                                    </div>
                                    <div>
                                        <p className="text-sm text-stone-500 font-medium">
                                            {nav_extra?.fundacion_stats_desc || 'Becas Otorgadas'}
                                        </p>
                                        <p className="text-2xl font-bold text-stone-900">
                                            {nav_extra?.fundacion_stats_num || '+500'}
                                        </p>
                                    </div>
                                </div>
                                <p className="text-sm text-stone-600">
                                    {nav_extra?.fundacion_stats_sub || 'Jóvenes estudiando en todo el país gracias a nuestro programa.'}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* Dynamic Content Section */}
            <section className="py-24 bg-white flex-grow">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 text-center">
                    <HeartHandshake className="w-16 h-16 mx-auto text-rose-500 mb-8" />
                    <h2 className="text-3xl font-bold text-stone-900 mb-8">
                        {nav_extra?.fundacion_purpose_title || 'Nuestro Propósito'}
                    </h2>
                    <div
                        className="prose prose-rose prose-lg mx-auto text-stone-600 leading-relaxed text-left"
                        dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(content || 'En este espacio presentaremos en detalle los objetivos de la ONG, nuestros programas de ayuda y cómo puedes ser parte del cambio.') }}
                    />
                </div>
            </section>

            {/* Footer Minimal */}
            <footer className="bg-stone-900 text-stone-400 py-12">
                <div className="max-w-7xl mx-auto px-4 text-center">
                    <Globe2 className="w-8 h-8 mx-auto mb-4 text-stone-600" />
                    <p className="font-semibold text-stone-300">Fundación {appName}</p>
                    <p className="text-sm mt-2">© 2026. Ayudando a construir un mundo mejor.</p>
                </div>
            </footer>
        </div>
    );
}
