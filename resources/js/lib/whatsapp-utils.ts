/**
 * Formats a phone number for WhatsApp wa.me links.
 * Standardizes to international format for Chile (+569...) if it looks like a local number.
 */
export function formatWhatsAppNumber(phone: string): string {
    // Remove all non-numeric characters
    let cleaned = phone.replace(/\D/g, '');

    // If it's a 9-digit Chilean number starting with 9, add 56
    if (cleaned.length === 9 && cleaned.startsWith('9')) {
        cleaned = '56' + cleaned;
    }

    // If it's a 8-digit Chilean number, add 569
    if (cleaned.length === 8) {
        cleaned = '569' + cleaned;
    }

    // Ensure it doesn't have leading plus (wa.me doesn't need it)
    return cleaned;
}

export function getWhatsAppLink(phone: string, message?: string): string {
    const formatted = formatWhatsAppNumber(phone);
    let url = `https://wa.me/${formatted}`;
    if (message) {
        url += `?text=${encodeURIComponent(message)}`;
    }
    return url;
}
