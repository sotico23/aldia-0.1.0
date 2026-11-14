import { Link } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { cn } from '@/lib/utils';
import {
    feedback,
    fundacion,
    home,
    login,
    quienesSomos,
    register,
} from '@/routes';

export interface PublicNavigation {
    quienes_somos?: string;
    feedback?: string;
    fundacion?: string;
    app_brand_visible?: boolean;
    quienes_somos_visible?: boolean;
    feedback_visible?: boolean;
    fundacion_visible?: boolean;
}

interface PublicHeaderProps {
    appName: string;
    appLogo?: string | null;
    nav?: PublicNavigation;
    activePage?: 'quienes-somos' | 'feedback' | 'fundacion';
    tone?: 'amber' | 'emerald' | 'rose';
    brandPrefix?: string;
    canRegister?: boolean;
    showLogin?: boolean;
}

const tones = {
    amber: {
        logo: 'from-amber-400 to-orange-500',
        brand: 'text-gray-900',
        active: 'text-amber-600',
        hover: 'hover:text-amber-600',
    },
    emerald: {
        logo: 'from-emerald-500 to-purple-600',
        brand: 'bg-gradient-to-r from-emerald-600 to-purple-600 bg-clip-text text-transparent',
        active: 'text-emerald-600',
        hover: 'hover:text-emerald-600',
    },
    rose: {
        logo: 'from-rose-500 to-orange-500',
        brand: 'bg-gradient-to-r from-rose-600 to-orange-500 bg-clip-text text-transparent',
        active: 'text-rose-600',
        hover: 'hover:text-rose-600',
    },
};

export function PublicHeader({
    appName,
    appLogo,
    nav,
    activePage,
    tone = 'amber',
    brandPrefix,
    canRegister = false,
    showLogin = true,
}: PublicHeaderProps) {
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const menuId = useId();
    const menuTrigger = useRef<HTMLButtonElement>(null);
    const colors = tones[tone];
    const brandName = brandPrefix ? `${brandPrefix} ${appName}` : appName;
    const items = [
        {
            key: 'quienes-somos',
            title: nav?.quienes_somos || 'Quiénes Somos',
            href: quienesSomos(),
            visible: nav?.quienes_somos_visible ?? true,
        },
        {
            key: 'feedback',
            title: nav?.feedback || 'Feedback',
            href: feedback(),
            visible: nav?.feedback_visible ?? true,
        },
        {
            key: 'fundacion',
            title: nav?.fundacion || 'Nuestra Fundación',
            href: fundacion(),
            visible: nav?.fundacion_visible ?? true,
        },
    ].filter((item) => item.visible);

    useEffect(() => {
        const desktop = window.matchMedia('(min-width: 1280px)');
        const closeOnDesktop = () => {
            if (desktop.matches) {
                setIsMenuOpen(false);
            }
        };

        desktop.addEventListener('change', closeOnDesktop);

        return () => desktop.removeEventListener('change', closeOnDesktop);
    }, []);

    const accountLinks = (mobile = false) => (
        <div
            className={cn(
                'gap-3',
                mobile ? 'grid sm:grid-cols-2' : 'flex shrink-0 items-center',
            )}
        >
            {showLogin && (
                <Link
                    href={login()}
                    onClick={() => setIsMenuOpen(false)}
                    className="inline-flex min-h-11 items-center justify-center rounded-full px-3 py-2 text-sm font-medium whitespace-nowrap text-gray-600 hover:bg-gray-50 hover:text-gray-900"
                >
                    Iniciar sesión
                </Link>
            )}
            {canRegister && (
                <Link
                    href={register()}
                    onClick={() => setIsMenuOpen(false)}
                    className="inline-flex min-h-11 items-center justify-center rounded-full bg-gray-900 px-5 py-2 text-sm font-medium whitespace-nowrap text-white transition-colors hover:bg-gray-800"
                >
                    Regístrate gratis
                </Link>
            )}
        </div>
    );

    return (
        <header
            className="sticky top-0 z-50 border-b border-gray-200 bg-white/90 backdrop-blur-md"
            onKeyDown={(event) => {
                if (event.key === 'Escape' && isMenuOpen) {
                    setIsMenuOpen(false);
                    menuTrigger.current?.focus();
                }
            }}
        >
            <div className="mx-auto flex min-h-16 max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 xl:py-4">
                <Link
                    href={home()}
                    className="flex min-w-0 items-center gap-2"
                    title={brandName}
                >
                    {(nav?.app_brand_visible ?? true) ? (
                        <>
                            {appLogo ? (
                                <img
                                    src={appLogo}
                                    alt=""
                                    className="size-9 shrink-0 rounded-lg object-contain sm:size-10"
                                />
                            ) : (
                                <span
                                    className={cn(
                                        'flex size-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br text-xl font-black text-white sm:size-10',
                                        colors.logo,
                                    )}
                                >
                                    {appName.charAt(0).toUpperCase()}
                                </span>
                            )}
                            <span
                                className={cn(
                                    'truncate text-lg font-bold sm:text-xl',
                                    colors.brand,
                                )}
                            >
                                {brandName}
                            </span>
                        </>
                    ) : (
                        <span className="sr-only">{brandName}</span>
                    )}
                </Link>

                <nav
                    aria-label="Navegación principal"
                    className="hidden min-w-0 items-center justify-end gap-6 xl:flex"
                >
                    <div className="flex min-w-0 items-center gap-6">
                        {items.map((item) => (
                            <Link
                                key={item.key}
                                href={item.href}
                                aria-current={
                                    activePage === item.key ? 'page' : undefined
                                }
                                className={cn(
                                    'min-w-0 text-sm font-medium break-words transition-colors',
                                    colors.hover,
                                    activePage === item.key
                                        ? colors.active
                                        : 'text-gray-500',
                                )}
                            >
                                {item.title}
                            </Link>
                        ))}
                    </div>
                    {accountLinks()}
                </nav>

                <button
                    ref={menuTrigger}
                    type="button"
                    aria-label={
                        isMenuOpen
                            ? 'Cerrar menú de navegación'
                            : 'Abrir menú de navegación'
                    }
                    aria-expanded={isMenuOpen}
                    aria-controls={menuId}
                    onClick={() => setIsMenuOpen((open) => !open)}
                    className="inline-flex size-11 shrink-0 items-center justify-center rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary xl:hidden"
                >
                    {isMenuOpen ? (
                        <X className="size-5" />
                    ) : (
                        <Menu className="size-5" />
                    )}
                </button>
            </div>

            <nav
                id={menuId}
                aria-label="Navegación móvil"
                hidden={!isMenuOpen}
                className="max-h-[calc(100dvh-4rem)] overflow-y-auto border-t border-gray-100 px-4 py-4 sm:px-6 xl:hidden"
            >
                <div className="mx-auto max-w-7xl space-y-3">
                    <div className="space-y-1">
                        {items.map((item) => (
                            <Link
                                key={item.key}
                                href={item.href}
                                aria-current={
                                    activePage === item.key ? 'page' : undefined
                                }
                                onClick={() => setIsMenuOpen(false)}
                                className={cn(
                                    'flex min-h-11 items-center rounded-lg px-3 py-2 text-sm font-medium break-words hover:bg-gray-50',
                                    colors.hover,
                                    activePage === item.key
                                        ? colors.active
                                        : 'text-gray-600',
                                )}
                            >
                                {item.title}
                            </Link>
                        ))}
                    </div>
                    {(showLogin || canRegister) && (
                        <div className="border-t border-gray-100 pt-3">
                            {accountLinks(true)}
                        </div>
                    )}
                </div>
            </nav>
        </header>
    );
}
