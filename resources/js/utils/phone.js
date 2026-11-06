/**
 * The national part of a US phone number: digits only, at most 10.
 * Drops a leading +1 or 1 country code and any spaces or symbols, so
 * "+18055551234", "+1 (805) 555-1234" and "1-805-555-1234" all become
 * "8055551234".
 */
export function usPhoneDigits(value) {
    let text = String(value ?? '').trim();

    if (text.startsWith('+1')) {
        text = text.slice(2);
    }

    let digits = text.replace(/\D/g, '');

    if (digits.length === 11 && digits.startsWith('1')) {
        digits = digits.slice(1);
    }

    return digits.slice(0, 10);
}

/** True for +1 followed by exactly 10 digits. */
export function isUsPhone(value) {
    return /^\+1\d{10}$/.test(value ?? '');
}
