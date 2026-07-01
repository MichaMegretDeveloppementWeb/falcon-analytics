<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Models\Session;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * A single session in detail: its main information and the chronological
 * timeline of its events. Read-only; the tabs are handled client-side.
 */
final class SessionDetailPage extends Component
{
    public Session $session;

    public function mount(Session $session): void
    {
        $this->session = $session->load('visitor:id,uuid,subject_type,subject_id,session_count,first_seen_at,last_seen_at');
    }

    public function render(): View
    {
        $events = $this->session->events()
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        return view('analytics::livewire.dashboard.session-detail', [
            'session' => $this->session,
            'events' => $events,
        ])->layout($this->layoutName(), ['title' => __('Session').' · '.__('Analytics')]);
    }

    private function layoutName(): string
    {
        $layout = config('analytics.dashboard.layout');

        return is_string($layout) && $layout !== '' ? $layout : 'analytics::layouts.dashboard';
    }
}
