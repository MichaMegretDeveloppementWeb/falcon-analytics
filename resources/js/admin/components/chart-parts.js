/**
 * A chart colour given by its token name. The kit resolves the name before
 * every draw, so the chart follows a theme switch without being told.
 */
export function tokenColour(name) {
    return `var(${name})`;
}

/** Token names turned into chart colours, one per slice. */
export function tokenColours(names) {
    return (names ?? []).map(tokenColour);
}

/** The part of a live tick addressed to one chart, as Livewire hands it over. */
export function payloadFor(detail, channel) {
    return (Array.isArray(detail) ? detail[0] : detail)?.[channel];
}

/** The options every doughnut of the dashboard shares. */
export function doughnutOptions() {
    return {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '72%',
        plugins: {
            legend: { display: false },
            tooltip: { padding: 8, cornerRadius: 6, bodyFont: { size: 12 } },
        },
    };
}

/** A doughnut's single dataset, its slices named by their tokens. */
export function doughnutDataset(values, colours) {
    return {
        data: values,
        backgroundColor: tokenColours(colours),
        borderColor: 'var(--ui-bg-surface)',
        borderWidth: 2,
        hoverOffset: 3,
    };
}
