/** How long the check mark stays after a copy, in milliseconds. */
const CONFIRMATION = 1400;

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

/**
 * A copy-to-clipboard button. A check mark confirms a copy; its absence is
 * what a refused copy looks like.
 */
export function anCopyButton(text) {
    let timer = null;

    return {
        copied: false,

        confirm() {
            this.copied = true;
            clearTimeout(timer);
            timer = setTimeout(() => { this.copied = false; }, CONFIRMATION);
        },

        async copy() {
            if (navigator.clipboard?.writeText) {
                try {
                    await navigator.clipboard.writeText(text);
                    this.confirm();

                    return;
                } catch (_refused) {
                    // The page may refuse the Clipboard API; the field below is the other way.
                }
            }

            if (copyThroughAField(text)) {
                this.confirm();
            }
        },

        destroy() {
            clearTimeout(timer);
        },
    };
}
