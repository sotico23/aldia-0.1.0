import { usePage } from '@inertiajs/react';

export interface CountrySettings {
    code: string;
    name: string;
    currency: {
        code: string;
        symbol: string;
        decimals: number;
        locale: string;
    };
    timezone: string;
    locale: string;
    tax: {
        name: string;
        rate: number;
    };
    fiscal_id: {
        label: string;
        pattern: string | null;
    };
    date_format: string;
    phone_code: string;
}

const FALLBACK: CountrySettings = {
    code: 'CL',
    name: 'Chile',
    currency: { code: 'CLP', symbol: '$', decimals: 0, locale: 'es-CL' },
    timezone: 'America/Santiago',
    locale: 'es-CL',
    tax: { name: 'IVA', rate: 19 },
    fiscal_id: { label: 'RUT', pattern: '/^\\d{7,8}[\\dkK]$/' },
    date_format: 'DD/MM/YYYY',
    phone_code: '+56',
};

export function useCountry(): CountrySettings {
    const { country_settings } = usePage().props as { country_settings?: CountrySettings };
    return country_settings ?? FALLBACK;
}
