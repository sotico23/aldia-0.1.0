import { Head } from '@inertiajs/react';
import DOMPurify from 'dompurify';
import { HeartHandshake, GraduationCap, Globe2, Sparkles } from 'lucide-react';
import { PublicHeader } from '@/components/public-header';

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

export default function Fundacion({
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

    return (
        <div className="flex min-h-screen flex-col bg-stone-50 font-sans">
            <Head title={title} />

            {/* Header */}
            <PublicHeader
                appName={appName}
                appLogo={appLogo}
                nav={nav}
                activePage="fundacion"
                tone="rose"
                brandPrefix="Fundación"
            />

            {/* Hero Section */}
            <section className="relative overflow-hidden pt-10 pb-16 sm:pt-20 sm:pb-32">
                <div className="absolute inset-0 z-0 bg-rose-900/5" />

                <div className="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="items-center lg:grid lg:grid-cols-12 lg:gap-16">
                        <div className="mb-12 text-center lg:col-span-5 lg:mb-0 lg:text-left">
                            <div className="mb-6 inline-flex items-center gap-2 rounded-full bg-rose-100 px-4 py-2 text-sm font-semibold tracking-wider text-rose-700 uppercase">
                                <Sparkles className="h-4 w-4" />{' '}
                                {nav_extra?.fundacion_badge || 'Impacto Social'}
                            </div>
                            <h1 className="mb-6 text-3xl leading-tight font-extrabold tracking-tight break-words text-stone-900 sm:text-5xl">
                                {title || (
                                    <>
                                        <span className="bg-gradient-to-r from-rose-500 to-orange-500 bg-clip-text text-transparent">
                                            educación y ayuda
                                        </span>
                                    </>
                                )}
                            </h1>
                            <p className="mb-8 text-base leading-relaxed text-stone-600 sm:text-xl">
                                {subtitle ||
                                    'Brindamos becas y asistencia a comunidades vulnerables para crear un futuro más brillante.'}
                            </p>
                            <div className="flex flex-col justify-center gap-4 sm:flex-row lg:justify-start">
                                <button className="rounded-full bg-rose-600 px-8 py-4 font-bold text-white shadow-lg shadow-rose-600/30 transition hover:bg-rose-700">
                                    {nav_extra?.fundacion_btn_primary ||
                                        'Donar ahora'}
                                </button>
                                <button className="rounded-full border-2 border-stone-200 bg-white px-8 py-4 font-bold text-stone-700 transition hover:border-rose-500 hover:text-rose-600">
                                    {nav_extra?.fundacion_btn_secondary ||
                                        'Conoce las Becas'}
                                </button>
                            </div>
                        </div>

                        <div className="relative lg:col-span-7">
                            <div className="absolute -inset-4 z-0 rounded-[3rem] bg-gradient-to-r from-rose-500/20 to-orange-500/20 blur-2xl" />
                            <img
                                src={image || '/images/ngo_foundation.png'}
                                alt="Voluntaria entregando beca"
                                className="relative z-10 h-72 w-full rounded-3xl object-cover shadow-2xl sm:h-[500px] sm:rounded-[2.5rem]"
                            />

                            {/* Floating Card */}
                            <div className="absolute -bottom-8 -left-8 z-20 hidden max-w-xs rounded-3xl border border-stone-100 bg-white p-6 shadow-xl sm:block">
                                <div className="mb-3 flex items-center gap-4">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-full bg-orange-100 text-orange-600">
                                        <GraduationCap className="h-6 w-6" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-stone-500">
                                            {nav_extra?.fundacion_stats_desc ||
                                                'Becas Otorgadas'}
                                        </p>
                                        <p className="text-2xl font-bold text-stone-900">
                                            {nav_extra?.fundacion_stats_num ||
                                                '+500'}
                                        </p>
                                    </div>
                                </div>
                                <p className="text-sm text-stone-600">
                                    {nav_extra?.fundacion_stats_sub ||
                                        'Jóvenes estudiando en todo el país gracias a nuestro programa.'}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* Dynamic Content Section */}
            <section className="flex-grow bg-white py-24">
                <div className="mx-auto max-w-4xl px-4 text-center sm:px-6">
                    <HeartHandshake className="mx-auto mb-8 h-16 w-16 text-rose-500" />
                    <h2 className="mb-8 text-3xl font-bold text-stone-900">
                        {nav_extra?.fundacion_purpose_title ||
                            'Nuestro Propósito'}
                    </h2>
                    <div
                        className="prose prose-rose prose-lg mx-auto overflow-x-auto text-left leading-relaxed break-words text-stone-600"
                        dangerouslySetInnerHTML={{
                            __html: DOMPurify.sanitize(
                                content ||
                                    'En este espacio presentaremos en detalle los objetivos de la ONG, nuestros programas de ayuda y cómo puedes ser parte del cambio.',
                            ),
                        }}
                    />
                </div>
            </section>

            {/* Footer Minimal */}
            <footer className="bg-stone-900 py-12 text-stone-400">
                <div className="mx-auto max-w-7xl px-4 text-center">
                    <Globe2 className="mx-auto mb-4 h-8 w-8 text-stone-600" />
                    <p className="font-semibold text-stone-300">
                        Fundación {appName}
                    </p>
                    <p className="mt-2 text-sm">
                        © 2026. Ayudando a construir un mundo mejor.
                    </p>
                </div>
            </footer>
        </div>
    );
}
