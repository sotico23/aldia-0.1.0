import { useCountry, type CountrySettings } from '@/hooks/use-country';
import { CURRENCIES, DEFAULT_CURRENCY, type CurrencyInfo } from './currencies';

export function getCurrencyInfoForCountry(countryCode: string): CurrencyInfo {
    const countryMap: Record<string, string> = {
        CL: 'CLP',
        PE: 'PEN',
    };
    const code = countryMap[countryCode] || DEFAULT_CURRENCY;
    return CURRENCIES[code] || CURRENCIES[DEFAULT_CURRENCY];
}

export function formatCurrencyForCountry(amount: number | string | null | undefined, countryCode?: string): string {
    const code = countryCode || 'CL';
    const info = getCurrencyInfoForCountry(code);
    const value = typeof amount === 'string' ? parseFloat(amount) : amount;
    if (value === null || value === undefined || isNaN(value)) return `${info.symbol} 0`;

    return new Intl.NumberFormat(info.locale, {
        style: 'currency',
        currency: info.code,
        minimumFractionDigits: info.decimals,
        maximumFractionDigits: info.decimals,
    }).format(value);
}

export function formatDateForCountry(dateString: string | null | undefined, countryCode?: string): string {
    if (!dateString) return '-';
    const parts = dateString.split('-');
    if (parts.length !== 3) return '-';
    const [year, month, day] = parts.map(Number);
    if (isNaN(year) || isNaN(month) || isNaN(day)) return '-';
    const date = new Date(year, month - 1, day);
    if (isNaN(date.getTime())) return '-';

    const localeMap: Record<string, string> = {
        CL: 'es-CL',
        PE: 'es-PE',
    };
    const locale = localeMap[countryCode || 'CL'] || 'es-CL';

    return new Intl.DateTimeFormat(locale, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
}

export function formatNumberForCountry(value: number | null | undefined, countryCode?: string): string {
    if (value === null || value === undefined || isNaN(value)) return '0';

    const localeMap: Record<string, string> = {
        CL: 'es-CL',
        PE: 'es-PE',
    };
    const locale = localeMap[countryCode || 'CL'] || 'es-CL';

    return new Intl.NumberFormat(locale).format(value);
}

export function formatDateTimeForCountry(dateString: string | null | undefined, countryCode?: string): string {
    if (!dateString) return '-';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '-';

    const localeMap: Record<string, string> = {
        CL: 'es-CL',
        PE: 'es-PE',
    };
    const locale = localeMap[countryCode || 'CL'] || 'es-CL';

    return new Intl.DateTimeFormat(locale, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

export function formatTimeForCountry(dateString: string | null | undefined, countryCode?: string): string {
    if (!dateString) return '-';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '-';

    const localeMap: Record<string, string> = {
        CL: 'es-CL',
        PE: 'es-PE',
    };
    const locale = localeMap[countryCode || 'CL'] || 'es-CL';

    return new Intl.DateTimeFormat(locale, {
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

export function formatMonthShortForCountry(year: number | string, month: number | string, countryCode?: string): string {
    const date = new Date(+year, +month - 1);

    const localeMap: Record<string, string> = {
        CL: 'es-CL',
        PE: 'es-PE',
    };
    const locale = localeMap[countryCode || 'CL'] || 'es-CL';

    return new Intl.DateTimeFormat(locale, { month: 'short' }).format(date);
}
