/**
 * Asks the kit's modals to open or close, from the element that made the
 * gesture · the event bubbles to the window, where every modal listens.
 */
function tell(element, event, modal) {
    element.dispatchEvent(new CustomEvent(event, { detail: modal, bubbles: true, composed: true }));
}

/** Waits for a Livewire call, and opens the modal only when it answers yes. */
export function anOpenWhenDone(element) {
    return async (call, modal) => {
        const answer = await call;

        if (answer === true) {
            tell(element, 'ui-open-modal', modal);
        }

        return answer;
    };
}

/** Waits for a Livewire call, and closes the modal only when it answers yes. */
export function anCloseWhenDone(element) {
    return async (call, modal) => {
        const answer = await call;

        if (answer === true) {
            tell(element, 'ui-close-modal', modal);
        }

        return answer;
    };
}
