<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Facades\Analytics;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

final class AServerEventIsRecordedForTheVisitorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_analytics_record_test', function () {
            Analytics::record('Lead', value: 3, props: ['listing_id' => 42]);

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
        $this->assertDoesntThrow(fn () => Analytics::record('X', value: 1));

        $this->assertSame(0, Event::count());
    }

    /**
     * With consent the identifier comes from a cookie that no web request
     * carries here, so every call would otherwise make a new visitor.
     */
    public function test_it_makes_no_visitor_outside_a_web_request_even_with_consent(): void
    {
        Analytics::consentUsing(fn (): bool => true);

        $this->withoutDefer();
        Analytics::record('Lead', value: 3);
        Analytics::record('Lead', value: 3);

        $this->assertSame(0, Visitor::count());
        $this->assertSame(0, Event::count());
    }

    public function test_it_says_in_the_log_why_nothing_was_recorded_outside_a_web_request(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message): bool => str_contains($message, 'outside a visitor'));

        Analytics::record('Lead');
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
