<?php

use Falcon\Analytics\Facades\Analytics;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function analyticsPayload(): array
{
    return [
        'sent_at' => 1000,
        'events' => [['type' => 'pageview', 'ts' => 1000, 'route' => 'home', 'url' => 'https://vantadrive.ch/']],
    ];
}

it('ingests a valid same-origin batch', function () {
    $this->withoutDefer()
        ->withHeader('Origin', config('app.url'))
        ->postJson('/__analytics', analyticsPayload())
        ->assertNoContent();

    expect(Visitor::count())->toBe(1)
        ->and(Session::count())->toBe(1)
        ->and(Event::count())->toBe(1);
});

it('rejects an invalid payload with 422', function () {
    $this->withHeader('Origin', config('app.url'))
        ->postJson('/__analytics', ['sent_at' => 1, 'events' => []])
        ->assertStatus(422);
});

it('is a silent no-op when disabled', function () {
    config(['analytics.enabled' => false]);

    $this->withoutDefer()
        ->withHeader('Origin', config('app.url'))
        ->postJson('/__analytics', analyticsPayload())
        ->assertNoContent();

    expect(Event::count())->toBe(0);
});

it('drops a request from an excluded subject', function () {
    Analytics::excludeUsing(fn () => true);

    $this->withoutDefer()
        ->withHeader('Origin', config('app.url'))
        ->postJson('/__analytics', analyticsPayload())
        ->assertNoContent();

    expect(Event::count())->toBe(0);
});

it('drops a cross-origin request', function () {
    $this->withoutDefer()
        ->withHeader('Origin', 'https://evil.example')
        ->postJson('/__analytics', analyticsPayload())
        ->assertNoContent();

    expect(Event::count())->toBe(0);
});

it('drops a request with a present but unparseable origin', function () {
    $this->withoutDefer()
        ->withHeader('Origin', 'null')
        ->postJson('/__analytics', analyticsPayload())
        ->assertNoContent();

    expect(Event::count())->toBe(0);
});

it('drops a request from an excluded ip', function () {
    config(['analytics.exclude_ips' => ['127.0.0.1']]);

    $this->withoutDefer()
        ->withHeader('Origin', config('app.url'))
        ->postJson('/__analytics', analyticsPayload())
        ->assertNoContent();

    expect(Event::count())->toBe(0);
});

it('swallows a persistence failure and still returns no content', function () {
    // Break the write target so the deferred ingestion throws a real query error.
    Schema::drop('falcon_analytics_events');

    $this->withoutDefer()
        ->withHeader('Origin', config('app.url'))
        ->postJson('/__analytics', analyticsPayload())
        ->assertNoContent();
});
