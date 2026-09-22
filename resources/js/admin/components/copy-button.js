import { CONFIRMATION, copyText } from '../clipboard.js';

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
            if (await copyText(text)) {
                this.confirm();
            }
        },

        destroy() {
            clearTimeout(timer);
        },
    };
}
