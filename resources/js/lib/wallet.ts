export function formatWalletAmount(
    amount: number | string,
    currency = 'CLP',
): string {
    const value = Number(amount);

    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency,
        minimumFractionDigits: Number.isInteger(value) ? 0 : 2,
        maximumFractionDigits: 2,
    }).format(value);
}
