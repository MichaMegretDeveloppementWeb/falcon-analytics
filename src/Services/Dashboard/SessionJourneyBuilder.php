<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\Dashboard;

use Carbon\CarbonInterface;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Support\PageUrl;
use Illuminate\Support\Collection;

/**
 * Turns a session's ordered events into its chronological journey and the time
 * spent per page. Deterministic: the output depends only on the given events
 * and session window, so it lives outside the Livewire component.
 */
final class SessionJourneyBuilder
{
    /**
     * Group events into a journey: each pageview is a step, other events nest
     * under the page they happened on, and each step carries the seconds spent
     * before the next step (or the end of the session).
     *
     * The whole session window is partitioned across the steps so the per-page
     * times always sum to the session duration: the first step starts at the
     * session start, every boundary is clamped inside [start, end].
     *
     * @param  Collection<int, Event>  $events
     * @return list<array{event: Event, children: list<Event>, seconds: int}>
     */
    public function build(Collection $events, CarbonInterface $windowStart, CarbonInterface $windowEnd): array
    {
        $journey = [];

        foreach ($events as $event) {
            if ($event->type === EventType::Pageview || $journey === []) {
                $journey[] = ['event' => $event, 'children' => [], 'seconds' => 0];

                continue;
            }

            $journey[array_key_last($journey)]['children'][] = $event;
        }

        $clamp = fn (CarbonInterface $moment): CarbonInterface => $moment->lessThan($windowStart)
            ? $windowStart
            : ($moment->greaterThan($windowEnd) ? $windowEnd : $moment);

        foreach ($journey as $index => $step) {
            $from = $index === 0 ? $windowStart : $clamp($step['event']->occurred_at);
            $to = isset($journey[$index + 1]) ? $clamp($journey[$index + 1]['event']->occurred_at) : $windowEnd;
            $journey[$index]['seconds'] = max(0, (int) $from->diffInSeconds($to));
        }

        return $journey;
    }

    /**
     * Seconds spent per page (route resolved to a clean URL), aggregated across
     * repeat visits and sorted from most to least time.
     *
     * @param  list<array{event: Event, children: list<Event>, seconds: int}>  $journey
     * @return array<string, int>
     */
    public function timePerPage(array $journey): array
    {
        $byPage = [];

        foreach ($journey as $step) {
            if ($step['event']->type !== EventType::Pageview) {
                continue;
            }

            $label = PageUrl::resolve($step['event']->route, $step['event']->url) ?: '·';

            $byPage[$label] = ($byPage[$label] ?? 0) + $step['seconds'];
        }

        arsort($byPage);

        return $byPage;
    }
}
