import { Head } from '@inertiajs/react';
import '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import EmailMarketingPage from '../ConfiguracionWeb/EmailMarketing';

interface Template {
    id?: number;
    slug: string;
    name: string;
    subject: string;
    content: string;
    type: 'system' | 'marketing';
    is_active: boolean;
    is_default?: boolean;
}

interface Props {
    templates: Template[];
    type?: 'system' | 'marketing';
}

export default function Index({ templates, type }: Props) {
    const currentType = type || 'system';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Configuración', href: '#config' },
        { title: currentType === 'system' ? 'Emails de Sistema' : 'Email Marketing', href: '/mail-templates' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={currentType === 'system' ? 'Emails de Sistema' : 'Email Marketing'} />
            <div className="p-6">
                <EmailMarketingPage templates={templates} type={currentType} />
            </div>
        </AppLayout>
    );
}
