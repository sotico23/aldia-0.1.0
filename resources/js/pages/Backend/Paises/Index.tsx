import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { index } from '@/routes/paises';
import CountriesTab from '../ConfiguracionWeb/CountriesTab';
import type { Country } from '../ConfiguracionWeb/CountriesTab';

export default function CountriesIndex({
    countries,
}: {
    countries: Country[];
}) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: dashboard().url },
                { title: 'Países', href: index().url },
            ]}
        >
            <Head title="Países" />
            <div className="mx-auto w-full max-w-6xl min-w-0 p-3 sm:p-6">
                <CountriesTab countries={countries} />
            </div>
        </AppLayout>
    );
}
