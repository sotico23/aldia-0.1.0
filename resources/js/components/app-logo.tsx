import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import AppLogoIcon from './app-logo-icon';

interface AuthUser {
    business_logo_url?: string | null;
}

export default function AppLogo({ sidebar = false }: { sidebar?: boolean }) {
    const { web_settings, auth } = usePage().props as {
        web_settings?: { app_logo: string | null };
        auth?: { user?: AuthUser | null };
    };
    const businessLogo = auth?.user?.business_logo_url;
    const globalLogo = web_settings?.app_logo;
    const appLogo = businessLogo || globalLogo || null;

    return (
        <div
            className={`flex w-full items-center justify-center ${sidebar ? 'h-full min-w-0' : 'py-2'}`}
        >
            <div
                className={cn(
                    'flex items-center justify-center',
                    sidebar
                        ? 'size-full min-w-0'
                        : 'overflow-hidden rounded-2xl',
                    !sidebar &&
                        (appLogo ? 'h-40 w-full max-w-[300px]' : 'size-32'),
                    !appLogo &&
                        'rounded-2xl bg-sidebar-primary text-sidebar-primary-foreground',
                )}
            >
                {appLogo ? (
                    <img
                        src={appLogo}
                        alt="Logo"
                        className={`size-full object-contain ${sidebar ? '' : 'rounded-2xl'}`}
                        onError={(e) => {
                            (e.target as HTMLImageElement).style.display =
                                'none';
                        }}
                    />
                ) : (
                    <AppLogoIcon
                        className={`fill-current text-white dark:text-black ${sidebar ? 'size-8 max-h-full max-w-full' : 'size-16'}`}
                    />
                )}
            </div>
        </div>
    );
}
