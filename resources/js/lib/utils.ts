import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

import { CURRENCIES, DEFAULT_CURRENCY, type CurrencyInfo } from './currencies';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

export function formatCurrencyCLP(amount: number | string | null | undefined): string {
    return formatCurrency(amount, DEFAULT_CURRENCY);
}

export function formatCurrency(amount: number | string | null | undefined, currencyCode?: string): string {
    const code = currencyCode || DEFAULT_CURRENCY;
    const info: CurrencyInfo = CURRENCIES[code] || CURRENCIES[DEFAULT_CURRENCY];
    const value = typeof amount === 'string' ? parseFloat(amount) : amount;
    if (value === null || value === undefined || isNaN(value)) return `${info.symbol} 0`;

    return new Intl.NumberFormat(info.locale, {
        style: 'currency',
        currency: info.code,
        minimumFractionDigits: info.decimals,
        maximumFractionDigits: info.decimals,
    }).format(value);
}

const LOCALE_MAP: Record<string, string> = {
    CL: 'es-CL',
    PE: 'es-PE',
};

export function formatDateCLP(dateString: string | null | undefined): string {
    return formatDateLocale(dateString, 'es-CL');
}

export function formatDateCL(dateString: string | null | undefined): string {
    return formatDateLocale(dateString, 'es-CL');
}

export function formatDatePE(dateString: string | null | undefined): string {
    return formatDateLocale(dateString, 'es-PE');
}

export function formatDateLocale(dateString: string | null | undefined, locale?: string): string {
    if (!dateString) return '-';
    const parts = dateString.split('-');
    if (parts.length !== 3) return '-';
    const [year, month, day] = parts.map(Number);
    if (isNaN(year) || isNaN(month) || isNaN(day)) return '-';
    const date = new Date(year, month - 1, day);
    if (isNaN(date.getTime())) return '-';

    return new Intl.DateTimeFormat(locale || 'es-CL', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
}

export function formatDateTimeLocale(dateString: string | null | undefined, locale?: string): string {
    if (!dateString) return '-';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '-';

    return new Intl.DateTimeFormat(locale || 'es-CL', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

export function formatTimeLocale(dateString: string | null | undefined, locale?: string): string {
    if (!dateString) return '-';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '-';

    return new Intl.DateTimeFormat(locale || 'es-CL', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

export function formatNumberLocale(value: number | null | undefined, locale?: string): string {
    if (value === null || value === undefined || isNaN(value)) return '0';
    return new Intl.NumberFormat(locale || 'es-CL').format(value);
}

export function formatMonthShortLocale(year: number | string, month: number | string, locale?: string): string {
    const date = new Date(+year, +month - 1);
    return new Intl.DateTimeFormat(locale || 'es-CL', { month: 'short' }).format(date);
}

export function getCountryLocale(countryCode: string): string {
    return LOCALE_MAP[countryCode] || 'es-CL';
}

export function getLocalDateString(date?: Date): string {
    const d = date || new Date();
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

export function cleanRut(rut: string): string {
    return rut.replace(/[^0-9kK]/g, '');
}

export function validarRutChileno(rut: string): boolean | null {
    const clean = cleanRut(rut);
    if (clean.length < 8) return null;

    const dv = clean.slice(-1).toUpperCase();
    const body = clean.slice(0, -1);

    let sum = 0;
    let multiplier = 2;
    for (let i = body.length - 1; i >= 0; i--) {
        sum += parseInt(body[i]) * multiplier;
        multiplier = multiplier === 7 ? 2 : multiplier + 1;
    }

    const expectedDv = 11 - (sum % 11);
    if (expectedDv === 11) return dv === '0';
    if (expectedDv === 10) return dv === 'K';
    return dv === String(expectedDv);
}

export function formatRut(rut: string): string {
    const clean = cleanRut(rut);
    if (clean.length < 2) return clean;

    let body = clean.slice(0, -1);
    const dv = clean.slice(-1).toUpperCase();

    let result = '';
    while (body.length > 3) {
        result = '.' + body.slice(-3) + result;
        body = body.slice(0, -3);
    }
    result = body + result;

    return result + '-' + dv;
}
