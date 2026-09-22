<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin;

use Falcon\Analytics\Actions\SaveCampaignAction;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Support\UrlConditions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * The campaign form, a component of its own under the campaigns list and on a
 * campaign's page · opening it, adding a condition or a refused save draw the
 * form alone. What it writes, it announces by `an-campaigns-changed`, and the
 * screen around it reads itself again then.
 *
 * @internal
 */
final class CampaignForm extends Component
{
    /** Put back to their defaults on every opening, so the form never carries the previous campaign. */
    private const FORM_FIELDS = ['campaignId', 'campaignName', 'campaignPlatform', 'campaignConditions'];

    /** The campaign being edited, null for a new one · set by the opening alone. */
    #[Locked]
    public ?int $campaignId = null;

    public string $campaignName = '';

    public string $campaignPlatform = '';

    /** @var array<int, array{param: string, value: string}> */
    public array $campaignConditions = [];

    /**
     * Fills the form, blank when no campaign is named · the modal opens on a
     * yes, so a campaign that could not be read never opens as a new one.
     */
    public function editCampaign(?int $campaignId = null): bool
    {
        $this->resetValidation();
        $this->reset(self::FORM_FIELDS);

        if ($campaignId === null) {
            $this->campaignConditions = [UrlConditions::BLANK];

            return true;
        }

        try {
            $campaign = Campaign::query()->findOrFail($campaignId);
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Campaign.edit_load_failed', [
                'campaign_id' => $campaignId,
                'exception' => $e,
            ]);
            $this->dispatch('ui-toast', type: 'danger', title: __('Cette campagne est introuvable. Actualisez la page.'));

            return false;
        }

        $this->campaignId = $campaign->id;
        $this->campaignName = $campaign->name;
        $this->campaignPlatform = (string) $campaign->platform;
        $this->campaignConditions = UrlConditions::toEdit($campaign->match_conditions);

        return true;
    }

    public function addCampaignCondition(): void
    {
        $this->campaignConditions[] = UrlConditions::BLANK;
    }

    public function removeCampaignCondition(int $index): void
    {
        unset($this->campaignConditions[$index]);
    }

    /**
     * Whether the campaign was saved · the modal closes on a yes, and only
     * then. The fields are not cleared here: the next opening refills them, and
     * clearing them now would take their rows away in the middle of the morph
     * that closes the modal.
     */
    public function saveCampaign(SaveCampaignAction $action): bool
    {
        $this->validate();

        try {
            $action->execute(
                $this->campaignId,
                $this->campaignName,
                $this->campaignPlatform !== '' ? $this->campaignPlatform : null,
                UrlConditions::cleaned($this->campaignConditions),
            );
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Campaign.save_failed', [
                'campaign_id' => $this->campaignId,
                'exception' => $e,
            ]);
            $this->dispatch('ui-toast', type: 'danger', title: __('L\'enregistrement de la campagne a échoué. Réessayez.'));

            return false;
        }

        $this->dispatch('an-campaigns-changed');

        return true;
    }

    public function render(): View
    {
        return view('analytics::livewire.dashboard.marketing-campaign-form');
    }

    /**
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        return [
            'campaignName' => ['required', 'string', 'max:150'],
            'campaignPlatform' => ['nullable', 'string', 'max:60'],
            ...UrlConditions::rules('campaignConditions'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'campaignName.required' => __('Le nom est obligatoire.'),
            'campaignName.max' => __('Le nom ne doit pas dépasser :max caractères.'),
            'campaignPlatform.max' => __('La plateforme ne doit pas dépasser :max caractères.'),
            ...UrlConditions::messages('campaignConditions'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['campaignName' => __('nom'), ...UrlConditions::attributes('campaignConditions')];
    }
}
