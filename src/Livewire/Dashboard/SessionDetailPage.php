<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

/**
 * A single session in detail: its main information and the chronological journey
 * (pages visited, clicks nested under their page, time spent). Read-only.
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

        $journey = $this->buildJourney($events);

        return view('analytics::livewire.dashboard.session-detail', [
            'session' => $this->session,
            'journey' => $journey,
            'clicksCount' => $events->where('type', EventType::Click)->count(),
            'timePerPage' => $this->timePerPage($journey),
        ])->layout($this->layoutName(), ['title' => __('Session').' · '.__('Analytics')]);
    }

    /**
     * Seconds spent per page (route resolved to a clean URL), aggregated across
     * repeat visits and sorted from most to least time.
     *
     * @param  list<array{event: Event, children: list<Event>, seconds: int}>  $journey
     * @return array<string, int>
     */
    private function timePerPage(array $journey): array
    {
        $byPage = [];

        foreach ($journey as $step) {
            if ($step['event']->type !== EventType::Pageview) {
                continue;
            }

            $route = $step['event']->route;
            $uri = $route !== null ? Route::getRoutes()->getByName($route)?->uri() : null;
            $label = $uri !== null ? '/'.ltrim($uri, '/') : ($route ?? $step['event']->url ?? '—');

            $byPage[$label] = ($byPage[$label] ?? 0) + $step['seconds'];
        }

        arsort($byPage);

        return $byPage;
    }

    /**
     * Group events into a journey: each pageview is a step, other events nest
     * under the page they happened on, and each step carries the seconds spent
     * before the next step (or the end of the session).
     *
     * @param  Collection<int, Event>  $events
     * @return list<array{event: Event, children: list<Event>, seconds: int}>
     */
    private function buildJourney(Collection $events): array
    {
        $journey = [];

        foreach ($events as $event) {
            if ($event->type === EventType::Pageview || $journey === []) {
                $journey[] = ['event' => $event, 'children' => [], 'seconds' => 0];

                continue;
            }

            $journey[array_key_last($journey)]['children'][] = $event;
        }

        foreach ($journey as $index => $step) {
            $end = $journey[$index + 1]['event']->occurred_at ?? $this->session->last_activity_at;
            $journey[$index]['seconds'] = max(0, (int) $step['event']->occurred_at->diffInSeconds($end));
        }

        return $journey;
    }

    private function layoutName(): string
    {
        $layout = config('analytics.dashboard.layout');

        return is_string($layout) && $layout !== '' ? $layout : 'analytics::layouts.dashboard';
    }
}
