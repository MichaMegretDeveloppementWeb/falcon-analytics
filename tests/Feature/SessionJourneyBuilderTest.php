<?php

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Services\Dashboard\SessionJourneyBuilder;
use Illuminate\Support\Collection;

function journeyEvent(EventType $type, string $at, ?string $route = null, ?string $url = null): Event
{
    return (new Event)->forceFill([
        'type' => $type,
        'occurred_at' => CarbonImmutable::parse($at),
        'route' => $route,
        'url' => $url,
    ]);
}

it('nests non-pageview events under their page and partitions the session window', function () {
    $start = CarbonImmutable::parse('2026-07-02 12:00:00');
    $end = CarbonImmutable::parse('2026-07-02 12:03:00');

    $events = new Collection([
        journeyEvent(EventType::Pageview, '2026-07-02 12:00:00', 'home'),
        journeyEvent(EventType::Click, '2026-07-02 12:00:30', 'home'),
        journeyEvent(EventType::Pageview, '2026-07-02 12:01:00', 'catalog'),
    ]);

    $journey = (new SessionJourneyBuilder)->build($events, $start, $end);

    expect($journey)->toHaveCount(2)                     // two pageviews = two steps
        ->and($journey[0]['event']->route)->toBe('home')
        ->and($journey[0]['children'])->toHaveCount(1)   // the click nests under home
        ->and($journey[0]['seconds'])->toBe(60)          // 12:00:00 -> 12:01:00
        ->and($journey[1]['event']->route)->toBe('catalog')
        ->and($journey[1]['children'])->toHaveCount(0)
        ->and($journey[1]['seconds'])->toBe(120);        // 12:01:00 -> 12:03:00 (window end)
});

it('sums the time per page across repeat visits, most first', function () {
    $start = CarbonImmutable::parse('2026-07-02 12:00:00');
    $end = CarbonImmutable::parse('2026-07-02 12:05:00');

    $events = new Collection([
        journeyEvent(EventType::Pageview, '2026-07-02 12:00:00', 'home', 'https://x.test/home'),       // 60s
        journeyEvent(EventType::Pageview, '2026-07-02 12:01:00', 'catalog', 'https://x.test/catalog'), // 180s
        journeyEvent(EventType::Pageview, '2026-07-02 12:04:00', 'home', 'https://x.test/home'),       // 60s
    ]);

    $builder = new SessionJourneyBuilder;
    $perPage = $builder->timePerPage($builder->build($events, $start, $end));

    expect($perPage)->toBe(['/catalog' => 180, '/home' => 120]);
});
