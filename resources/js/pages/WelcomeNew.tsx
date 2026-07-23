import { Head, Link } from '@inertiajs/react';
import { useCountry } from '@/hooks/use-country';

interface Caracteristica {
    icono: string;
    titulo: string;
    descripcion: string;
}

interface Plan {
    nombre: string;
    precio: string;
    periodo: string;
    descripcion: string;
    popular: boolean;
    caracteristicas: string[];
}

interface WelcomeProps {
    canRegister: boolean;
    web_settings?: {
        app_name: string;
        app_logo: string | null;
        app_title: string;
        app_description: string | null;
        app_keywords: string | null;
    };
    hero: {
        titulo: string;
        subtitulo: string;
        boton_principal: string;
        boton_secundario: string;
        badge: string;
    };
    promociones?: {
        nombre: string;
        tipo: string;
        valor: number;
        descripcion: string | null;
        categoria: string | null;
    }[];
    caracteristicas: Caracteristica[];
    planes: Plan[];
    cta: {
        titulo: string;
        descripcion: string;
        boton: string;
    };
    nav?: {
        quienes_somos: string;
        feedback: string;
        fundacion: string;
        app_brand_visible?: boolean;
        quienes_somos_visible?: boolean;
        feedback_visible?: boolean;
        fundacion_visible?: boolean;
    };
}

const getPlanStyles = (plan: Plan, index: number) => {
    const styles = [
        {
            color: 'bg-gray-100',
            borderColor: 'border-gray-200',
            textoPrecio: 'text-gray-700',
            boton: 'bg-gray-600 hover:bg-gray-700',
        },
        {
            color: 'bg-blue-50',
            borderColor: 'border-blue-200',
            textoPrecio: 'text-blue-600',
            boton: 'bg-blue-600 hover:bg-blue-700',
        },
        {
            color: 'bg-amber-50',
            borderColor: 'border-amber-300',
            textoPrecio: 'text-amber-600',
            boton: 'bg-amber-500 hover:bg-amber-600',
        },
        {
            color: 'bg-purple-50',
            borderColor: 'border-purple-300',
            textoPrecio: 'text-purple-600',
            boton: 'bg-purple-600 hover:bg-purple-700',
        },
        {
            color: 'bg-emerald-50',
            borderColor: 'border-emerald-300',
            textoPrecio: 'text-emerald-600',
            boton: 'bg-emerald-600 hover:bg-emerald-700',
        },
    ];
    return styles[index] || styles[0];
};

