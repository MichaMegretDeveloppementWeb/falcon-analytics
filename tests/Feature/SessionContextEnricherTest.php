<?php

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\IncomingBatch;
use Falcon\Analytics\DTOs\IncomingEvent;
use Falcon\Analytics\DTOs\RequestSnapshot;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Services\SessionContextEnricher;

function pageviewBatch(?string $url = null, ?string $referrer = null): IncomingBatch
{
    return new IncomingBatch(
        events: [new IncomingEvent(type: EventType::Pageview, occurredAt: CarbonImmutable::now(), route: 'home', url: $url)],
        referrer: $referrer,
    );
}

it('builds a full context from the snapshot and batch', function () {
    $snapshot = new RequestSnapshot(
        ip: '85.4.12.66',
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:128.0) Gecko/20100101 Firefox/128.0',
        host: 'vantadrive.ch',
    );

    $context = app(SessionContextEnricher::class)->enrich(
        $snapshot,
        pageviewBatch('https://vantadrive.ch/?utm_source=meta&utm_medium=cpc&utm_campaign=spring', 'https://facebook.com/'),
        ['type' => 'client', 'id' => 7],
    );

    expect($context->subjectType)->toBe('client')
        ->and($context->subjectId)->toBe(7)
        ->and($context->browser)->toBe('Firefox')
        ->and($context->os)->toBe('Windows')
        ->and($context->isBot)->toBeFalse()
        ->and($context->ip)->toBe('85.4.12.66')
        ->and($context->source)->toBe('paid')
        ->and($context->utmCampaign)->toBe('spring')
        ->and($context->landingRoute)->toBe('home')
        ->and($context->referrer)->toBe('https://facebook.com/');
});

it('flags a bot request', function () {
    $snapshot = new RequestSnapshot(
        ip: '66.249.66.1',
        userAgent: 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        host: 'vantadrive.ch',
    );

    expect(app(SessionContextEnricher::class)->enrich($snapshot, pageviewBatch(), null)->isBot)->toBeTrue();
});

it('anonymises the ip when configured', function () {
    config(['analytics.privacy.anonymize_ip' => true]);

    $snapshot = new RequestSnapshot(ip: '85.4.12.66', userAgent: null, host: 'vantadrive.ch');

    expect(app(SessionContextEnricher::class)->enrich($snapshot, pageviewBatch(), null)->ip)->toBe('85.4.12.0');
});
