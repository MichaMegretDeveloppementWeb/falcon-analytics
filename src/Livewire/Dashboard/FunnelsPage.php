<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Funnels\FunnelEvaluator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

/**
 * Conversion funnels: every declared funnel evaluated over the selected period,
 * with per-step reach, conversion rates, weighted score and a period-over-period
 * delta per step.
 */
final class FunnelsPage extends DashboardComponent
{
    public function render(FunnelEvaluator $evaluator): View
    {
        $period = $this->currentPeriod();
        $subjectType = $this->subjectType();

        return view('analytics::livewire.dashboard.funnels', [
            'range' => $period,
            'reports' => $evaluator->evaluateAll($period, $subjectType),
            'previousReports' => Collection::make($evaluator->evaluateAll($period->previous(), $subjectType))->keyBy('key'),
            ...$this->filterData(),
        ])->layout($this->layoutName(), ['title' => __('Entonnoirs').' · '.__('Analytics')]);
    }
}
