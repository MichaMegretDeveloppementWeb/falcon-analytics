<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Funnels\Funnel;
use Falcon\Analytics\Funnels\FunnelEventWalker;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Repositories\Dashboard\MarketingReadRepository;

/**
 * What the tracked data says about the objectives of ads · which visitors fired
 * an event, which completed a funnel, and when. The crediting of conversions is
 * decided from it, elsewhere.
 *
 * @internal
 */
final readonly class ObjectiveCompletionReader
{
    /**
     * The collaborator defaults so a plain `new ObjectiveCompletionReader`
     * still works; the container injects the shared services otherwise.
     */
    public function __construct(
        private MarketingReadRepository $repository = new MarketingReadRepository,
        private FunnelEventWalker $walker = new FunnelEventWalker,
    ) {}

    /**
     * The earliest completion day of each event per visitor, from one batched
     * read.
     *
     * @param  list<int>  $visitorIds
     * @param  list<string>  $names
     * @return array<string, array<int, string>> event name => visitor id => day (Y-m-d)
     */
    public function eventDays(array $visitorIds, array $names, Period $period): array
    {
        if ($names === []) {
            return [];
        }

        /** @var array<string, array<int, string>> $completers */
        $completers = [];

        foreach ($this->repository->objectiveEventRows($visitorIds, $names, $period) as $row) {
            $name = (string) $row->name;
            $visitorId = $row->visitor_id;
            $day = $row->occurred_at->toDateString();
            if (! isset($completers[$name][$visitorId]) || $day < $completers[$name][$visitorId]) {
                $completers[$name][$visitorId] = $day;
            }
        }

        return $completers;
    }

    /**
     * The visitors who fired each event, in one read for every ad and
     * objective.
     *
     * @param  list<int>  $visitorIds
     * @param  list<string>  $names
     * @return array<string, array<int, true>> event name => set of visitor ids
     */
    public function eventVisitors(array $visitorIds, array $names, Period $period): array
    {
        /** @var array<string, array<int, true>> $eventVisitors */
        $eventVisitors = [];
        foreach ($this->repository->distinctObjectiveEventRows($visitorIds, $names, $period) as $row) {
            $eventVisitors[(string) $row->name][$row->visitor_id] = true;
        }

        return $eventVisitors;
    }

    /**
     * The completion day of each funnel per visitor, walking each funnel once
     * over the given visitors.
     *
     * @param  list<string>  $keys
     * @param  list<int>  $visitorIds
     * @return array<string, array<int, string>> funnel key => visitor id => day (Y-m-d)
     */
    public function funnelDays(array $keys, FunnelRegistry $funnels, Period $period, ?string $subjectType, array $visitorIds): array
    {
        /** @var array<string, array<int, string>> $completers */
        $completers = [];

        foreach ($keys as $key) {
            $funnel = $funnels->get($key);
            if ($funnel instanceof Funnel) {
                $completers[$key] = $this->funnelCompleters($funnel, $period, $subjectType, $visitorIds);
            }
        }

        return $completers;
    }

    /**
     * How many of the given visitors reached each funnel step (monotonically
     * decreasing), over the period. Index i = visitors who reached step i.
     *
     * @param  list<int>  $visitorIds
     * @return list<int>
     */
    public function stepReach(Funnel $funnel, Period $period, ?string $subjectType, array $visitorIds): array
    {
        $reached = array_fill(0, count($funnel->steps()), 0);

        $this->walker->walk(
            $funnel,
            $period,
            $subjectType,
            $visitorIds,
            function (int $visitorId, int $stepIndex, Event $event) use (&$reached): void {
                $reached[$stepIndex]++;
            },
        );

        // `array_values`: incrementing by reference loses the `list` type `array_fill` gave it.
        return array_values($reached);
    }

    /**
     * The subset of the given visitors who completed the funnel (reached its
     * last step in chronological order) over the period, mirroring the funnels
     * screen.
     *
     * @param  list<int>  $visitorIds
     * @return array<int, string> visitor id => completion day (Y-m-d)
     */
    private function funnelCompleters(Funnel $funnel, Period $period, ?string $subjectType, array $visitorIds): array
    {
        $lastIndex = count($funnel->steps()) - 1;

        /** @var array<int, string> $completers */
        $completers = [];

        $this->walker->walk(
            $funnel,
            $period,
            $subjectType,
            $visitorIds,
            function (int $visitorId, int $stepIndex, Event $event) use (&$completers, $lastIndex): void {
                if ($stepIndex === $lastIndex) {
                    $completers[$visitorId] = $event->occurred_at->toDateString();
                }
            },
        );

        return $completers;
    }
}
