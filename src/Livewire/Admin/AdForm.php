<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin;

use Falcon\Analytics\Actions\SaveAdAction;
use Falcon\Analytics\DTOs\Dashboard\Marketing\ObjectiveTag;
use Falcon\Analytics\Enums\ObjectiveType;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Services\Dashboard\ObjectiveLabels;
use Falcon\Analytics\Support\UrlConditions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * The ad form, a component of its own on a campaign's page and on an ad's ·
 * opening it, adding a condition or an objective, or a refused save draw the
 * form alone. It edits the ads of one campaign, the one it is laid for. What
 * it writes, it announces by `an-ads-changed`, and the screen around it reads
 * itself again then.
 *
 * @internal
 */
final class AdForm extends Component
{
    /** Put back to their defaults on every opening, so the form never carries the previous ad. */
    private const FORM_FIELDS = ['adId', 'adName', 'adConditions', 'objectives'];

    #[Locked]
    public int $campaignId;

    /** The ad being edited, null for a new one · set by the opening alone. */
    #[Locked]
    public ?int $adId = null;

    public string $adName = '';

    /** @var array<int, array{param: string, value: string}> */
    public array $adConditions = [];

    /** @var array<int, array{type: string, reference: string, label: string}> */
    public array $objectives = [];

    public function mount(int $campaignId): void
    {
        $this->campaignId = $campaignId;
    }

    /**
     * Fills the form, blank when no ad is named · the modal opens on a yes, so
     * an ad that could not be read, or that belongs to another campaign, never
     * opens as a new one.
     */
    public function editAd(ObjectiveLabels $labels, ?int $adId = null): bool
    {
        $this->resetValidation();
        $this->reset(self::FORM_FIELDS);

        if ($adId === null) {
            $this->adConditions = [UrlConditions::BLANK];

            return true;
        }

        try {
            $ad = Ad::with('objectives')->where('campaign_id', $this->campaignId)->findOrFail($adId);
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Ad.edit_load_failed', [
                'ad_id' => $adId,
                'exception' => $e,
            ]);
            $this->dispatch('ui-toast', type: 'danger', title: __('Cette publicité est introuvable. Actualisez la page.'));

            return false;
        }

        $this->adId = $ad->id;
        $this->adName = $ad->name;
        $this->adConditions = UrlConditions::toEdit($ad->match_conditions);
        $this->objectives = $this->objectivesOf($ad, $labels);

        return true;
    }

    public function addAdCondition(): void
    {
        $this->adConditions[] = UrlConditions::BLANK;
    }

    public function removeAdCondition(int $index): void
    {
        unset($this->adConditions[$index]);
    }

    public function addObjective(string $type, string $reference, string $label): void
    {
        foreach ($this->objectives as $objective) {
            if ($objective['type'] === $type && $objective['reference'] === $reference) {
                return;
            }
        }

        $this->objectives[] = ['type' => $type, 'reference' => $reference, 'label' => $label];
    }

    public function removeObjective(int $index): void
    {
        unset($this->objectives[$index]);
    }

    /**
     * Whether the ad was saved · the modal closes on a yes, and only then. The
     * fields are not cleared here: the next opening refills them, and clearing
     * them now would take their rows away in the middle of the morph that
     * closes the modal.
     */
    public function saveAd(SaveAdAction $action): bool
    {
        $this->validate();

        try {
            $action->execute(
                $this->adId,
                $this->campaignId,
                $this->adName,
                UrlConditions::cleaned($this->adConditions),
                array_values($this->objectives),
            );
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Ad.save_failed', [
                'ad_id' => $this->adId,
                'exception' => $e,
            ]);
            $this->dispatch('ui-toast', type: 'danger', title: __('L\'enregistrement de la publicité a échoué. Réessayez.'));

            return false;
        }

        $this->dispatch('an-ads-changed');

        return true;
    }

    public function render(FunnelRegistry $funnels, EventRegistry $events): View
    {
        return view('analytics::livewire.dashboard.marketing-ad-form', $this->pickerOptions($funnels, $events));
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'adName' => ['required', 'string', 'max:150'],
            ...UrlConditions::rules('adConditions'),
            // Objectives are added through wire:click, whose payload is forgeable:
            // an arbitrary type would later crash every read on the enum cast.
            'objectives' => ['array'],
            'objectives.*.type' => ['required', Rule::enum(ObjectiveType::class)],
            'objectives.*.reference' => ['required', 'string', 'max:191'],
            'objectives.*.label' => ['required', 'string', 'max:191'],
        ];
    }

    /**
     * One message for every refused objective · it can only come from a forged
     * call, and the way out is the same whatever was forged.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        $invalid = __('Cet objectif est invalide. Retirez-le puis choisissez-le depuis les listes.');

        return [
            'adName.required' => __('Le nom est obligatoire.'),
            'adName.max' => __('Le nom ne doit pas dépasser :max caractères.'),
            ...UrlConditions::messages('adConditions'),
            'objectives.*.type.required' => $invalid,
            'objectives.*.type.enum' => $invalid,
            'objectives.*.reference.required' => $invalid,
            'objectives.*.reference.max' => $invalid,
            'objectives.*.label.required' => $invalid,
            'objectives.*.label.max' => $invalid,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'adName' => __('nom'),
            ...UrlConditions::attributes('adConditions'),
            'objectives.*.type' => __('objectif'),
            'objectives.*.reference' => __('objectif'),
            'objectives.*.label' => __('objectif'),
        ];
    }

    /**
     * The ad's objectives as the form lists them, named.
     *
     * @return list<array{type: string, reference: string, label: string}>
     */
    private function objectivesOf(Ad $ad, ObjectiveLabels $labels): array
    {
        return array_map(
            static fn (ObjectiveTag $tag): array => ['type' => $tag->type->value, 'reference' => $tag->reference, 'label' => $tag->label],
            $labels->tagsOf($ad->objectives),
        );
    }

    /**
     * The funnels and events the two pickers offer, less the ones the ad
     * already counts.
     *
     * @return array{funnelOptions: list<array{reference: string, label: string}>, eventOptions: list<array{reference: string, label: string}>}
     */
    private function pickerOptions(FunnelRegistry $funnels, EventRegistry $events): array
    {
        $chosen = [];
        foreach ($this->objectives as $objective) {
            $chosen[$objective['type'] === 'funnel' ? 'funnel' : 'event'][$objective['reference']] = true;
        }

        $funnelOptions = [];
        foreach ($funnels->all() as $funnel) {
            if (! isset($chosen['funnel'][$funnel->key])) {
                $funnelOptions[] = ['reference' => $funnel->key, 'label' => $funnel->label];
            }
        }

        $eventOptions = [];
        foreach ($events->all() as $event) {
            if (! isset($chosen['event'][$event->name])) {
                $eventOptions[] = ['reference' => $event->name, 'label' => $event->label];
            }
        }

        return ['funnelOptions' => $funnelOptions, 'eventOptions' => $eventOptions];
    }
}
