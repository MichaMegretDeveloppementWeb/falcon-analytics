<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\IngestionContext;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Repositories\SessionWriteRepository;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class SessionWriteRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function visitorRow(): Visitor
    {
        return Visitor::factory()->create();
    }

    public function test_it_starts_a_session_mapping_the_resolved_context(): void
    {
        $repository = new SessionWriteRepository;
        $visitor = $this->visitorRow();

        $context = new IngestionContext(
            ip: '203.0.113.66',
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
            landingUrl: 'https://boutique.test/',
            mktParams: ['src' => 'meta_ete', 'creative' => 'cabrio'],
            subjectType: 'client',
            subjectId: 7,
        );

        $session = $repository->start($visitor, $context, CarbonImmutable::parse('2026-06-30 09:00:00'), $visitor->uuid)->fresh();

        $this->assertNotNull($session, 'The session was just written, it has to read back.');
        $this->assertSame($visitor->id, $session->visitor_id);
        $this->assertSame($visitor->uuid, $session->browser_key);
        $this->assertSame('2026-06-30 09:00:00', $session->started_at->toDateTimeString());
        $this->assertSame('2026-06-30 09:00:00', $session->last_activity_at->toDateTimeString());
        $this->assertNull($session->ended_at);
        $this->assertSame('203.0.113.66', $session->ip);
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

        $repository->recordActivity($session, CarbonImmutable::parse('2026-06-30 09:05:00'), pageviewDelta: 2, clickDelta: 1, eventDelta: 5, lastPageviewUrl: 'https://x.test/a');
        $repository->recordActivity($session, CarbonImmutable::parse('2026-06-30 09:08:00'), pageviewDelta: 1, clickDelta: 2, eventDelta: 3, lastPageviewUrl: 'https://x.test/b');

        // An older batch arriving afterwards still counts, without pushing the timestamp back.
        $repository->recordActivity($session, CarbonImmutable::parse('2026-06-30 09:02:00'), pageviewDelta: 1, clickDelta: 1, eventDelta: 1, lastPageviewUrl: 'https://x.test/b');

        $fresh = $session->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame(4, $fresh->pageview_count);

        // Past the retention no click rows are left, so this counter is all the session can say.
        $this->assertSame(4, $fresh->click_count);

        $this->assertSame(9, $fresh->event_count);
        $this->assertSame('https://x.test/b', $fresh->last_pageview_url);
        $this->assertSame('2026-06-30 09:08:00', $fresh->last_activity_at->toDateTimeString());
    }

    /** The subject a vouched send carries is taken whole · the type and the id, in one statement. */
    public function test_a_session_without_a_subject_takes_the_one_vouched_for(): void
    {
        $session = $this->anonymousSession();

        $this->record($session, CarbonImmutable::parse('2026-06-30 09:05:00'), ['type' => 'client', 'id' => 7]);

        $this->assertSame(['client', 7], $this->subjectOf($session));
    }

    /** Without a vouching the send names nobody, whatever subject it carries. */
    public function test_an_unvouched_send_names_nobody(): void
    {
        $session = $this->anonymousSession();

        $this->record($session, null, ['type' => 'client', 'id' => 7]);

        $this->assertSame([null, null], $this->subjectOf($session));
    }

    /** A session that has a subject never changes it. */
    public function test_a_session_keeps_the_subject_it_has(): void
    {
        $session = $this->anonymousSession();

        $this->record($session, CarbonImmutable::parse('2026-06-30 09:05:00'), ['type' => 'client', 'id' => 7]);
        $this->record($session, CarbonImmutable::parse('2026-06-30 09:06:00'), ['type' => 'lessor', 'id' => 9]);

        $this->assertSame(['client', 7], $this->subjectOf($session));
    }

    private function anonymousSession(): Session
    {
        $visitor = $this->visitorRow();

        return (new SessionWriteRepository)->start($visitor, new IngestionContext, CarbonImmutable::parse('2026-06-30 09:00:00'), $visitor->uuid);
    }

    /** @param  array{type: string, id: int}  $subject */
    private function record(Session $session, ?CarbonImmutable $confirmedAt, array $subject): void
    {
        (new SessionWriteRepository)->recordActivity($session, CarbonImmutable::parse('2026-06-30 09:05:00'), 1, 0, 1, null, $confirmedAt, $subject);
    }

    /** @return array{0: string|null, 1: int|null} */
    private function subjectOf(Session $session): array
    {
        $fresh = $session->fresh();

        $this->assertNotNull($fresh);

        return [$fresh->subject_type, $fresh->subject_id];
    }
}
