import { CONFIRMATION, copyText } from '../clipboard.js';

/**
 * One copy behaviour for a whole list · each row's button hands it its value,
 * and `copied` names the value just copied, so only that row shows the check
 * mark.
 */
export function anCopyList() {
    let timer = null;

    return {
        copied: null,

        async copy(text) {
            if (! await copyText(text)) {
                return;
            }

            this.copied = text;
            clearTimeout(timer);
            timer = setTimeout(() => { this.copied = null; }, CONFIRMATION);
        },

        destroy() {
            clearTimeout(timer);
        },
    };
}
