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
use Illuminate\Support\Facades\Route;
use Throwable;

final class ServerEventRecorderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_analytics_record_test', function (ServerEventRecorder $recorder) {
            $recorder->record('Lead', value: 3.0, props: ['listing_id' => 42]);

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
        $this->assertSame(3.0, $event->value);
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
        // Appelé hors d'une requête HTTP : aucune session n'est disponible, la
        // résolution échoue, et l'appelant ne doit jamais voir d'exception.
        $this->assertDoesntThrow(
            fn () => app(ServerEventRecorder::class)->record('X', value: 1.0),
            Throwable::class,
        );

        $this->assertSame(0, Event::count());
    }

    public function test_it_is_callable_through_the_analytics_facade(): void
    {
        Route::get('/_analytics_record_facade', function () {
            Analytics::record('CompleteRegistration', value: 5.0);

            return response()->noContent();
        })->middleware('web');

        $this->withoutDefer()->get('/_analytics_record_facade')->assertNoContent();

        $event = Event::firstOrFail();

        $this->assertSame('CompleteRegistration', $event->name);
        $this->assertSame(5.0, $event->value);
        $this->assertSame(EventType::Custom, $event->type);
    }
}