export default function WelcomeNew({
    canRegister = true,
    web_settings,
    hero,
    promociones,
    caracteristicas,
    planes,
    cta,
    nav,
}: WelcomeProps) {
    const { code: countryCode, currency } = useCountry();
    const appName = web_settings?.app_name || 'GrowERP';
    const appLogo = web_settings?.app_logo || null;
    const logoLetra = appName.charAt(0).toUpperCase();

    return (
        <div className="min-h-screen bg-gradient-to-b from-gray-50 to-white">
            <Head title={web_settings?.app_title || 'Welcome'}>
                <meta name="description" content={web_settings?.app_description || ''} />
                <meta name="keywords" content={web_settings?.app_keywords || ''} />
            </Head>

            {/* Header */}
            <header className="sticky top-0 z-50 border-b border-gray-200 bg-white/80 backdrop-blur-md">
                <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4">
                    {(nav?.app_brand_visible ?? true) ? (
                        <div className="flex items-center gap-2">
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
                        </div>
                    ) : (
                        <div />
                    )}
                    <nav className="flex items-center gap-6">
                        <div className="hidden md:flex items-center gap-6 mr-4">
                            {(nav?.quienes_somos_visible ?? true) && (
                                <Link href="/quienes-somos" className="text-sm font-medium text-gray-500 hover:text-amber-600 transition-colors">
                                    {nav?.quienes_somos || 'Quiénes Somos'}
                                </Link>
                            )}
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
                            {canRegister && (
                                <Link
                                    href="/register"
                                    className="rounded-full bg-gray-900 px-5 py-2 text-sm font-medium text-white transition-all hover:bg-gray-800 hover:shadow-lg"
                                >
                                    Regístrate gratis
                                </Link>
                            )}
                        </div>
                    </nav>
                </div>
            </header>

            {/* Hero Section */}
            <section className="relative overflow-hidden py-20 lg:py-32">
                <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-amber-100/50 via-transparent to-transparent"></div>
                <div className="mx-auto max-w-7xl px-4">
                    <div className="text-center">
                        {hero.badge && (
                            <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-4 py-1.5 text-sm font-medium text-amber-700">
                                <span className="relative flex h-2 w-2">
                                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75"></span>
                                    <span className="relative inline-flex h-2 w-2 rounded-full bg-amber-500"></span>
                                </span>
                                {hero.badge}
                            </div>
                        )}
                        <h1 className="mb-6 text-4xl font-extrabold tracking-tight text-gray-900 sm:text-5xl lg:text-6xl">
                            {hero.titulo}
                        </h1>
                        <p className="mx-auto mb-10 max-w-2xl text-lg text-gray-600">
                            {hero.subtitulo}
                        </p>
                        <div className="flex flex-col items-center justify-center gap-4 sm:flex-row">
                            {canRegister && (
                                <Link
                                    href="/register"
                                    className="group relative inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-amber-500 to-orange-500 px-8 py-4 text-lg font-bold text-white shadow-lg transition-all hover:scale-105 hover:shadow-xl"
                                >
                                    <span>{hero.boton_principal}</span>
                                    <svg
                                        className="h-5 w-5 transition-transform group-hover:translate-x-1"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth={2}
                                            d="M13 7l5 5m0 0l-5 5m5-5H6"
                                        />
                                    </svg>
                                </Link>
                            )}
                            <Link
                                href="/demo"
                                className="inline-flex items-center gap-2 rounded-full border border-gray-300 bg-white px-8 py-4 text-lg font-medium text-gray-700 transition-all hover:border-gray-400 hover:shadow-lg"
                            >
                                <svg
                                    className="h-5 w-5 text-gray-400"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        fillRule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z"
                                        clipRule="evenodd"
                                    />
                                </svg>
                                {hero.boton_secundario}
                            </Link>
                        </div>
                    </div>
                </div>
            </section>

            {/* Promociones */}
            {promociones && promociones.length > 0 && (
                <section className="bg-gradient-to-b from-amber-50/30 to-white py-16">
                    <div className="mx-auto max-w-7xl px-4">
                        <div className="mb-12 text-center">
                            <span className="mb-2 inline-block rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">
                                Ofertas Especiales
                            </span>
                            <h2 className="mb-3 text-2xl font-bold text-gray-900 sm:text-3xl">
                                Promociones Activas
                            </h2>
                            <p className="text-sm text-gray-500">
                                Descuentos y ofertas disponibles ahora
                            </p>
                        </div>
                        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {promociones.map((promo, idx) => {
                                const colors = [
                                    'from-amber-100 to-orange-100 text-amber-600',
                                    'from-blue-100 to-indigo-100 text-blue-600',
                                    'from-emerald-100 to-teal-100 text-emerald-600',
                                    'from-purple-100 to-pink-100 text-purple-600',
                                    'from-rose-100 to-orange-100 text-rose-600',
                                    'from-cyan-100 to-blue-100 text-cyan-600',
                                ];
                                const color = colors[idx % colors.length];

                                const formatValue = () => {
                                    if (promo.tipo === 'porcentaje')
                                        return `${promo.valor}% OFF`;
                                    if (promo.tipo === 'precio_fijo')
                                        return `$${Number(promo.valor).toLocaleString(currency.locale)}`;
                                    return '2x1';
                                };

                                return (
                                    <div
                                        key={idx}
                                        className="group relative overflow-hidden rounded-2xl border border-amber-100 bg-white p-6 shadow-lg transition-all hover:-translate-y-1 hover:shadow-xl"
                                    >
                                        <div
                                            className={`absolute -top-4 -right-4 inline-flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br ${color} text-xl font-black opacity-20 transition-transform group-hover:scale-110`}
                                        >
                                            {promo.tipo === 'porcentaje'
                                                ? '%'
                                                : promo.tipo === 'precio_fijo'
                                                    ? '$'
                                                    : '2x1'}
                                        </div>
                                        <div className="relative">
                                            <div
                                                className={`mb-3 inline-flex items-center gap-1.5 rounded-full bg-gradient-to-br ${color} px-3 py-1 text-xs font-bold`}
                                            >
                                                {promo.tipo === 'porcentaje'
                                                    ? '%'
                                                    : promo.tipo ===
                                                        'precio_fijo'
                                                        ? '$'
                                                        : '🎁'}
                                                {formatValue()}
                                            </div>
                                            <h3 className="mb-1 text-lg font-bold text-gray-900">
                                                {promo.nombre}
                                            </h3>
                                            {promo.categoria && (
                                                <p className="mb-2 text-xs font-medium text-amber-600">
                                                    {promo.categoria}
                                                </p>
                                            )}
                                            {promo.descripcion && (
                                                <p className="text-sm text-gray-600">
                                                    {promo.descripcion}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </section>
            )}

            {/* Características */}
            <section className="bg-white py-20">
                <div className="mx-auto max-w-7xl px-4">
                    <div className="mb-16 text-center">
                        <h2 className="mb-4 text-3xl font-bold text-gray-900 sm:text-4xl">
                            Todo lo que necesitas para tu negocio
                        </h2>
                        <p className="text-lg text-gray-600">
                            Herramientas poderosas diseñadas para hacer crecer
                            tu empresa
                        </p>
                    </div>
                    <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                        {caracteristicas.map((caract, index) => (
                            <div
                                key={index}
                                className="group rounded-2xl border border-gray-100 bg-white p-6 shadow-lg transition-all hover:-translate-y-1 hover:shadow-xl"
                            >
                                <div className="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-amber-100 to-orange-100 text-2xl">
                                    {caract.icono}
                                </div>
                                <h3 className="mb-2 text-xl font-bold text-gray-900">
                                    {caract.titulo}
                                </h3>
                                <p className="text-gray-600">
                                    {caract.descripcion}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Planes de Precios */}
            <section className="bg-gradient-to-b from-gray-50 to-amber-50/30 py-20">
                <div className="mx-auto max-w-7xl px-4">
                    <div className="mb-16 text-center">
                        <h2 className="mb-4 text-3xl font-bold text-gray-900 sm:text-4xl">
                            Planes para cada etapa de tu negocio
                        </h2>
                        <p className="text-lg text-gray-600">
                            Elige el plan que mejor se adapte a tus necesidades
                        </p>
                    </div>
                    <div className="flex flex-wrap justify-center gap-6">
                        {planes.map((plan, index) => {
                            const styles = getPlanStyles(plan, index);
                            return (
                                <div
                                    key={index}
                                    className={`relative w-full sm:max-w-[280px] rounded-2xl border-2 ${styles.borderColor} ${styles.color} p-6 transition-all hover:shadow-xl`}
                                >
                                    {plan.popular == true && (
                                        <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                                            <span className="rounded-full bg-gradient-to-r from-amber-500 to-orange-500 px-4 py-1 text-xs font-bold text-white">
                                                Más popular
                                            </span>
                                        </div>
                                    )}
                                    <div className="mb-4 text-center">
                                        <h3 className="text-lg font-bold text-gray-900">
                                            {plan.nombre}
                                        </h3>
                                        <p className="mt-1 text-sm text-gray-600">
                                            {plan.descripcion}
                                        </p>
                                    </div>
                                    <div className="mb-6 text-center">
                                        <span
                                            className={`text-4xl font-extrabold ${styles.textoPrecio}`}
                                        >
                                            {plan.precio}
                                        </span>
                                        <span className="text-gray-500">
                                            {plan.periodo}
                                        </span>
                                    </div>
                                    <ul className="mb-6 space-y-3">
                                        {plan.caracteristicas.map(
                                            (caract, i) => (
                                                <li
                                                    key={i}
                                                    className="flex items-start gap-2 text-sm text-gray-700"
                                                >
                                                    <svg
                                                        className="mt-0.5 h-5 w-5 flex-shrink-0 text-green-500"
                                                        fill="none"
                                                        viewBox="0 0 24 24"
                                                        stroke="currentColor"
                                                    >
                                                        <path
                                                            strokeLinecap="round"
                                                            strokeLinejoin="round"
                                                            strokeWidth={2}
                                                            d="M5 13l4 4L19 7"
                                                        />
                                                    </svg>
                                                    {caract}
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                    <button
                                        className={`w-full rounded-xl ${styles.boton} py-3 font-bold text-white transition-all hover:shadow-lg`}
                                    >
                                        {plan.nombre === 'Corporativo'
                                            ? 'Contactar ventas'
                                            : plan.nombre === 'Gratuito'
                                                ? 'Comenzar gratis'
                                                : 'Suscribirse'}
                                    </button>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </section>

            {/* CTA Final */}
            <section className="bg-gray-900 py-20">
                <div className="mx-auto max-w-4xl px-4 text-center">
                    <h2 className="mb-4 text-3xl font-bold text-white sm:text-4xl">
                        {cta.titulo}
                    </h2>
                    <p className="mb-8 text-lg text-gray-300">
                        {cta.descripcion}
                    </p>
                    {canRegister && (
                        <Link
                            href="/register"
                            className="inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-amber-500 to-orange-500 px-8 py-4 text-lg font-bold text-white shadow-lg transition-all hover:scale-105 hover:shadow-xl"
                        >
                            {cta.boton}
                            <svg
                                className="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M13 7l5 5m0 0l-5 5m5-5H6"
                                />
                            </svg>
                        </Link>
                    )}
                </div>
            </section>

            {/* Footer */}
            <footer className="border-t border-gray-200 bg-white py-12">
                <div className="mx-auto max-w-7xl px-4">
                    <div className="flex flex-col items-center justify-between gap-4 sm:flex-row">
                        <div className="flex items-center gap-2">
                            {appLogo ? (
                                <img
                                    src={appLogo}
                                    alt={appName}
                                    className="h-8 w-8 rounded-lg object-contain"
                                />
                            ) : (
                                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-amber-400 to-orange-500 text-lg font-black text-white">
                                    {logoLetra}
                                </div>
                            )}
                            <span className="text-lg font-bold text-gray-900">
                                {appName}
                            </span>
                        </div>
                        <p className="text-sm text-gray-500">
                            © 2026 {appName}. Todos los derechos reservados.
                        </p>
                        <div className="flex gap-6">
                            <a
                                href="#"
                                className="text-sm text-gray-500 hover:text-gray-900"
                            >
                                Términos
                            </a>
                            <a
                                href="#"
                                className="text-sm text-gray-500 hover:text-gray-900"
                            >
                                Privacidad
                            </a>
                            <a
                                href="#"
                                className="text-sm text-gray-500 hover:text-gray-900"
                            >
                                Contacto
                            </a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    );
}
