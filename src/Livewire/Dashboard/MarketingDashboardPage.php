<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Models\Session;
use Illuminate\Contracts\View\View;

/**
 * The marketing landing: headline figures, a trend and a per-campaign performance
 * table over the selected period. Traffic figures now; conversion figures land
 * with the attribution engine.
 */
final class MarketingDashboardPage extends DashboardComponent
{
    public function render(): View
    {
        return $this->guardedRender(
            function (): array {
                $period = $this->currentPeriod();
                $subjectType = $this->subjectType();

                $taggedSessions = Session::query()
                    ->where('is_bot', false)
                    ->whereNotNull('mkt_params')
                    ->whereBetween('started_at', [$period->from, $period->to])
                    ->when($subjectType !== null, fn ($query) => $query->where('subject_type', $subjectType));

                return [
                    'range' => $period,
                    'campaignCount' => Campaign::query()->count(),
                    'adCount' => Ad::query()->count(),
                    'sessionsFromAds' => (clone $taggedSessions)->count(),
                    'visitorsFromAds' => (clone $taggedSessions)->distinct()->count('visitor_id'),
                    ...$this->filterData(),
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.marketing-dashboard', $data)
                ->layout($this->layoutName('marketing'), ['title' => __('Tableau de bord').' · '.__('Marketing')]),
        );
    }
}
