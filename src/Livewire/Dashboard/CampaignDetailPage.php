<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\MetricDelta;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\AdObjective;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Repositories\Dashboard\MarketingReadRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A single campaign in detail: its headline traffic over the period, its identity
 * and URL conditions, and the table of its ads with per-ad traffic and conversion
 * objectives. Ads and objectives are managed here; the campaign can be edited or
 * deleted.
 */
final class CampaignDetailPage extends DashboardComponent
{
    public Campaign $campaign;

    /** '' | campaign | ad | delete-campaign | delete-ad */
    public string $modal = '';

    public string $campaignName = '';

    public string $campaignPlatform = '';

    /** @var list<array{param: string, value: string}> */
    public array $campaignConditions = [];

    public ?int $adId = null;

    public string $adName = '';

    /** @var list<array{param: string, value: string}> */
    public array $adConditions = [];

    /** @var list<array{type: string, reference: string, label: string, value: string|null}> */
    public array $objectives = [];

    public ?int $deleteAdId = null;

    public string $deleteAdLabel = '';

    public function mount(Campaign $campaign): void
    {
        $this->campaign = $campaign;
    }

    public function editCampaign(): void
    {
        $this->campaignName = $this->campaign->name;
        $this->campaignPlatform = (string) $this->campaign->platform;
        $this->campaignConditions = $this->campaign->match_conditions ?: [['param' => '', 'value' => '']];
        $this->modal = 'campaign';
    }

    public function addCampaignCondition(): void
    {
        $this->campaignConditions[] = ['param' => '', 'value' => ''];
    }

    public function removeCampaignCondition(int $index): void
    {
        unset($this->campaignConditions[$index]);
        $this->campaignConditions = array_values($this->campaignConditions);
    }

    public function saveCampaign(): void
    {
        $this->validate([
            'campaignName' => ['required', 'string', 'max:150'],
            'campaignPlatform' => ['nullable', 'string', 'max:60'],
            'campaignConditions' => ['required', 'array', 'min:1'],
            'campaignConditions.*.param' => ['required', 'string', 'max:100'],
            'campaignConditions.*.value' => ['required', 'string', 'max:150'],
        ], attributes: [
            'campaignName' => __('nom'),
            'campaignConditions.*.param' => __('paramètre'),
            'campaignConditions.*.value' => __('valeur'),
        ]);

        $this->campaign->fill([
            'name' => $this->campaignName,
            'platform' => $this->campaignPlatform !== '' ? $this->campaignPlatform : null,
            'match_conditions' => $this->cleanConditions($this->campaignConditions),
        ])->save();

        $this->modal = '';
    }

    public function confirmDeleteCampaign(): void
    {
        $this->modal = 'delete-campaign';
    }

    public function deleteCampaignConfirmed(): void
    {
        $campaignId = $this->campaign->id;

        DB::transaction(function () use ($campaignId): void {
            $adIds = Ad::query()->where('campaign_id', $campaignId)->pluck('id');
            AdObjective::query()->whereIn('ad_id', $adIds)->delete();
            Ad::query()->where('campaign_id', $campaignId)->delete();
            Campaign::query()->whereKey($campaignId)->delete();
        });

        $this->redirect(route(config('analytics.marketing.route_name', 'marketing').'.campaigns'));
    }

    public function newAd(): void
    {
        $this->resetAdForm();
        $this->adConditions = [['param' => '', 'value' => '']];
        $this->modal = 'ad';
    }

    public function editAd(int $id, FunnelRegistry $funnels, EventRegistry $events): void
    {
        $ad = Ad::with('objectives')->where('campaign_id', $this->campaign->id)->findOrFail($id);

        $funnelLabels = [];
        foreach ($funnels->all() as $funnel) {
            $funnelLabels[$funnel->key] = $funnel->label;
        }

        $eventLabels = [];
        foreach ($events->all() as $event) {
            $eventLabels[$event->name] = $event->label;
        }

        $this->adId = $ad->id;
        $this->adName = $ad->name;
        $this->adConditions = $ad->match_conditions ?: [['param' => '', 'value' => '']];
        $this->objectives = $ad->objectives->map(fn (AdObjective $objective): array => [
            'type' => $objective->type->value,
            'reference' => $objective->reference,
            'label' => $objective->type->value === 'funnel'
                ? ($funnelLabels[$objective->reference] ?? $objective->reference)
                : ($eventLabels[$objective->reference] ?? $objective->reference),
            'value' => $objective->type->value === 'event' ? (string) (float) $objective->value : null,
        ])->all();
        $this->modal = 'ad';
    }

