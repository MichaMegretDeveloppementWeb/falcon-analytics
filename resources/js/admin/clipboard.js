/** How long a check mark stays after a copy, in milliseconds. */
export const CONFIRMATION = 1400;

/**
 * Copies through a hidden field, for a page without the Clipboard API · plain
 * HTTP, where it is not offered. Tells whether the browser copied.
 */
function copyThroughAField(text) {
    const field = document.createElement('textarea');

    field.value = text;
    field.style.position = 'fixed';
    field.style.opacity = '0';
    document.body.appendChild(field);
    field.select();

    const copied = document.execCommand('copy');

    document.body.removeChild(field);

    return copied;
}

/** Copies a text to the clipboard, and tells whether it was copied. */
export async function copyText(text) {
    if (navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(text);

            return true;
        } catch (_refused) {
            // The page may refuse the Clipboard API; the field below is the other way.
        }
    }

    return copyThroughAField(text);
}
