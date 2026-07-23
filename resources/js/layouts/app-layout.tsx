import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { TrialExpiryModal } from '@/components/trial-expiry-modal';
import { TrialWarningModal } from '@/components/trial-warning-modal';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { index as planesIndex } from '@/routes{planesIndex().url}';
import type { AppLayoutProps } from '@/types';

const TrialBanner = () => {
    const { auth } = usePage().props as any;
    const user = auth?.user;
    const [now] = useState(() => Date.now());

    if (!user?.is_trial_active && !user?.is_trial_expired) return null;

    if (user.is_trial_active) {
        const daysLeft = user.trial_days_remaining ?? Math.max(0, Math.ceil((new Date(user.trial_ends_at).getTime() - now) / (1000 * 60 * 60 * 24)));

        if (daysLeft <= 1) {
            return (
                <div className="bg-red-600 text-white text-center text-xs py-1.5 px-4 font-medium">
                    Tu período de prueba termina <strong>hoy</strong> — elige un plan para no perder acceso
                    {' — '}
                    <Link href="{planesIndex().url}" className="underline font-semibold hover:text-red-100">
                        Ver planes
                    </Link>
                </div>
            );
        }

        if (daysLeft <= 3) {
            return (
                <div className="bg-orange-500 text-white text-center text-xs py-1.5 px-4 font-medium">
                    Tu período de prueba termina en <strong>{daysLeft} días</strong> — elige un plan para continuar
                    {' — '}
                    <Link href="{planesIndex().url}" className="underline font-semibold hover:text-orange-100">
                        Ver planes
                    </Link>
                </div>
            );
        }

        if (daysLeft <= 7) {
            return (
                <div className="bg-amber-500 text-white text-center text-xs py-1.5 px-4 font-medium">
                    Periodo de prueba activo — te quedan <strong>{daysLeft} días</strong> con acceso completo
                    {' — '}
                    <Link href="{planesIndex().url}" className="underline font-semibold hover:text-amber-100">
                        Ver planes
                    </Link>
                </div>
            );
        }

        return (
            <div className="bg-blue-600 text-white text-center text-xs py-1.5 px-4 font-medium">
                Periodo de prueba activo — te quedan <strong>{daysLeft} días</strong> con acceso completo
                {' — '}
                <Link href="{planesIndex().url}" className="underline font-semibold hover:text-blue-100">
                    Ver planes
                </Link>
            </div>
        );
    }

    if (user.is_trial_expired) {
        return (
            <div className="bg-red-600 text-white text-center text-xs py-1.5 px-4 font-medium">
                Tu período de prueba finalizó — puedes seguir viendo tus datos pero no crear ni editar registros
                {' — '}
                <Link href="{planesIndex().url}" className="underline font-semibold hover:text-red-100">
                    Actualizar plan
                </Link>
            </div>
        );
    }

    return null;
};

export default ({ children, breadcrumbs, ...props }: AppLayoutProps) => (
    <AppLayoutTemplate breadcrumbs={breadcrumbs} {...props}>
        <TrialBanner />
        <TrialWarningModal />
        <TrialExpiryModal />
        {children}
    </AppLayoutTemplate>
);
