<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Facades\Analytics;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class IngestEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'sent_at' => 1000,
            'events' => [['type' => 'pageview', 'ts' => 1000, 'route' => 'home', 'url' => 'https://vantadrive.ch/']],
        ];
    }

    public function test_it_ingests_a_valid_same_origin_batch(): void
    {
        $this->withoutDefer()
            ->withHeader('Origin', config('app.url'))
            ->postJson('/__analytics', $this->payload())
            ->assertNoContent();

        $this->assertSame(1, Visitor::count());
        $this->assertSame(1, Session::count());
        $this->assertSame(1, Event::count());
    }

    public function test_it_rejects_an_invalid_payload_with_422(): void
    {
        $this->withHeader('Origin', config('app.url'))
            ->postJson('/__analytics', ['sent_at' => 1, 'events' => []])
            ->assertStatus(422);
    }

    public function test_it_is_a_silent_no_op_when_disabled(): void
    {
        config(['analytics.enabled' => false]);

        $this->withoutDefer()
            ->withHeader('Origin', config('app.url'))
            ->postJson('/__analytics', $this->payload())
            ->assertNoContent();

        $this->assertSame(0, Event::count());
    }

    public function test_it_drops_a_request_from_an_excluded_subject(): void
    {
        Analytics::excludeUsing(fn () => true);

        $this->withoutDefer()
            ->withHeader('Origin', config('app.url'))
            ->postJson('/__analytics', $this->payload())
            ->assertNoContent();

        $this->assertSame(0, Event::count());
    }

    public function test_it_drops_a_cross_origin_request(): void
    {
        $this->withoutDefer()
            ->withHeader('Origin', 'https://evil.example')
            ->postJson('/__analytics', $this->payload())
            ->assertNoContent();

        $this->assertSame(0, Event::count());
    }

    public function test_it_drops_a_request_with_a_present_but_unparseable_origin(): void
    {
        $this->withoutDefer()
            ->withHeader('Origin', 'null')
            ->postJson('/__analytics', $this->payload())
            ->assertNoContent();

        $this->assertSame(0, Event::count());
    }

    public function test_it_drops_a_request_with_a_malformed_origin_without_erroring(): void
    {
        // `parse_url` rend false, et non null, ici · la requête doit être
        // écartée, pas rendre une 500.
        $this->withoutDefer()
            ->withHeader('Origin', 'http://:80')
            ->postJson('/__analytics', $this->payload())
            ->assertNoContent();

        $this->assertSame(0, Event::count());
    }

    public function test_it_accepts_a_request_with_neither_origin_nor_referer_as_same_origin(): void
    {
        $this->withoutDefer()
            ->postJson('/__analytics', $this->payload())
            ->assertNoContent();

        $this->assertSame(1, Event::count());
    }

    public function test_it_drops_a_request_from_an_excluded_ip(): void
    {
        config(['analytics.exclude_ips' => ['127.0.0.1']]);

        $this->withoutDefer()
            ->withHeader('Origin', config('app.url'))
            ->postJson('/__analytics', $this->payload())
            ->assertNoContent();

        $this->assertSame(0, Event::count());
    }

    public function test_it_swallows_a_persistence_failure_and_still_returns_no_content(): void
    {
        // On casse la cible d'écriture pour que l'ingestion différée lève une
        // vraie erreur de requête.
        $this->withoutDatabase(function (): void {
            $this->withoutDefer()
                ->withHeader('Origin', config('app.url'))
                ->postJson('/__analytics', $this->payload())
                ->assertNoContent();
        });
    }
}
