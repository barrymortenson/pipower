/** +1, then 10 digits; the area code cannot start with 0 or 1. */
const US_PHONE = /^\+1[2-9]\d{9}$/;

/** The form UsPhoneInput writes while typing: +1 and up to 10 digits. */
const TYPED_US_PHONE = /^\+1\d{0,10}$/;

/**
 * The national part of a US phone number: digits only, at most 10.
 * Drops a leading +1 or 1 country code and any spaces or symbols, so
 * "+18055551234", "+1 (805) 555-1234" and "1-805-555-1234" all become
 * "8055551234".
 *
 * Returns null instead of cutting digits off when the value has another
 * country code (such as +44) or more than 10 digits, so a number is never
 * silently turned into a different one.
 */
export function usPhoneDigits(value) {
    let text = String(value ?? '').trim();

    if (text.startsWith('+')) {
        if (!text.startsWith('+1')) {
            return null;
        }
        text = text.slice(2);
    }

    let digits = text.replace(/\D/g, '');

    if (digits.length === 11 && digits.startsWith('1')) {
        digits = digits.slice(1);
    }

    return digits.length <= 10 ? digits : null;
}

/**
 * The value to save: +1XXXXXXXXXX when the value has exactly 10 US digits,
 * otherwise the value as written (trimmed), so validation can reject it.
 */
export function toUsPhone(value) {
    const digits = usPhoneDigits(value);

    return digits?.length === 10 ? `+1${digits}` : String(value ?? '').trim();
}

/**
 * True when UsPhoneInput can show the value as digits without hiding
 * anything: a number it wrote itself, or one with exactly 10 US digits.
 */
export function showsAsUsDigits(value) {
    return TYPED_US_PHONE.test(value ?? '') || usPhoneDigits(value)?.length === 10;
}

/** True for a valid US number in the stored form, +1 followed by 10 digits. */
export function isUsPhone(value) {
    return US_PHONE.test(value ?? '');
}
