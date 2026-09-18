/** A list of objectives to add, opened from a button and filtered by typing. */
export function anObjectivePicker() {
    return {
        open: false,
        search: '',

        toggle() {
            this.open = ! this.open;
            this.search = '';
        },
    };
}
