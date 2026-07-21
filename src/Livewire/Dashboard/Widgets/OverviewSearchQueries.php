<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Widgets;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Livewire\Dashboard\Concerns\GuardsWidgetRead;
use Falcon\Analytics\Models\SearchConsoleConnection;
use Falcon\Analytics\Repositories\Dashboard\SearchQueryReadRepository;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Deferred "Clics par recherches Google" section: the organic queries Google
 * reported for the period, read from the local Search Console cache. Without
 * an attached connection the section renders a call-to-action towards the
 * integrations screen instead of numbers. This is a different source from the
 * campaign terms (utm_term): these are the words typed into Google.
 */
#[Lazy]
final class OverviewSearchQueries extends Component
{
    use GuardsWidgetRead;

    private const TOP_LIMIT = 10;

    public int $period = Period::DEFAULT_DAYS;

    public function placeholder(): View
    {
        return view('analytics::livewire.dashboard.widgets.section-skeleton');
    }

    public function render(SearchQueryReadRepository $repository): View
    {
        return $this->guardedWidget(function () use ($repository): array {
            $connection = SearchConsoleConnection::current();
            $range = Period::ofDays($this->period);
            $connected = $connection?->isConnected() ?? false;

            return [
                'connected' => $connected,
                'connectionStatus' => $connection?->status,
                'queries' => $connected ? $repository->topQueries($range, self::TOP_LIMIT) : [],
                'totals' => $connected ? $repository->clicksTotals($range) : ['current' => 0, 'previous' => 0],
                'freshestDate' => $connected ? $repository->freshestDate($range) : null,
                'range' => $range,
                'integrationsRoute' => route(config('analytics.dashboard.route_name', 'analytics').'.integrations'),
            ];
        }, fn (array $data): View => view('analytics::livewire.dashboard.widgets.overview-search-queries', $data));
    }
}
