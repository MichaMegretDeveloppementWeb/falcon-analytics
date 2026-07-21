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
        $stepCount = count($steps);
        $reached = array_fill(0, max($stepCount, 1), 0);

        $this->walker->walk(
            $funnel,
            $period,
            $subjectType,
            null,
            function (int $visitorId, int $stepIndex, Event $event) use (&$reached): void {
                $reached[$stepIndex]++;
            },
        );

        $entrants = $stepCount > 0 ? $reached[0] : 0;
        $results = [];
        $totalScore = 0.0;

        foreach ($steps as $i => $step) {
            $visitors = $reached[$i];
            $score = $visitors * $step->value;
            $totalScore += $score;

            $results[] = new FunnelStepResult(
                label: $step->label,
                value: $step->value,
                visitors: $visitors,
                conversionFromStart: $entrants > 0 ? $visitors / $entrants : 0.0,
                conversionFromPrevious: $i === 0 ? 1.0 : ($reached[$i - 1] > 0 ? $visitors / $reached[$i - 1] : 0.0),
                score: $score,
            );
        }

        return new FunnelReport($funnel->key, $funnel->label, $entrants, $totalScore, $results);
    }
}
