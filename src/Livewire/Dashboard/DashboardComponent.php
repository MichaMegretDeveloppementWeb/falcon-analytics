<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Livewire\Dashboard\Concerns\RecoversFromReadFailure;
use Falcon\Analytics\Livewire\Dashboard\Concerns\ResolvesDashboardLayout;
use Falcon\Analytics\Services\SubjectResolver;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Shared foundation for the dashboard pages: the period and subject filters
 * (kept in the query string), and the layout resolution that honours the host's
 * configurable shell.
 */
abstract class DashboardComponent extends Component
{
    use RecoversFromReadFailure;
    use ResolvesDashboardLayout;

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
        return $this->subject !== '' ? $this->subject : null;
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
     * Subject filter options, derived from the host's tracked guards. The blank
     * value means "all subjects", including anonymous visitors.
     *
     * @return array<string, string>
     */
    protected function subjectOptions(): array
    {
        $options = ['' => __('Tous')];

        /** @var list<string> $guards */
        $guards = config('analytics.identity.subject_guards', []);
        $subjects = app(SubjectResolver::class);

        foreach ($guards as $guard) {
            $options[$guard] = $subjects->label($guard);
        }

        return $options;
    }
}
