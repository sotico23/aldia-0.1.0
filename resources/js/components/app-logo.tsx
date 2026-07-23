import { usePage } from '@inertiajs/react';
import AppLogoIcon from './app-logo-icon';

interface AuthUser {
    business_logo_url?: string | null;
}

export default function AppLogo() {
    const { web_settings, auth } = usePage().props as {
        web_settings?: { app_logo: string | null };
        auth?: { user?: AuthUser | null };
    };
    const businessLogo = auth?.user?.business_logo_url;
    const globalLogo = web_settings?.app_logo;
    const appLogo = businessLogo || globalLogo || null;

    return (
        <div className="flex w-full items-center justify-center py-2">
            <div className={`flex items-center justify-center overflow-hidden rounded-2xl ${!appLogo ? 'size-32 bg-sidebar-primary text-sidebar-primary-foreground' : 'h-40 w-full max-w-[300px]'}`}>
                {appLogo ? (
                    <img
                        src={appLogo}
                        alt="Logo"
                        className="h-full w-full object-contain rounded-2xl"
                        onError={(e) => {
                            (e.target as HTMLImageElement).style.display = 'none';
                        }}
                    />
                ) : (
                    <AppLogoIcon className="size-16 fill-current text-white dark:text-black" />
                )}
            </div>
        </div>
    );
}
