<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Http\Requests\IngestBatchRequest;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Support\Facades\Validator;

final class IngestBatchRequestTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ingestRequest(array $data): IngestBatchRequest
    {
        $request = IngestBatchRequest::create('/__analytics', 'POST', $data);
        $request->headers->set('Accept', 'application/json');
        $request->setContainer(app());
        $request->validateResolved();

        return $request;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ingestFails(array $data): bool
    {
        return Validator::make($data, (new IngestBatchRequest)->rules())->fails();
    }

    public function test_it_validates_and_maps_a_batch_rebuilding_timestamps_from_client_deltas(): void
    {
        CarbonImmutable::setTestNow('2026-07-01 12:00:00');

        $batch = $this->ingestRequest([
            'sent_at' => 10_000,
            'referrer' => 'https://google.com',
            'events' => [
                ['type' => 'pageview', 'ts' => 10_000, 'route' => 'home', 'url' => 'https://vantadrive.ch/'],
                ['type' => 'click', 'ts' => 7_000, 'name' => 'listing.contact_click', 'props' => ['listing_id' => 42], 'value' => 3],
            ],
        ])->toBatch();

        $this->assertCount(2, $batch->events);
        $this->assertSame('https://google.com', $batch->referrer);
        $this->assertSame(EventType::Pageview, $batch->events[0]->type);
        $this->assertSame('2026-07-01 12:00:00', $batch->events[0]->occurredAt->toDateTimeString());
        $this->assertSame(EventType::Click, $batch->events[1]->type);
        $this->assertSame('2026-07-01 11:59:57', $batch->events[1]->occurredAt->toDateTimeString());
        $this->assertSame(3.0, $batch->events[1]->value);
        $this->assertSame(['listing_id' => 42], $batch->events[1]->props);
    }

    public function test_it_caps_the_reconstructed_age_of_very_old_events(): void
    {
        CarbonImmutable::setTestNow('2026-07-01 12:00:00');

        $batch = $this->ingestRequest([
            'sent_at' => 10_000_000,
            // Un écart de 10 000 000 ms, borné à 3 600 000 ms, soit une heure.
            'events' => [['type' => 'pageview', 'ts' => 0]],
        ])->toBatch();

        $this->assertSame('2026-07-01 11:00:00', $batch->events[0]->occurredAt->toDateTimeString());
    }

    public function test_it_rejects_an_invalid_event_type(): void
    {
        $this->assertTrue($this->ingestFails(['sent_at' => 1, 'events' => [['type' => 'bogus', 'ts' => 1]]]));
    }

    public function test_it_rejects_an_empty_batch(): void
    {
        $this->assertTrue($this->ingestFails(['sent_at' => 1, 'events' => []]));
    }

    public function test_it_rejects_a_missing_sent_at(): void
    {
        $this->assertTrue($this->ingestFails(['events' => [['type' => 'pageview', 'ts' => 1]]]));
    }

    public function test_it_rejects_a_batch_larger_than_the_cap(): void
    {
        $this->assertTrue($this->ingestFails([
            'sent_at' => 1,
            'events' => array_fill(0, 101, ['type' => 'pageview', 'ts' => 1]),
        ]));
    }

    public function test_it_accepts_a_well_formed_batch(): void
    {
        $this->assertFalse($this->ingestFails(['sent_at' => 1, 'events' => [['type' => 'pageview', 'ts' => 1]]]));
    }

    public function test_it_redacts_sensitive_query_parameters_while_keeping_tracking_params(): void
    {
        config(['analytics.privacy.redact_query_params' => ['token']]);

        $batch = $this->ingestRequest([
            'sent_at' => 1,
            'referrer' => 'https://ref.example/?token=zzz&utm_source=meta',
            'events' => [['type' => 'pageview', 'ts' => 1, 'url' => 'https://vantadrive.ch/?token=secret&gclid=abc']],
        ])->toBatch();

        $this->assertStringContainsString('token=redacted', $batch->events[0]->url);
        $this->assertStringContainsString('gclid=abc', $batch->events[0]->url);
        $this->assertStringNotContainsString('secret', $batch->events[0]->url);
        $this->assertStringContainsString('token=redacted', $batch->referrer);
        $this->assertStringContainsString('utm_source=meta', $batch->referrer);
    }
}
