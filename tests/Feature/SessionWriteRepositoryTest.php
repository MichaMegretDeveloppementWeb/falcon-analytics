<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\IngestionContext;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Repositories\SessionWriteRepository;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

final class SessionWriteRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function visitorRow(): Visitor
    {
        return Visitor::create([
            'uuid' => (string) Str::uuid(),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    public function test_it_starts_a_session_mapping_the_resolved_context(): void
    {
        $repository = new SessionWriteRepository;
        $visitor = $this->visitorRow();

        $context = new IngestionContext(
            ip: '85.4.12.66',
            country: 'CH',
            region: 'Geneva',
            city: 'Geneva',
            latitude: 46.2044,
            longitude: 6.1432,
            deviceType: 'desktop',
            browser: 'Firefox',
            os: 'Windows',
            referrer: 'https://google.com',
            source: 'google',
            utmCampaign: 'spring',
            landingRoute: 'home',
            landingUrl: 'https://vantadrive.ch/',
            mktParams: ['src' => 'meta_ete', 'creative' => 'cabrio'],
            subjectType: 'client',
            subjectId: 7,
        );

        $session = $repository->start($visitor, $context, CarbonImmutable::parse('2026-06-30 09:00:00'), $visitor->uuid)->fresh();

        $this->assertSame($visitor->id, $session->visitor_id);
        $this->assertSame($visitor->uuid, $session->browser_key);
        $this->assertSame('2026-06-30 09:00:00', $session->started_at->toDateTimeString());
        $this->assertSame('2026-06-30 09:00:00', $session->last_activity_at->toDateTimeString());
        $this->assertNull($session->ended_at);
        $this->assertSame('85.4.12.66', $session->ip);
        $this->assertSame('CH', $session->country);
        $this->assertSame('Geneva', $session->city);
        $this->assertEqualsWithDelta(46.2044, $session->latitude, 0.00001);
        $this->assertSame('Firefox', $session->browser);
        $this->assertSame('google', $session->source);
        $this->assertSame('spring', $session->utm_campaign);
        $this->assertSame('home', $session->landing_route);
        $this->assertSame(['src' => 'meta_ete', 'creative' => 'cabrio'], $session->mkt_params);
        $this->assertSame('client', $session->subject_type);
        $this->assertSame(7, $session->subject_id);
        $this->assertFalse($session->is_bot);
        $this->assertSame(0, $session->pageview_count);
    }

    public function test_it_records_activity_atomically_with_counters(): void
    {
        $repository = new SessionWriteRepository;
        $visitor = $this->visitorRow();
        $session = $repository->start($visitor, new IngestionContext, CarbonImmutable::parse('2026-06-30 09:00:00'), $visitor->uuid);

        $repository->recordActivity($session, CarbonImmutable::parse('2026-06-30 09:05:00'), pageviewDelta: 2, eventDelta: 5, lastPageviewUrl: 'https://x.test/a');
        $repository->recordActivity($session, CarbonImmutable::parse('2026-06-30 09:08:00'), pageviewDelta: 1, eventDelta: 3, lastPageviewUrl: 'https://x.test/b');

        // Un lot plus ancien arrivé après coup ne doit pas faire reculer
        // l'horodatage, tout en comptant.
        $repository->recordActivity($session, CarbonImmutable::parse('2026-06-30 09:02:00'), pageviewDelta: 1, eventDelta: 1, lastPageviewUrl: 'https://x.test/b');

        $fresh = $session->fresh();

        $this->assertSame(4, $fresh->pageview_count);
        $this->assertSame(9, $fresh->event_count);
        $this->assertSame('https://x.test/b', $fresh->last_pageview_url);
        $this->assertSame('2026-06-30 09:08:00', $fresh->last_activity_at->toDateTimeString());
    }
}
