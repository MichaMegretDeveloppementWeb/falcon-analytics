<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Widgets;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Funnels\FunnelEvaluator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Deferred funnel reports: every declared funnel evaluated over the period (and the
 * previous one, for per-step deltas). The evaluation is the whole weight of the
 * page, so it loads after the header and filters have painted.
 */
#[Lazy]
final class FunnelsContent extends Component
{
    public int $period = Period::DEFAULT_DAYS;

    public string $subject = '';

    public function placeholder(): View
    {
        return view('analytics::livewire.dashboard.widgets.section-skeleton');
    }

    public function render(FunnelEvaluator $evaluator): View
    {
        $period = Period::ofDays($this->period);
        $subjectType = $this->subject !== '' ? $this->subject : null;

        return view('analytics::livewire.dashboard.widgets.funnels-content', [
            'reports' => $evaluator->evaluateAll($period, $subjectType),
            'previousReports' => Collection::make($evaluator->evaluateAll($period->previous(), $subjectType))->keyBy('key'),
        ]);
    }
}
