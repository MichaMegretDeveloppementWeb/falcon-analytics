<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Livewire\Admin\Concerns\RecoversFromReadFailure;
use Falcon\Analytics\Services\SubjectResolver;
use Falcon\Analytics\Support\SubjectFilter;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Shared foundation for the dashboard pages: the period and subject filters
 * (kept in the query string), and the layout resolution that honours the host's
 * configurable shell.
 *
 * @internal like every screen of this package. The provider registers them
 *           under `analytics::…` for the package's own pages, not for a host's ·
 *           a screen expects a whole page around it, its layout and its assets,
 *           and laid elsewhere it comes out wrong with nothing to say so. What
 *           this package promises are the addresses of its pages.
 */
abstract class DashboardComponent extends Component
{
    use RecoversFromReadFailure;

    #[Url]
    public int $period = Period::DEFAULT_DAYS;

    #[Url]
    public string $subject = '';

    protected function currentPeriod(): Period
    {
        return Period::ofDays($this->period);
    }

    protected function subjectType(): ?string
    {
        return SubjectFilter::chosen($this->subject);
    }

    /**
     * Data every page needs to render the shared filter bar.
     *
     * @return array{periodOptions: array<int, string>, subjectOptions: array<string, string>}
     */
    protected function filterData(): array
    {
        return [
            'periodOptions' => $this->periodOptions(),
            'subjectOptions' => $this->subjectOptions(),
        ];
    }

    /**
     * Period options for the range selector: window in days, human label.
     *
     * @return array<int, string>
     */
    protected function periodOptions(): array
    {
        $options = [];

        foreach (Period::ALLOWED_DAYS as $days) {
            $options[$days] = __(':count derniers jours', ['count' => $days]);
        }

        return $options;
    }

    /**
     * Subject filter options, derived from the host's tracked guards · the blank
     * value means every visitor, and the visitors no guard named have their own
     * choice beside the guards.
     *
     * @return array<string, string>
     */
    protected function subjectOptions(): array
    {
        $subjects = app(SubjectResolver::class);
        $guards = $subjects->guards();

        if ($guards === []) {
            return ['' => __('Tous')];
        }

        $options = ['' => __('Tous')];

        foreach ($guards as $guard) {
            $options[$guard] = $subjects->label($guard);
        }

        return [...$options, SubjectFilter::NOBODY => $subjects->nobodyLabel()];
    }
}
