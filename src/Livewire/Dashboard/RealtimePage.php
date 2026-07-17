<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Livewire\Dashboard\Concerns\RecoversFromReadFailure;
use Falcon\Analytics\Livewire\Dashboard\Concerns\ResolvesDashboardLayout;
use Falcon\Analytics\Livewire\Dashboard\Concerns\ResolvesSubjectNames;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Repositories\Dashboard\RealtimeReadRepository;
use Falcon\Analytics\Services\Dashboard\SessionSubjectAttributor;
use Falcon\Analytics\Services\SubjectResolver;
use Falcon\Analytics\Support\DeviceLabel;
use Falcon\Analytics\Support\SourceLabel;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Realtime screen: who is online now and the recent-window activity (KPIs,
 * per-minute chart, device and source doughnuts, activity feed, top pages),
 * refreshed through plain Livewire polling suspended while the tab is hidden.
 * No worker, websocket or external service: it must run on any host. Every
 * read is bounded because the whole render re-runs on each tick. No filters:
 * realtime shows everything, and realtime IS the period. Each render also
 * dispatches the fresh series to the live charts, which sit under wire:ignore
 * and update in place instead of being destroyed by the morph.
 */
final class RealtimePage extends Component
{
    use RecoversFromReadFailure;
    use ResolvesDashboardLayout;
    use ResolvesSubjectNames;

    private const PALETTE = ['#1684ea', '#4b9bf0', '#7cb8f2', '#a5cdf7', '#bcdcfa', '#d1d5db'];

    public function render(
        RealtimeReadRepository $repository,
        SessionSubjectAttributor $attributor,
        SubjectResolver $subjects,
        EventRegistry $events,
    ): View {
        return $this->guardedRender(
            function () use ($repository, $attributor, $subjects, $events): array {
                $now = CarbonImmutable::now();
                $windowMinutes = max(1, (int) config('analytics.realtime.window_minutes', 30));
                $since = $now->subMinutes($windowMinutes);
                $onlineSince = $now->subSeconds(max(1, (int) config('analytics.realtime.online_seconds', 60)));

                $conversionNames = [];
                $eventLabels = [];
                foreach ($events->all() as $declared) {
                    $eventLabels[$declared->name] = $declared->label;
                    if ($declared->isConversion()) {
                        $conversionNames[] = $declared->name;
                    }
                }

                $feed = $repository->activityFeed($since, null, max(1, (int) config('analytics.realtime.feed_limit', 25)));

                /** @var list<Session> $feedSessions */
                $feedSessions = $feed->pluck('session')->filter()->unique('id')->values()->all();
                $attributions = $attributor->attribute($feedSessions);

                $minuteSeries = $this->zeroFilledMinutes($repository->pageviewsPerMinute($since, null), $since, $now);
                $devices = $this->chartSeries($repository->topDevices($since, null), fn (string $label): string => DeviceLabel::for($label));
                $sources = $this->chartSeries($repository->topSources($since, null), fn (string $label): string => SourceLabel::for($label));

                // Fresh series for the live charts (wire:ignore + in-place update).
                $this->dispatch(
                    'analytics-realtime-tick',
                    pulse: ['labels' => array_keys($minuteSeries), 'values' => array_values($minuteSeries)],
                    devices: $devices,
                    sources: $sources,
                );

                return [
                    'onlineCount' => $repository->onlineCount($onlineSince, null),
                    'window' => $repository->windowCounts($since, null),
                    'conversionsCount' => $repository->conversionsCount($since, null, $conversionNames),
                    'minuteSeries' => $minuteSeries,
                    'devices' => $devices,
                    'sources' => $sources,
                    'feed' => $feed,
                    'attributions' => $attributions,
                    'subjectNames' => $this->resolveAttributedNames($attributions, $subjects),
                    'conversionNames' => $conversionNames,
                    'eventLabels' => $eventLabels,
                    'topPages' => $repository->topPages($since, null),
                    'windowMinutes' => $windowMinutes,
                    'pollSeconds' => max(1, (int) config('analytics.realtime.poll_seconds', 10)),
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.realtime', $data)
                ->layout($this->layoutName(), ['title' => __('Temps réel').' · '.__('Analytics')]),
        );
    }

    /**
     * One bucket per minute of the window, oldest first, zeros where the
     * repository returned nothing, so the per-minute chart always spans the
     * window.
     *
     * @param  array<string, int>  $buckets
     * @return array<string, int>
     */
    private function zeroFilledMinutes(array $buckets, CarbonImmutable $since, CarbonImmutable $now): array
    {
        $series = [];
        $cursor = $since->startOfMinute();
        $end = $now->startOfMinute();

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->format('Y-m-d H:i');
            $series[$cursor->format('H:i')] = $buckets[$key] ?? 0;
            $cursor = $cursor->addMinute();
        }

        return $series;
    }

    /**
     * Doughnut-ready series from a breakdown: display labels, values, one
     * palette colour per slice, and the total.
     *
     * @param  list<array{label: string, total: int}>  $breakdown
     * @param  callable(string): string  $labelFor
     * @return array{labels: list<string>, values: list<int>, colors: list<string>, total: int}
     */
    private function chartSeries(array $breakdown, callable $labelFor): array
    {
        $labels = [];
        $values = [];
        $colors = [];

        foreach ($breakdown as $index => $row) {
            $labels[] = $labelFor($row['label']);
            $values[] = $row['total'];
            $colors[] = self::PALETTE[$index] ?? '#d1d5db';
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'colors' => $colors,
            'total' => array_sum($values),
        ];
    }
}
