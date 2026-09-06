<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Concerns;

use Falcon\Analytics\Actions\SaveCampaignAction;
use Falcon\Analytics\Models\Campaign;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shared campaign-editing form (name, platform and URL conditions) used by both
 * the campaigns list and the campaign detail screens, so a campaign can be
 * created or edited from either place with identical behaviour. The host
 * component owns the `$modal` state and tells the trait which campaign is
 * being saved.
 */
trait EditsCampaign
{
    public ?int $campaignId = null;

    public string $campaignName = '';

    public string $campaignPlatform = '';

    /*
     * `array<int, …>` et non `list<…>` · la suppression d'une ligne fait un
     * `unset` sans reindexer, expres, pour ne pas deplacer le `wire:key` d'un
     * champ sur une autre ligne. Voir {@see EditsAd} pour le detail.
     */

    /** @var array<int, array{param: string, value: string}> */
    public array $campaignConditions = [];

    /**
     * The id of the campaign being saved (null creates one). Kept abstract so
     * the detail page can answer from its route-bound model rather than a
     * client-forgeable property.
     */
    abstract protected function campaignFormId(): ?int;

    protected function blankCampaignForm(): void
    {
        $this->resetCampaignForm();
        $this->campaignConditions = [['param' => '', 'value' => '']];
    }

    protected function fillCampaignForm(Campaign $campaign): void
    {
        $this->campaignId = $campaign->id;
        $this->campaignName = $campaign->name;
        $this->campaignPlatform = (string) $campaign->platform;
        $this->campaignConditions = $campaign->match_conditions ?: [['param' => '', 'value' => '']];
    }

    public function addCampaignCondition(): void
    {
        $this->campaignConditions[] = ['param' => '', 'value' => ''];
    }

    public function removeCampaignCondition(int $index): void
    {
        // Keep the surviving rows' keys stable (no array_values reindex) so Livewire
        // does not shift the wire:key of a wire:model input onto another row and
        // break Alpine's cleanup on morph.
        unset($this->campaignConditions[$index]);
    }

    public function saveCampaign(SaveCampaignAction $action): void
    {
        $this->validate([
            'campaignName' => ['required', 'string', 'max:150'],
            'campaignPlatform' => ['nullable', 'string', 'max:60'],
            'campaignConditions' => ['required', 'array', 'min:1'],
            'campaignConditions.*.param' => ['required', 'string', 'max:100'],
            'campaignConditions.*.value' => ['required', 'string', 'max:150'],
        ], messages: [
            'campaignName.required' => __('Le nom est obligatoire.'),
            'campaignName.max' => __('Le nom ne doit pas dépasser :max caractères.'),
            'campaignPlatform.max' => __('La plateforme ne doit pas dépasser :max caractères.'),
            'campaignConditions.required' => __('Ajoutez au moins une condition.'),
            'campaignConditions.min' => __('Ajoutez au moins une condition.'),
            'campaignConditions.*.param.required' => __('Le paramètre est obligatoire.'),
            'campaignConditions.*.param.max' => __('Le paramètre ne doit pas dépasser :max caractères.'),
            'campaignConditions.*.value.required' => __('La valeur est obligatoire.'),
            'campaignConditions.*.value.max' => __('La valeur ne doit pas dépasser :max caractères.'),
        ], attributes: [
            'campaignName' => __('nom'),
            'campaignConditions.*.param' => __('paramètre'),
            'campaignConditions.*.value' => __('valeur'),
        ]);

        try {
            // `array_values` a l'enregistrement, et seulement la · les trous
            // laisses par `unset` servent au formulaire vivant, pas a ce qui
            // part en base.
            $action->execute(
                $this->campaignFormId(),
                $this->campaignName,
                $this->campaignPlatform !== '' ? $this->campaignPlatform : null,
                $this->cleanCampaignConditions(array_values($this->campaignConditions)),
            );
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Campaign.save_failed', [
                'campaign_id' => $this->campaignFormId(),
                'exception' => $e,
            ]);
            $this->dispatch('toast', type: 'danger', title: __('L\'enregistrement de la campagne a échoué. Réessayez.'));

            return;
        }

        // The modal only closes; the form is repopulated on the next open
        // (newCampaign / editCampaign). Resetting the form arrays here would
        // remove their wire:model rows during the same morph and break Alpine's
        // pending model-update flush.
        $this->modal = '';
        $this->afterCampaignSaved();
    }

    protected function afterCampaignSaved(): void {}

    protected function resetCampaignForm(): void
    {
        $this->reset('campaignId', 'campaignName', 'campaignPlatform', 'campaignConditions');
    }

    /**
     * @param  list<array{param: string, value: string}>  $conditions
     * @return list<array{param: string, value: string}>
     */
    protected function cleanCampaignConditions(array $conditions): array
    {
        $cleaned = array_map(
            fn (array $condition): array => ['param' => trim($condition['param']), 'value' => trim($condition['value'])],
            $conditions,
        );

        return array_values(array_filter($cleaned, fn (array $c): bool => $c['param'] !== '' && $c['value'] !== ''));
    }
}
