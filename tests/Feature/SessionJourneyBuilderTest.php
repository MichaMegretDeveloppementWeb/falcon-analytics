<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Services\Dashboard\SessionJourneyBuilder;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Support\Collection;

final class SessionJourneyBuilderTest extends TestCase
{
    private function event(EventType $type, string $at, ?string $route = null, ?string $url = null): Event
    {
        return (new Event)->forceFill([
            'type' => $type,
            'occurred_at' => CarbonImmutable::parse($at),
            'route' => $route,
            'url' => $url,
        ]);
    }

    public function test_it_nests_non_pageview_events_under_their_page_and_partitions_the_session_window(): void
    {
        $start = CarbonImmutable::parse('2026-07-02 12:00:00');
        $end = CarbonImmutable::parse('2026-07-02 12:03:00');

        $events = new Collection([
            $this->event(EventType::Pageview, '2026-07-02 12:00:00', 'home'),
            $this->event(EventType::Click, '2026-07-02 12:00:30', 'home'),
            $this->event(EventType::Pageview, '2026-07-02 12:01:00', 'catalog'),
        ]);

        $journey = (new SessionJourneyBuilder)->build($events, $start, $end);

        $this->assertCount(2, $journey, 'deux pages vues, donc deux etapes');
        $this->assertSame('home', $journey[0]['event']->route);
        $this->assertCount(1, $journey[0]['children'], 'le clic se range sous home');
        $this->assertSame(60, $journey[0]['seconds'], '12:00:00 vers 12:01:00');
        $this->assertSame('catalog', $journey[1]['event']->route);
        $this->assertCount(0, $journey[1]['children']);
        $this->assertSame(120, $journey[1]['seconds'], '12:01:00 vers la fin de la fenetre');
    }

    public function test_it_sums_the_time_per_page_across_repeat_visits_most_first(): void
    {
        $start = CarbonImmutable::parse('2026-07-02 12:00:00');
        $end = CarbonImmutable::parse('2026-07-02 12:05:00');

        $events = new Collection([
            $this->event(EventType::Pageview, '2026-07-02 12:00:00', 'home', 'https://x.test/home'),       // 60 s
            $this->event(EventType::Pageview, '2026-07-02 12:01:00', 'catalog', 'https://x.test/catalog'), // 180 s
            $this->event(EventType::Pageview, '2026-07-02 12:04:00', 'home', 'https://x.test/home'),       // 60 s
        ]);

        $builder = new SessionJourneyBuilder;
        $perPage = $builder->timePerPage($builder->build($events, $start, $end));

        $this->assertSame(['/catalog' => 180, '/home' => 120], $perPage);
    }
}
