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

function register() {
    for (const [name, component] of Object.entries(components)) {
        window.Alpine.data(name, component);
    }
}

// Alpine may already be there, after a navigation; otherwise it announces its start.
if (window.Alpine) {
    register();
} else {
    document.addEventListener('alpine:init', register, { once: true });
}
