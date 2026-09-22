<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Facades\Analytics;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Services\ServerEventRecorder;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Throwable;

final class ServerEventRecorderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_analytics_record_test', function (ServerEventRecorder $recorder) {
            $recorder->record('Lead', value: 3, props: ['listing_id' => 42]);

            return response()->noContent();
        })->middleware('web');
    }

    public function test_it_records_a_server_event_for_the_current_visitor(): void
    {
        $this->withoutDefer()->get('/_analytics_record_test')->assertNoContent();

        $this->assertSame(1, Visitor::count());
        $this->assertSame(1, Session::count());

        $event = Event::firstOrFail();

        $this->assertSame(EventType::Custom, $event->type);
        $this->assertSame('Lead', $event->name);
        $this->assertSame(3, $event->value);
        $this->assertSame(['listing_id' => 42], $event->props);
    }

    public function test_it_does_not_record_when_tracking_is_disabled(): void
    {
        config(['analytics.enabled' => false]);

        $this->withoutDefer()->get('/_analytics_record_test')->assertNoContent();

        $this->assertSame(0, Event::count());
    }

    public function test_it_does_not_record_for_an_excluded_context(): void
    {
        Analytics::excludeUsing(fn () => true);

        $this->withoutDefer()->get('/_analytics_record_test')->assertNoContent();

        $this->assertSame(0, Event::count());
    }

    public function test_it_never_throws_to_the_caller_even_without_a_usable_request_context(): void
    {
        // Called outside an HTTP request: no session is available, resolution
        // fails, and the caller must never see an exception.
        // One argument only: `assertDoesntThrow` takes just the closure, and it
        // already catches every `Throwable`. The second argument served no
        // purpose but to suggest it chose what gets caught.
        $this->assertDoesntThrow(fn () => app(ServerEventRecorder::class)->record('X', value: 1));

        $this->assertSame(0, Event::count());
    }

    /**
     * Outside a visitor's web request there is no visitor to record it for.
     *
     * With consent the identifier would come from a cookie the request does
     * not carry, so every call would make a new visitor · a queued job firing
     * a thousand times would leave a thousand of them.
     */
    public function test_it_makes_no_visitor_outside_a_web_request_even_with_consent(): void
    {
        Analytics::consentUsing(fn (): bool => true);

        $this->withoutDefer();
        app(ServerEventRecorder::class)->record('Lead', value: 3);
        app(ServerEventRecorder::class)->record('Lead', value: 3);

        $this->assertSame(0, Visitor::count());
        $this->assertSame(0, Event::count());
    }

    public function test_it_says_in_the_log_why_nothing_was_recorded_outside_a_web_request(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message): bool => str_contains($message, 'outside a visitor'));

        app(ServerEventRecorder::class)->record('Lead');
    }

    public function test_it_is_callable_through_the_analytics_facade(): void
    {
        Route::get('/_analytics_record_facade', function () {
            Analytics::record('CompleteRegistration', value: 5);

            return response()->noContent();
        })->middleware('web');

        $this->withoutDefer()->get('/_analytics_record_facade')->assertNoContent();

        $event = Event::firstOrFail();

        $this->assertSame('CompleteRegistration', $event->name);
        $this->assertSame(5, $event->value);
        $this->assertSame(EventType::Custom, $event->type);
    }
}
