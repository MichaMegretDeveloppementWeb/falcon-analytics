<?php

declare(strict_types=1);

namespace Falcon\Analytics\Funnels;

use Falcon\Analytics\DTOs\Dashboard\FunnelReport;
use Falcon\Analytics\DTOs\Dashboard\FunnelStepResult;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Models\Event;

/**
 * Evaluates code-declared funnels against the raw events, streaming them
 * through the shared FunnelEventWalker. Progression is sequential: a visitor
 * reaches step i only by matching each earlier step in chronological order
 * first, so reach is monotonically decreasing and a step can never out-count
 * the one before it.
 *
 * @internal
 */
final readonly class FunnelEvaluator
{
    public function __construct(
        private FunnelRegistry $registry,
        private FunnelEventWalker $walker,
    ) {}

    /**
     * @return list<FunnelReport>
     */
    public function evaluateAll(Period $period, ?string $subjectType): array
    {
        return array_map(
            fn (Funnel $funnel): FunnelReport => $this->evaluate($funnel, $period, $subjectType),
            $this->registry->all(),
        );
    }

    public function evaluate(Funnel $funnel, Period $period, ?string $subjectType): FunnelReport
    {
        $steps = $funnel->steps();
        ['reached' => $reached, 'branches' => $branchReach] = $this->reach($funnel, $period, $subjectType);

        $entrants = $steps !== [] ? $reached[0] : 0;
        $results = [];

        foreach ($steps as $i => $step) {
            $results[] = $this->stepResult($step, $reached[$i], $i === 0 ? null : $reached[$i - 1], $entrants, $branchReach[$i] ?? []);
        }

        $totalScore = array_sum(array_map(fn (FunnelStepResult $result): int => $result->score, $results));

        return new FunnelReport($funnel->key, $funnel->label, $entrants, $totalScore, $results);
    }

    /**
     * How many visitors reached each step, and by which branch when a step has
     * several ways in.
     *
     * @return array{reached: array<int, int>, branches: array<int, array<string, int>>}
     */
    private function reach(Funnel $funnel, Period $period, ?string $subjectType): array
    {
        $steps = $funnel->steps();
        $reached = array_fill(0, max(count($steps), 1), 0);

        /** @var array<int, array<string, int>> $branchReach step index => branch label => visitors */
        $branchReach = [];

        $this->walker->walk(
            $funnel,
            $period,
            $subjectType,
            null,
            function (int $visitorId, int $stepIndex, Event $event) use (&$reached, &$branchReach, $steps): void {
                $reached[$stepIndex]++;

                $branch = $steps[$stepIndex]->branchFor($event);

                if ($branch !== null) {
                    $branchReach[$stepIndex][$branch->label] = ($branchReach[$stepIndex][$branch->label] ?? 0) + 1;
                }
            },
        );

        return ['reached' => $reached, 'branches' => $branchReach];
    }

    /**
     * One step's reading · every declared branch appears, including those
     * nobody took: a zero is itself a reading, and a disappearing row would
     * look like a bug.
     *
     * @param  array<string, int>  $branchReach  branch label => visitors
     */
    private function stepResult(FunnelStep $step, int $visitors, ?int $previous, int $entrants, array $branchReach): FunnelStepResult
    {
        $branches = [];

        foreach ($step->branches as $branch) {
            $branches[$branch->label] = $branchReach[$branch->label] ?? 0;
        }

        return new FunnelStepResult(
            label: $step->label,
            value: $step->value,
            visitors: $visitors,
            conversionFromStart: $entrants > 0 ? $visitors / $entrants : 0.0,
            conversionFromPrevious: $previous === null ? 1.0 : ($previous > 0 ? $visitors / $previous : 0.0),
            score: $visitors * $step->value,
            branches: $branches,
        );
    }
}
