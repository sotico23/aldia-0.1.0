import { Head, Link } from '@inertiajs/react';

interface PublicPageProps {
    title: string;
    content: string;
    web_settings: {
        app_name: string;
        app_logo: string | null;
    };
}

export default function PublicPage({
    title,
    content,
    web_settings,
}: PublicPageProps) {
    const appName = web_settings?.app_name || 'GrowERP';
    const appLogo = web_settings?.app_logo || null;
    const logoLetra = appName.charAt(0).toUpperCase();

    return (
        <div className="flex min-h-screen flex-col bg-gray-50">
            <Head title={title} />

            {/* Header */}
            <header className="sticky top-0 z-50 border-b border-gray-200 bg-white/80 backdrop-blur-md">
                <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4">
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
                    <nav className="flex items-center gap-4">
                        <Link
                            href="/login"
                            className="text-sm font-medium text-gray-600 hover:text-gray-900"
                        >
                            Iniciar sesión
                        </Link>
                        <Link
                            href="/register"
                            className="rounded-full bg-gray-900 px-5 py-2 text-sm font-medium text-white transition-all hover:bg-gray-800 hover:shadow-lg"
                        >
                            Regístrate gratis
                        </Link>
                    </nav>
                </div>
            </header>

            {/* Content */}
            <main className="container mx-auto max-w-4xl flex-grow px-4 py-12">
                <div className="rounded-2xl border border-gray-100 bg-white p-8 shadow-sm md:p-12">
                    <h1 className="mb-8 border-b border-gray-100 pb-4 text-3xl font-extrabold text-gray-900 md:text-4xl">
                        {title}
                    </h1>

                    <div className="prose prose-amber max-w-none leading-relaxed whitespace-pre-wrap text-gray-600">
                        {content}
                    </div>

                    <div className="mt-12 flex justify-center border-t border-gray-100 pt-8">
                        <Link
                            href="/"
                            className="inline-flex items-center gap-2 font-medium text-amber-600 transition-colors hover:text-amber-700"
                        >
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
                                    d="M10 19l-7-7m0 0l7-7m-7 7h18"
                                />
                            </svg>
                            Volver al inicio
                        </Link>
                    </div>
                </div>
            </main>

            {/* Footer */}
            <footer className="border-t border-gray-200 bg-white py-8">
                <div className="mx-auto max-w-7xl px-4 text-center">
                    <p className="text-sm text-gray-500">
                        © 2026 {appName}. Todos los derechos reservados.
                    </p>
                </div>
            </footer>
        </div>
    );
}
