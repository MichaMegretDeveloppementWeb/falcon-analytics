<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Widgets;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Repositories\Dashboard\MarketingReadRepository;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Deferred ad-driven sessions-and-conversions trend, shared by the marketing
 * dashboard (scope "overview") and the campaign/ad detail pages (scope "campaign"
 * or "ad" with the subject id in refId). The parent gives it a wire:key built from
 * scope/refId/period/subject, so a filter change re-mounts it — no reactive props
 * leak to sibling components.
 */
#[Lazy]
final class MarketingTrendChart extends Component
{
    public int $period = Period::DEFAULT_DAYS;

    public string $subject = '';

    public string $scope = 'overview';

    public ?int $refId = null;

    public function placeholder(): View
    {
        return view('analytics::livewire.dashboard.widgets.trend-chart-placeholder');
    }

    public function render(MarketingReadRepository $marketing, FunnelRegistry $funnels): View
    {
        $range = Period::ofDays($this->period);
        $subjectType = $this->subject !== '' ? $this->subject : null;
        $conversions = $marketing->conversions($range, $subjectType, $funnels);

        /** @var array<string, int> $sessionsDaily */
        $sessionsDaily = [];
        /** @var array<string, int> $conversionsDaily */
        $conversionsDaily = [];

        if ($this->scope === 'campaign' && $this->refId !== null) {
            $campaign = Campaign::query()->find($this->refId);
            if ($campaign instanceof Campaign) {
                $sessionsDaily = $marketing->campaignReport($range, $subjectType, $campaign)['daily'];
            }
            $conversionsDaily = $conversions['campaignDaily'][$this->refId] ?? [];
        } elseif ($this->scope === 'ad' && $this->refId !== null) {
            $ad = Ad::query()->find($this->refId);
            if ($ad instanceof Ad) {
                $sessionsDaily = $marketing->adReport($range, $subjectType, $ad)['daily'];
            }
            $conversionsDaily = $conversions['adDaily'][$this->refId] ?? [];
        } else {
            $sessionsDaily = $marketing->dailySessions($range, $subjectType);
            $conversionsDaily = $conversions['daily'];
        }

        $labels = [];
        $sessionsData = [];
        $conversionsData = [];
        foreach ($range->eachDay() as $day) {
            $key = $day->toDateString();
            $labels[] = $day->isoFormat('D MMM');
            $sessionsData[] = $sessionsDaily[$key] ?? 0;
            $conversionsData[] = $conversionsDaily[$key] ?? 0;
        }

        return view('analytics::livewire.dashboard.widgets.marketing-trend-chart', [
            'labels' => $labels,
            'sessionsData' => $sessionsData,
            'conversionsData' => $conversionsData,
        ]);
    }
}
