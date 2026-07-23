export interface CurrencyInfo {
    code: string;
    symbol: string;
    decimals: number;
    locale: string;
    name: string;
}

export const CURRENCIES: Record<string, CurrencyInfo> = {
    CLP: { code: 'CLP', symbol: '$', decimals: 0, locale: 'es-CL', name: 'Peso Chileno' },
    COP: { code: 'COP', symbol: '$', decimals: 0, locale: 'es-CO', name: 'Peso Colombiano' },
    PEN: { code: 'PEN', symbol: 'S/', decimals: 2, locale: 'es-PE', name: 'Sol Peruano' },
    ARS: { code: 'ARS', symbol: '$', decimals: 2, locale: 'es-AR', name: 'Peso Argentino' },
    BOB: { code: 'BOB', symbol: 'Bs', decimals: 2, locale: 'es-BO', name: 'Boliviano' },
    USD: { code: 'USD', symbol: '$', decimals: 2, locale: 'en-US', name: 'Dólar' },
    BRL: { code: 'BRL', symbol: 'R$', decimals: 2, locale: 'pt-BR', name: 'Real Brasileño' },
    VES: { code: 'VES', symbol: 'Bs', decimals: 2, locale: 'es-VE', name: 'Bolívar Venezolano' },
    UYU: { code: 'UYU', symbol: '$', decimals: 2, locale: 'es-UY', name: 'Peso Uruguayo' },
    PYG: { code: 'PYG', symbol: '₲', decimals: 0, locale: 'es-PY', name: 'Guaraní' },
    GTQ: { code: 'GTQ', symbol: 'Q', decimals: 2, locale: 'es-GT', name: 'Quetzal' },
};

export const DEFAULT_CURRENCY = 'CLP';

export function getCurrencyInfo(code: string): CurrencyInfo {
    return CURRENCIES[code] || CURRENCIES[DEFAULT_CURRENCY];
}

export const CURRENCY_OPTIONS = Object.values(CURRENCIES).map((c) => ({
    value: c.code,
    label: `${c.name} (${c.symbol})`,
    symbol: c.symbol,
}));
