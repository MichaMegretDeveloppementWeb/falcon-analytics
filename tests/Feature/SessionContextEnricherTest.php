<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\IncomingBatch;
use Falcon\Analytics\DTOs\IncomingEvent;
use Falcon\Analytics\DTOs\RequestSnapshot;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Services\SessionContextEnricher;
use Falcon\Analytics\Tests\TestCase;

final class SessionContextEnricherTest extends TestCase
{
    private function pageviewBatch(?string $url = null, ?string $referrer = null): IncomingBatch
    {
        return new IncomingBatch(
            events: [new IncomingEvent(type: EventType::Pageview, occurredAt: CarbonImmutable::now(), route: 'home', url: $url)],
            referrer: $referrer,
        );
    }

    public function test_it_builds_a_full_context_from_the_snapshot_and_batch(): void
    {
        $snapshot = new RequestSnapshot(
            ip: '85.4.12.66',
            userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:128.0) Gecko/20100101 Firefox/128.0',
            host: 'vantadrive.ch',
        );

        $context = app(SessionContextEnricher::class)->enrich(
            $snapshot,
            $this->pageviewBatch(
                'https://vantadrive.ch/?utm_source=meta&utm_medium=cpc&utm_campaign=spring',
                'https://facebook.com/',
            ),
            ['type' => 'client', 'id' => 7],
        );

        $this->assertSame('client', $context->subjectType);
        $this->assertSame(7, $context->subjectId);
        $this->assertSame('Firefox', $context->browser);
        $this->assertSame('Windows', $context->os);
        $this->assertFalse($context->isBot);
        $this->assertSame('85.4.12.66', $context->ip);
        $this->assertSame('paid', $context->source);
        $this->assertSame('spring', $context->utmCampaign);
        $this->assertSame('home', $context->landingRoute);
        $this->assertSame('https://facebook.com/', $context->referrer);
    }

    public function test_it_flags_a_bot_request(): void
    {
        $snapshot = new RequestSnapshot(
            ip: '66.249.66.1',
            userAgent: 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            host: 'vantadrive.ch',
        );

        $this->assertTrue(
            app(SessionContextEnricher::class)->enrich($snapshot, $this->pageviewBatch(), null)->isBot,
        );
    }

    public function test_it_anonymises_the_ip_when_configured(): void
    {
        config(['analytics.privacy.anonymize_ip' => true]);

        $snapshot = new RequestSnapshot(ip: '85.4.12.66', userAgent: null, host: 'vantadrive.ch');

        $this->assertSame(
            '85.4.12.0',
            app(SessionContextEnricher::class)->enrich($snapshot, $this->pageviewBatch(), null)->ip,
        );
    }

    public function test_it_captures_the_landing_url_query_parameters_for_marketing_matching(): void
    {
        $snapshot = new RequestSnapshot(ip: '85.4.12.66', userAgent: null, host: 'vantadrive.ch');

        $context = app(SessionContextEnricher::class)->enrich(
            $snapshot,
            $this->pageviewBatch('https://vantadrive.ch/?src=meta_ete&creative=cabrio&utm_source=meta'),
            null,
        );

        $this->assertSame(
            ['src' => 'meta_ete', 'creative' => 'cabrio', 'utm_source' => 'meta'],
            $context->mktParams,
        );
    }
}
