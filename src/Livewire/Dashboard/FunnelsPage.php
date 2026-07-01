<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Funnels\FunnelEvaluator;
use Illuminate\Contracts\View\View;

/**
 * Conversion funnels: every declared funnel evaluated over the selected period,
 * with per-step reach, conversion rates and weighted score.
 */
final class FunnelsPage extends DashboardComponent
{
    public function render(FunnelEvaluator $evaluator): View
    {
        $period = $this->currentPeriod();

        return view('analytics::livewire.dashboard.funnels', [
            'range' => $period,
            'reports' => $evaluator->evaluateAll($period, $this->subjectType()),
            ...$this->filterData(),
        ])->layout($this->layoutName(), ['title' => __('Entonnoirs').' · '.__('Analytics')]);
    }
}
