<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\Authorization\Ability;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Livewire\Admin\Concerns\AsksTheScreenAbility;
use Falcon\Analytics\Livewire\Admin\Concerns\RecoversFromReadFailure;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Repositories\Dashboard\RealtimeReadRepository;
use Falcon\Analytics\Services\Dashboard\RealtimeRowBuilder;
use Falcon\Analytics\Support\ChartPalette;
use Falcon\Analytics\Support\DeviceLabel;
use Falcon\Analytics\Support\SourceLabel;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
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
 *
 * @internal
 */
final class RealtimePage extends Component
{
    use AsksTheScreenAbility;
    use RecoversFromReadFailure;

    /** Bound of the countries list next to the map. */
    private const MAX_COUNTRY_ROWS = 8;

    public function render(RealtimeReadRepository $repository, RealtimeRowBuilder $rows, EventRegistry $events): View
    {
        return $this->guardedRender(
            fn (): array => [
                ...$this->board($repository, $rows, $events),
                'mayOpenSessions' => Gate::allows(Ability::Sessions),
            ],
            fn (array $data): View => view('analytics::livewire.dashboard.realtime', $data),
        );
    }

    /**
     * Everything the board shows, read for the realtime window.
     *
     * @return array<string, mixed>
     */
    private function board(RealtimeReadRepository $repository, RealtimeRowBuilder $rows, EventRegistry $events): array
    {
        $now = CarbonImmutable::now();
        $windowMinutes = self::setting('window_minutes', 30);
        $since = $now->subMinutes($windowMinutes);
        $onlineSince = $now->subSeconds(self::setting('online_seconds', 60));
        $conversionNames = self::conversionNames($events);

        $charts = $this->charts($repository, $since, $onlineSince, $now);
        $this->sendToTheCharts($charts);

        return [
            'onlineCount' => $repository->onlineCount($onlineSince, null),
            'window' => $repository->windowCounts($since, null),
            'conversionsCount' => $repository->conversionsCount($since, null, $conversionNames),
            ...$charts,
            'countries' => $this->countriesFrom($charts['map']['points']),
            ...$rows->build(
                $this->recentSessions($repository, $now),
                $repository->activityFeed($since, null, self::setting('feed_limit', 25)),
                $onlineSince,
                $conversionNames,
            ),
            'topPages' => $repository->topPages($since, null),
            'windowMinutes' => $windowMinutes,
            'pollSeconds' => self::setting('poll_seconds', 10),
        ];
    }

    /**
     * The series of the four live charts.
     *
     * @return array{minuteSeries: array<string, int>, devices: array{labels: list<string>, values: list<int>, colors: list<string>, total: int, count: int}, sources: array{labels: list<string>, values: list<int>, colors: list<string>, total: int, count: int}, map: array{points: list<array{city: string|null, country: string|null, latitude: float, longitude: float, total: int, online: int}>, unlocated: int}}
     */
    private function charts(RealtimeReadRepository $repository, CarbonImmutable $since, CarbonImmutable $onlineSince, CarbonImmutable $now): array
    {
        return [
            'minuteSeries' => $this->zeroFilledMinutes($repository->pageviewsPerMinute($since, null), $since, $now),
            'devices' => $this->chartSeries($repository->topDevices($since, null), DeviceLabel::for(...)),
            'sources' => $this->chartSeries($repository->topSources($since, null), SourceLabel::for(...)),
            'map' => [
                'points' => $repository->mapPoints($since, $onlineSince),
                'unlocated' => $repository->unlocatedCount($since),
            ],
        ];
    }

    /**
     * Hands the fresh series to the live charts, which sit under wire:ignore
     * and update in place. A doughnut's centre shows how many categories it
     * holds, not the sessions.
     *
     * @param  array{minuteSeries: array<string, int>, devices: array{count: int}, sources: array{count: int}, map: array<string, mixed>}  $charts
     */
    private function sendToTheCharts(array $charts): void
    {
        $this->dispatch(
            'an-realtime-tick',
            pulse: ['labels' => array_keys($charts['minuteSeries']), 'values' => array_values($charts['minuteSeries'])],
            devices: [...$charts['devices'], 'total' => $charts['devices']['count']],
            sources: [...$charts['sources'], 'total' => $charts['sources']['count']],
            map: $charts['map'],
        );
    }

    /**
     * One session per visitor of the last day, their most recent · the feed,
     * the figures and the map stay on the realtime window.
     *
     * @return Collection<int, Session>
     */
    private function recentSessions(RealtimeReadRepository $repository, CarbonImmutable $now): Collection
    {
        return $repository->recentSessions($now->subDay(), null, self::setting('feed_limit', 25))
            ->unique('visitor_id')
            ->values();
    }

    /**
     * The names of the declared events that count as a conversion.
     *
     * @return list<string>
     */
    private static function conversionNames(EventRegistry $events): array
    {
        $names = [];

        foreach ($events->all() as $event) {
            if ($event->isConversion()) {
                $names[] = $event->name;
            }
        }

        return $names;
    }

    /** A realtime setting, one at the least. */
    private static function setting(string $key, int $default): int
    {
        return max(1, (int) config("analytics.realtime.{$key}", $default));
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
     * Country rows for the countries list, derived from the map points (no extra
     * query): totals and online counts per country, busiest first.
     *
     * @param  list<array{city: string|null, country: string|null, latitude: float, longitude: float, total: int, online: int}>  $points
     * @return list<array{country: string|null, total: int, online: int}>
     */
    private function countriesFrom(array $points): array
    {
        $countries = [];

        foreach ($points as $point) {
            $key = $point['country'] ?? '??';
            $countries[$key] ??= ['country' => $point['country'], 'total' => 0, 'online' => 0];
            $countries[$key]['total'] += $point['total'];
            $countries[$key]['online'] += $point['online'];
        }

        usort($countries, fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return array_slice($countries, 0, self::MAX_COUNTRY_ROWS);
    }

    /**
     * Doughnut-ready series from a breakdown: display labels, values, one ramp
     * token per slice, the session sum and the category count.
     *
     * @param  list<array{label: string, total: int}>  $breakdown
     * @param  callable(string): string  $labelFor
     * @return array{labels: list<string>, values: list<int>, colors: list<string>, total: int, count: int}
     */
    private function chartSeries(array $breakdown, callable $labelFor): array
    {
        $labels = [];
        $values = [];
        $colors = ChartPalette::steps(count($breakdown), ChartPalette::LIVE);

        foreach ($breakdown as $row) {
            $labels[] = $labelFor($row['label']);
            $values[] = $row['total'];
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'colors' => $colors,
            'total' => array_sum($values),
            'count' => count($labels),
        ];
    }

    protected function screenAbility(): Ability
    {
        return Ability::Realtime;
    }
}