    public function addAdCondition(): void
    {
        $this->adConditions[] = ['param' => '', 'value' => ''];
    }

    public function removeAdCondition(int $index): void
    {
        unset($this->adConditions[$index]);
        $this->adConditions = array_values($this->adConditions);
    }

    public function addObjective(string $type, string $reference, string $label, ?float $value = null): void
    {
        foreach ($this->objectives as $objective) {
            if ($objective['type'] === $type && $objective['reference'] === $reference) {
                return;
            }
        }

        $this->objectives[] = [
            'type' => $type,
            'reference' => $reference,
            'label' => $label,
            'value' => $type === 'event' ? (string) ($value ?? 0) : null,
        ];
    }

    public function removeObjective(int $index): void
    {
        unset($this->objectives[$index]);
        $this->objectives = array_values($this->objectives);
    }

    public function saveAd(): void
    {
        $this->validate([
            'adName' => ['required', 'string', 'max:150'],
            'adConditions' => ['required', 'array', 'min:1'],
            'adConditions.*.param' => ['required', 'string', 'max:100'],
            'adConditions.*.value' => ['required', 'string', 'max:150'],
            'objectives.*.value' => ['nullable', 'numeric', 'min:0'],
        ], attributes: [
            'adName' => __('nom'),
            'adConditions.*.param' => __('paramètre'),
            'adConditions.*.value' => __('valeur'),
        ]);

        $ad = $this->adId !== null
            ? Ad::where('campaign_id', $this->campaign->id)->findOrFail($this->adId)
            : new Ad(['campaign_id' => $this->campaign->id]);
        $ad->fill([
            'name' => $this->adName,
            'match_conditions' => $this->cleanConditions($this->adConditions),
        ])->save();

        DB::transaction(function () use ($ad): void {
            AdObjective::query()->where('ad_id', $ad->id)->delete();

            foreach ($this->objectives as $objective) {
                AdObjective::create([
                    'ad_id' => $ad->id,
                    'type' => $objective['type'],
                    'reference' => $objective['reference'],
                    'value' => $objective['type'] === 'event' ? (is_numeric($objective['value']) ? $objective['value'] : 0) : null,
                ]);
            }
        });

        $this->modal = '';
        $this->resetAdForm();
    }

    public function confirmDeleteAd(int $id): void
    {
        $this->deleteAdId = $id;
        $this->deleteAdLabel = (string) Ad::query()->whereKey($id)->value('name');
        $this->modal = 'delete-ad';
    }

    public function deleteAdConfirmed(): void
    {
        if ($this->deleteAdId !== null) {
            $adId = $this->deleteAdId;

            DB::transaction(function () use ($adId): void {
                AdObjective::query()->where('ad_id', $adId)->delete();
                Ad::query()->whereKey($adId)->where('campaign_id', $this->campaign->id)->delete();
            });
        }

        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->modal = '';
        $this->reset('campaignName', 'campaignPlatform', 'campaignConditions', 'deleteAdId', 'deleteAdLabel');
        $this->resetAdForm();
    }

    private function resetAdForm(): void
    {
        $this->reset('adId', 'adName', 'adConditions', 'objectives');
    }

    /**
     * @param  list<array{param: string, value: string}>  $conditions
     * @return list<array{param: string, value: string}>
     */
    private function cleanConditions(array $conditions): array
    {
        $cleaned = array_map(
            fn (array $condition): array => ['param' => trim($condition['param']), 'value' => trim($condition['value'])],
            $conditions,
        );

        return array_values(array_filter($cleaned, fn (array $c): bool => $c['param'] !== '' && $c['value'] !== ''));
    }

