/**
 * The real-time screen's two views of its visitors · the realtime window, or
 * those online now. Choosing one tells the map, which filters its markers.
 */
export function anRealtimeTabs() {
    return {
        tab: 'window',

        choose(tab) {
            this.tab = tab;
            this.$dispatch('an-realtime-mode', { mode: tab });
        },
    };
}
