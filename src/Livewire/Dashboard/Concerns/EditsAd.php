<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Concerns;

use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\AdObjective;
use Illuminate\Support\Facades\DB;

/**
 * Shared ad-editing form (name, URL conditions and conversion objectives) used by
 * both the campaign detail and the ad detail screens, so an ad can be created or
 * edited from either place with identical behaviour. The host component owns the
 * `$modal` state and provides the campaign the ad belongs to.
 */
trait EditsAd
{
    public ?int $adId = null;

    public string $adName = '';

    /** @var list<array{param: string, value: string}> */
    public array $adConditions = [];

    /** @var list<array{type: string, reference: string, label: string, value: string|null}> */
    public array $objectives = [];

    abstract protected function adFormCampaignId(): int;

    protected function blankAdForm(): void
    {
        $this->resetAdForm();
        $this->adConditions = [['param' => '', 'value' => '']];
    }

    protected function fillAdForm(Ad $ad, FunnelRegistry $funnels, EventRegistry $events): void
    {
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
            ? Ad::findOrFail($this->adId)
            : new Ad(['campaign_id' => $this->adFormCampaignId()]);
        $ad->fill([
            'name' => $this->adName,
            'match_conditions' => $this->cleanAdConditions($this->adConditions),
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
        $this->afterAdSaved();
    }

    protected function afterAdSaved(): void {}

    protected function resetAdForm(): void
    {
        $this->reset('adId', 'adName', 'adConditions', 'objectives');
    }

    /**
     * The tunnel and event options offered by the objective pickers, excluding the
     * ones already selected on the ad.
     *
     * @return array{funnelOptions: list<array{reference: string, label: string}>, eventOptions: list<array{reference: string, label: string, value: float|null}>}
     */
    protected function adFormOptions(FunnelRegistry $funnels, EventRegistry $events): array
    {
        $selectedFunnels = [];
        $selectedEvents = [];
        foreach ($this->objectives as $objective) {
            if ($objective['type'] === 'funnel') {
                $selectedFunnels[] = $objective['reference'];
            } else {
                $selectedEvents[] = $objective['reference'];
            }
        }

        return [
            'funnelOptions' => array_values(array_filter(
                array_map(fn ($funnel): array => ['reference' => $funnel->key, 'label' => $funnel->label], $funnels->all()),
                fn (array $option): bool => ! in_array($option['reference'], $selectedFunnels, true),
            )),
            'eventOptions' => array_values(array_filter(
                array_map(fn ($event): array => ['reference' => $event->name, 'label' => $event->label, 'value' => $event->value], $events->all()),
                fn (array $option): bool => ! in_array($option['reference'], $selectedEvents, true),
            )),
        ];
    }

    /**
     * @param  list<array{param: string, value: string}>  $conditions
     * @return list<array{param: string, value: string}>
     */
    protected function cleanAdConditions(array $conditions): array
    {
        $cleaned = array_map(
            fn (array $condition): array => ['param' => trim($condition['param']), 'value' => trim($condition['value'])],
            $conditions,
        );

        return array_values(array_filter($cleaned, fn (array $c): bool => $c['param'] !== '' && $c['value'] !== ''));
    }
}