    public function render(FunnelRegistry $funnels, EventRegistry $events, MarketingReadRepository $marketing): View
    {
        return $this->guardedRender(
            function () use ($funnels, $events, $marketing): array {
                $selectedFunnels = [];
                $selectedEvents = [];
                foreach ($this->objectives as $objective) {
                    if ($objective['type'] === 'funnel') {
                        $selectedFunnels[] = $objective['reference'];
                    } else {
                        $selectedEvents[] = $objective['reference'];
                    }
                }

                $period = $this->currentPeriod();
                $subjectType = $this->subjectType();
                $report = $marketing->campaignReport($period, $subjectType, $this->campaign);
                $previous = $marketing->campaignReport($period->previous(), $subjectType, $this->campaign);

                $trend = [];
                foreach ($period->eachDay() as $day) {
                    $trend[$day->toDateString()] = $report['daily'][$day->toDateString()] ?? 0;
                }

                $conversions = $marketing->conversions($period, $subjectType, $funnels);
                $conversionsPrevious = $marketing->conversions($period->previous(), $subjectType, $funnels);
                $campaignConversions = $conversions['campaigns'][$this->campaign->id] ?? 0;
                $campaignConversionsPrevious = $conversionsPrevious['campaigns'][$this->campaign->id] ?? 0;
                $rate = $report['visitors'] > 0 ? $campaignConversions / $report['visitors'] * 100 : 0.0;
                $ratePrevious = $previous['visitors'] > 0 ? $campaignConversionsPrevious / $previous['visitors'] * 100 : 0.0;

                return [
                    'range' => $period,
                    'sessions' => $report['sessions'],
                    'visitors' => $report['visitors'],
                    'sessionsDelta' => new MetricDelta((float) $report['sessions'], (float) $previous['sessions']),
                    'visitorsDelta' => new MetricDelta((float) $report['visitors'], (float) $previous['visitors']),
                    'conversions' => $campaignConversions,
                    'conversionsDelta' => new MetricDelta((float) $campaignConversions, (float) $campaignConversionsPrevious),
                    'rateLabel' => number_format($rate, 1, ',', ' ')."\u{00A0}%",
                    'rateDelta' => new MetricDelta($rate, $ratePrevious),
                    'adMetrics' => $report['ads'],
                    'adConversions' => $conversions['ads'],
                    'trendLabels' => array_map(fn (string $d): string => Carbon::parse($d)->isoFormat('D MMM'), array_keys($trend)),
                    'trendData' => array_values($trend),
                    'ads' => $this->campaign->ads()->with('objectives')->orderBy('name')->get(),
                    'objectiveLabels' => $this->objectiveLabels($funnels, $events),
                    'funnelOptions' => array_values(array_filter(
                        array_map(fn ($f): array => ['reference' => $f->key, 'label' => $f->label], $funnels->all()),
                        fn (array $o): bool => ! in_array($o['reference'], $selectedFunnels, true),
                    )),
                    'eventOptions' => array_values(array_filter(
                        array_map(fn ($e): array => ['reference' => $e->name, 'label' => $e->label, 'value' => $e->value], $events->all()),
                        fn (array $o): bool => ! in_array($o['reference'], $selectedEvents, true),
                    )),
                    ...$this->filterData(),
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.marketing-campaign-detail', $data)
                ->layout($this->layoutName('marketing'), ['title' => $this->campaign->name.' · '.__('Marketing')]),
        );
    }

    /**
     * @return array<string, string>
     */
    private function objectiveLabels(FunnelRegistry $funnels, EventRegistry $events): array
    {
        $labels = [];
        foreach ($funnels->all() as $funnel) {
            $labels['funnel:'.$funnel->key] = $funnel->label;
        }
        foreach ($events->all() as $event) {
            $labels['event:'.$event->name] = $event->label;
        }

        return $labels;
    }
}
