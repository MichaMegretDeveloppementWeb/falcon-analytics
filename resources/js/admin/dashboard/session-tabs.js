/** The width from which the session's two columns sit side by side. */
const WIDE = '(min-width: 1024px)';

/**
 * The session screen's layout: two columns on a wide screen, two tabs below.
 * It follows the screen as it is resized, and lets go of it once removed.
 */
export function anSessionTabs() {
    const query = window.matchMedia(WIDE);
    let follow = null;

    return {
        tab: 'infos',
        desktop: query.matches,

        init() {
            follow = (event) => { this.desktop = event.matches; };
            query.addEventListener('change', follow);
        },

        destroy() {
            query.removeEventListener('change', follow);
        },
    };
}
