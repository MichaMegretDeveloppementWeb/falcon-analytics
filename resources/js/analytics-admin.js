import { anAreaChart } from './admin/components/area-chart.js';
import { anCopyButton } from './admin/components/copy-button.js';
import { anDonut } from './admin/components/donut.js';
import { anLiveDonut } from './admin/components/live-donut.js';
import { anLiveLine } from './admin/components/live-line.js';
import { anSparkline } from './admin/components/sparkline.js';
import { anWorldMap } from './admin/components/world-map.js';
import { anObjectivePicker } from './admin/dashboard/objective-picker.js';
import { anRealtimeTabs } from './admin/dashboard/realtime-tabs.js';
import { anSessionTabs } from './admin/dashboard/session-tabs.js';
import { anTooltipHost } from './admin/dashboard/tooltip-host.js';
import { anCloseWhenDone, anOpenWhenDone } from './admin/modal-actions.js';

/** Every Alpine component the dashboard's views name. */
const components = {
    anAreaChart,
    anCopyButton,
    anDonut,
    anLiveDonut,
    anLiveLine,
    anObjectivePicker,
    anRealtimeTabs,
    anSessionTabs,
    anSparkline,
    anTooltipHost,
    anWorldMap,
};

/** Every Alpine magic the dashboard's views call · a Livewire call, then a modal. */
const magics = {
    anCloseWhenDone,
    anOpenWhenDone,
};

function register() {
    for (const [name, component] of Object.entries(components)) {
        window.Alpine.data(name, component);
    }

    for (const [name, magic] of Object.entries(magics)) {
        window.Alpine.magic(name, magic);
    }
}

// Alpine may already be there, after a navigation; otherwise it announces its start.
if (window.Alpine) {
    register();
} else {
    document.addEventListener('alpine:init', register, { once: true });
}
