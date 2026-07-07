<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Livewire\Dashboard\Concerns\EditsAd;
use Falcon\Analytics\Models\Ad;
use Illuminate\Contracts\View\View;

/**
 * A single ad in detail: its parent campaign, URL conditions and an in-place editor
 * render immediately; its headline traffic, trend and conversion breakdown load in a
 * deferred widget after the shell paints.
 */
final class AdDetailPage extends DashboardComponent
{
    use EditsAd;

    public Ad $ad;

    /** '' | ad */
    public string $modal = '';

    public function mount(Ad $ad): void
    {
        $this->ad = $ad->load(['campaign', 'objectives']);
    }

    protected function adFormCampaignId(): int
    {
        return $this->ad->campaign_id;
    }

    public function editAd(FunnelRegistry $funnels, EventRegistry $events): void
    {
        $this->fillAdForm($this->ad, $funnels, $events);
        $this->modal = 'ad';
    }

    public function closeModal(): void
    {
        $this->modal = '';
        $this->resetValidation();
    }

    protected function afterAdSaved(): void
    {
        $this->ad->refresh()->load(['campaign', 'objectives']);
    }

    public function render(FunnelRegistry $funnels, EventRegistry $events): View
    {
        return $this->guardedRender(
            fn (): array => [
                'range' => $this->currentPeriod(),
                ...$this->adFormOptions($funnels, $events),
                ...$this->filterData(),
            ],
            fn (array $data): View => view('analytics::livewire.dashboard.marketing-ad-detail', $data)
                ->layout($this->layoutName('marketing'), ['title' => $this->ad->name.' · '.__('Marketing')]),
        );
    }
}
