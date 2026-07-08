<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Concerns;

use Falcon\Analytics\Actions\SaveAdAction;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\AdObjective;
use Illuminate\Support\Facades\Log;
use Throwable;

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

    /** @var list<array{type: string, reference: string, label: string}> */
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
        ])->all();
    }

    public function addAdCondition(): void
    {
        $this->adConditions[] = ['param' => '', 'value' => ''];
    }

    public function removeAdCondition(int $index): void
    {
        // Keep the surviving rows' keys stable (no array_values reindex) so Livewire
        // does not shift the wire:key of a wire:model input onto another row and
        // break Alpine's cleanup on morph.
        unset($this->adConditions[$index]);
    }

    public function addObjective(string $type, string $reference, string $label): void
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
        ];
    }

    public function removeObjective(int $index): void
    {
        unset($this->objectives[$index]);
    }

    public function saveAd(SaveAdAction $action): void
    {
        $this->validate([
            'adName' => ['required', 'string', 'max:150'],
            'adConditions' => ['required', 'array', 'min:1'],
            'adConditions.*.param' => ['required', 'string', 'max:100'],
            'adConditions.*.value' => ['required', 'string', 'max:150'],
        ], messages: [
            'adName.required' => __('Le nom est obligatoire.'),
            'adName.max' => __('Le nom ne doit pas dépasser :max caractères.'),
            'adConditions.required' => __('Ajoutez au moins une condition.'),
            'adConditions.min' => __('Ajoutez au moins une condition.'),
            'adConditions.*.param.required' => __('Le paramètre est obligatoire.'),
            'adConditions.*.value.required' => __('La valeur est obligatoire.'),
        ], attributes: [
            'adName' => __('nom'),
            'adConditions.*.param' => __('paramètre'),
            'adConditions.*.value' => __('valeur'),
        ]);

        try {
            $action->execute(
                $this->adId,
                $this->adFormCampaignId(),
                $this->adName,
                $this->cleanAdConditions($this->adConditions),
                $this->objectives,
            );
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Ad.save_failed', [
                'ad_id' => $this->adId,
                'exception' => $e,
            ]);
            $this->dispatch('toast', type: 'danger', title: __('L\'enregistrement de la pub a échoué. Réessayez.'));

            return;
        }

        // The modal only closes; the form is repopulated on the next open (newAd /
        // editAd). Resetting the form arrays here would remove their wire:model rows
        // during the same morph and break Alpine's pending model-update flush.
        $this->modal = '';
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
     * @return array{funnelOptions: list<array{reference: string, label: string}>, eventOptions: list<array{reference: string, label: string}>}
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
                array_map(fn ($event): array => ['reference' => $event->name, 'label' => $event->label], $events->all()),
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
