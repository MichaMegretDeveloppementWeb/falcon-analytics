/** A list of objectives to add, opened from a button and filtered by typing. */
export function anObjectivePicker() {
    return {
        open: false,
        search: '',

        toggle() {
            this.open = ! this.open;
            this.search = '';
        },

        /** Closes on the escape key and gives the focus back to the button, leaving the modal around it open. */
        closeOnEscape(event) {
            if (! this.open) {
                return;
            }

            event.stopPropagation();
            this.open = false;
            this.$refs.trigger.focus();
        },
    };
}
