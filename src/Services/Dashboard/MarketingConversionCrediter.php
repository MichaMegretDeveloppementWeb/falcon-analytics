<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Enums\ObjectiveType;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\Funnel;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Ad;
use Illuminate\Database\Eloquent\Collection;

/**
 * Credits the conversions of the visitors an ad drove · a visitor who
 * completed one of the ad's objectives (a tracked event, or a funnel) converted
 * for that ad, its campaign and the objective.
 *
 * Which ad drove which visitor is not decided here · `MarketingReportBuilder`
 * attributes the tagged sessions it has already read and hands the map over,
 * so a render reads them once. What the visitors completed is read by
 * `ObjectiveCompletionReader`.
 *
 * @internal
 */
final readonly class MarketingConversionCrediter
{
    /**
     * The collaborator default so a plain `new MarketingConversionCrediter`
     * still works; the container injects the shared service otherwise.
     */
    public function __construct(
        private ObjectiveCompletionReader $completions = new ObjectiveCompletionReader,
    ) {}

    /**
     * Conversions credited to each ad, campaign and objective over the period.
     * Counts are distinct visitors, so an ad's conversions never exceed its
     * visitors.
     *
     * @param  Collection<int, Ad>  $ads  active ads with their objectives loaded
     * @param  array<int, array<int, true>>  $visitorAds  visitor id => set of ad ids
     * @return array{total: int, campaigns: array<int, int>, ads: array<int, int>, objectives: array<int, array<string, int>>, daily: array<string, int>, campaignDaily: array<int, array<string, int>>, adDaily: array<int, array<string, int>>}
     */
    public function credit(Collection $ads, array $visitorAds, Period $period, ?string $subjectType, FunnelRegistry $funnels): array
    {
        if ($visitorAds === []) {
            return ['total' => 0, 'campaigns' => [], 'ads' => [], 'objectives' => [], 'daily' => [], 'campaignDaily' => [], 'adDaily' => []];
        }

        $visitorIds = array_keys($visitorAds);
        ['events' => $eventNames, 'funnels' => $funnelKeys] = $this->objectiveReferences($ads);

        return $this->creditConversions(
            $ads->keyBy('id'),
            $visitorAds,
            $this->completions->eventDays($visitorIds, $eventNames, $period),
            $this->completions->funnelDays($funnelKeys, $funnels, $period, $subjectType, $visitorIds),
        );
    }

    /**
     * The detailed conversion elements for a set of ads (a campaign's ads, or a
     * single ad): each objective with its conversions and, for a funnel, its
     * per-step reach. Sorted by conversions, descending.
     *
     * @param  list<Ad>  $ads  active ads with their objectives loaded
     * @param  array<int, array<int, true>>  $adVisitors  ad id => set of visitor ids
     * @return list<array{type: string, reference: string, label: string, adId: int, adName: string, conversions: int, steps: list<array{label: string, count: int}>|null}>
     */
    public function elements(array $ads, array $adVisitors, Period $period, ?string $subjectType, FunnelRegistry $funnels, EventRegistry $events): array
    {
        $eventLabels = [];
        foreach ($events->all() as $event) {
            $eventLabels[$event->name] = $event->label;
        }

        $eventVisitors = $this->objectiveEventVisitors($period, $ads, $adVisitors);

        $elements = [];
        foreach ($ads as $ad) {
            $visitorIds = array_keys($adVisitors[$ad->id] ?? []);

            foreach ($ad->objectives as $objective) {
                $element = $objective->type === ObjectiveType::Event
                    ? $this->eventElement($ad, $objective->reference, $visitorIds, $eventVisitors, $eventLabels)
                    : $this->funnelElement($ad, $objective->reference, $visitorIds, $period, $subjectType, $funnels);

                if ($element !== null) {
                    $elements[] = $element;
                }
            }
        }

        usort($elements, fn (array $a, array $b): int => $b['conversions'] <=> $a['conversions']);

        return $elements;
    }

    /**
     * One event objective of an ad, and how many of the ad's visitors fired it.
     *
     * @param  list<int>  $visitorIds
     * @param  array<string, array<int, true>>  $eventVisitors
     * @param  array<string, string>  $eventLabels
     * @return array{type: string, reference: string, label: string, adId: int, adName: string, conversions: int, steps: null}
     */
    private function eventElement(Ad $ad, string $reference, array $visitorIds, array $eventVisitors, array $eventLabels): array
    {
        $refVisitors = $eventVisitors[$reference] ?? [];
        $count = 0;
        foreach ($visitorIds as $visitorId) {
            if (isset($refVisitors[$visitorId])) {
                $count++;
            }
        }

        return [
            'type' => 'event',
            'reference' => $reference,
            'label' => $eventLabels[$reference] ?? $reference,
            'adId' => $ad->id,
            'adName' => $ad->name,
            'conversions' => $count,
            'steps' => null,
        ];
    }

    /**
     * One funnel objective of an ad, with the reach of each of its steps · null
     * when the funnel is no longer declared.
     *
     * @param  list<int>  $visitorIds
     * @return array{type: string, reference: string, label: string, adId: int, adName: string, conversions: int, steps: list<array{label: string, count: int}>}|null
     */
    private function funnelElement(Ad $ad, string $reference, array $visitorIds, Period $period, ?string $subjectType, FunnelRegistry $funnels): ?array
    {
        $funnel = $funnels->get($reference);
        if (! ($funnel instanceof Funnel)) {
            return null;
        }

        $reach = $this->completions->stepReach($funnel, $period, $subjectType, $visitorIds);
        $steps = [];
        foreach ($funnel->steps() as $index => $step) {
            $steps[] = ['label' => $step->label, 'count' => $reach[$index] ?? 0];
        }

        return [
            'type' => 'funnel',
            'reference' => $reference,
            'label' => $funnel->label,
            'adId' => $ad->id,
            'adName' => $ad->name,
            'conversions' => $reach === [] ? 0 : end($reach),
            'steps' => $steps,
        ];
    }

    /**
     * The distinct event names and funnel keys referenced by the ads' objectives.
     *
     * @param  Collection<int, Ad>  $ads
     * @return array{events: list<string>, funnels: list<string>}
     */
    private function objectiveReferences(Collection $ads): array
    {
        /** @var array<string, true> $eventNames */
        $eventNames = [];
        /** @var array<string, true> $funnelKeys */
        $funnelKeys = [];

        foreach ($ads as $ad) {
            foreach ($ad->objectives as $objective) {
                if ($objective->type === ObjectiveType::Event) {
                    $eventNames[$objective->reference] = true;
                } else {
                    $funnelKeys[$objective->reference] = true;
                }
            }
        }

        return ['events' => array_keys($eventNames), 'funnels' => array_keys($funnelKeys)];
    }

    /**
     * Crediting phase: mark each ad-driven visitor who completed one of the
     * ad's objectives as a converter for that ad, its campaign and the
     * objective, keeping the earliest completion day at every level for the
     * daily series.
     *
     * @param  Collection<int, Ad>  $adById
     * @param  array<int, array<int, true>>  $visitorAds
     * @param  array<string, array<int, string>>  $eventCompleters
     * @param  array<string, array<int, string>>  $funnelCompleters
     * @return array{total: int, campaigns: array<int, int>, ads: array<int, int>, objectives: array<int, array<string, int>>, daily: array<string, int>, campaignDaily: array<int, array<string, int>>, adDaily: array<int, array<string, int>>}
     */
    private function creditConversions(Collection $adById, array $visitorAds, array $eventCompleters, array $funnelCompleters): array
    {
        /** @var array<int, array<int, true>> $adConverters */
        $adConverters = [];
        /** @var array<int, array<int, true>> $campaignConverters */
        $campaignConverters = [];
        /** @var array<int, array<string, array<int, true>>> $objectiveConverters */
        $objectiveConverters = [];
        /** @var array<int, true> $totalConverters */
        $totalConverters = [];
        /** @var array<int, string> $visitorDay earliest conversion day per visitor, any ad */
        $visitorDay = [];
        /** @var array<int, array<int, string>> $adVisitorDay */
        $adVisitorDay = [];
        /** @var array<int, array<int, string>> $campaignVisitorDay */
        $campaignVisitorDay = [];

        foreach ($visitorAds as $visitorId => $adIds) {
            foreach (array_keys($adIds) as $adId) {
                $ad = $adById->get($adId);
                if (! ($ad instanceof Ad)) {
                    continue;
                }

                ['day' => $day, 'objectives' => $completed] = $this->completionOf($ad, $visitorId, $eventCompleters, $funnelCompleters);

                foreach ($completed as $reference) {
                    $objectiveConverters[$adId][$reference][$visitorId] = true;
                }

                if ($day === null) {
                    continue;
                }

                $adConverters[$adId][$visitorId] = true;
                $campaignConverters[$ad->campaign_id][$visitorId] = true;
                $totalConverters[$visitorId] = true;
                $adVisitorDay[$adId][$visitorId] = $day;
                $campaignVisitorDay[$ad->campaign_id][$visitorId] = $this->earlier($campaignVisitorDay[$ad->campaign_id][$visitorId] ?? null, $day);
                $visitorDay[$visitorId] = $this->earlier($visitorDay[$visitorId] ?? null, $day);
            }
        }

        return [
            'total' => count($totalConverters),
            'campaigns' => $this->counts($campaignConverters),
            'ads' => $this->counts($adConverters),
            'objectives' => array_map(fn (array $refs): array => $this->counts($refs), $objectiveConverters),
            'daily' => $this->dailyFromDays($visitorDay),
            'campaignDaily' => array_map(fn (array $days): array => $this->dailyFromDays($days), $campaignVisitorDay),
            'adDaily' => array_map(fn (array $days): array => $this->dailyFromDays($days), $adVisitorDay),
        ];
    }

    /**
     * The objectives of an ad a visitor completed, and the earliest day they
     * completed one of them · null when they completed none.
     *
     * @param  array<string, array<int, string>>  $eventCompleters
     * @param  array<string, array<int, string>>  $funnelCompleters
     * @return array{day: ?string, objectives: list<string>}
     */
    private function completionOf(Ad $ad, int $visitorId, array $eventCompleters, array $funnelCompleters): array
    {
        $day = null;
        $completed = [];

        foreach ($ad->objectives as $objective) {
            $completedOn = $objective->type === ObjectiveType::Event
                ? ($eventCompleters[$objective->reference][$visitorId] ?? null)
                : ($funnelCompleters[$objective->reference][$visitorId] ?? null);

            if ($completedOn !== null) {
                $completed[] = $objective->reference;
                $day = $this->earlier($day, $completedOn);
            }
        }

        return ['day' => $day, 'objectives' => $completed];
    }

    /** The earlier of two days, as Y-m-d · the second one when there is no first. */
    private function earlier(?string $current, string $day): string
    {
        return $current === null || $day < $current ? $day : $current;
    }

    /**
     * How many visitors each set holds.
     *
     * @template TKey of array-key
     *
     * @param  array<TKey, array<int, true>>  $sets
     * @return array<TKey, int>
     */
    private function counts(array $sets): array
    {
        return array_map(fn (array $visitors): int => count($visitors), $sets);
    }

    /**
     * Counts of converting visitors per day.
     *
     * @param  array<int, string>  $daysByVisitor
     * @return array<string, int>
     */
    private function dailyFromDays(array $daysByVisitor): array
    {
        $daily = [];
        foreach ($daysByVisitor as $day) {
            $daily[$day] = ($daily[$day] ?? 0) + 1;
        }

        return $daily;
    }

    /**
     * The visitors who fired each event an objective of these ads names, among
     * the visitors the ads drove.
     *
     * @param  list<Ad>  $ads
     * @param  array<int, array<int, true>>  $adVisitors
     * @return array<string, array<int, true>> event name => set of visitor ids
     */
    private function objectiveEventVisitors(Period $period, array $ads, array $adVisitors): array
    {
        /** @var array<string, true> $eventRefs */
        $eventRefs = [];
        foreach ($ads as $ad) {
            foreach ($ad->objectives as $objective) {
                if ($objective->type === ObjectiveType::Event) {
                    $eventRefs[$objective->reference] = true;
                }
            }
        }

        /** @var array<int, true> $allVisitorIds */
        $allVisitorIds = [];
        foreach ($adVisitors as $visitors) {
            foreach (array_keys($visitors) as $visitorId) {
                $allVisitorIds[$visitorId] = true;
            }
        }

        if ($eventRefs === [] || $allVisitorIds === []) {
            return [];
        }

        return $this->completions->eventVisitors(array_keys($allVisitorIds), array_keys($eventRefs), $period);
    }
}
