/** The gap kept between the tooltip and the edges of the screen, in pixels. */
const MARGIN = 8;

/**
 * The page's one tooltip: any element carrying `data-an-tooltip` shows its
 * value on hover, kept inside the screen.
 */
export function anTooltipHost() {
    return {
        text: '',
        show: false,
        x: 0,
        y: 0,

        move(event) {
            const target = event.target.closest ? event.target.closest('[data-an-tooltip]') : null;

            if (! target || ! target.getAttribute('data-an-tooltip')) {
                this.show = false;

                return;
            }

            this.text = target.getAttribute('data-an-tooltip');
            this.show = true;
            this.$nextTick(() => this.place(target));
        },

        place(target) {
            const tip = this.$refs.tip;

            if (! tip) {
                return;
            }

            const box = target.getBoundingClientRect();
            const above = box.top - tip.offsetHeight - MARGIN;

            this.x = Math.max(MARGIN, Math.min(box.left, window.innerWidth - tip.offsetWidth - MARGIN));
            this.y = above < MARGIN ? box.bottom + MARGIN : above;
        },
    };
}
