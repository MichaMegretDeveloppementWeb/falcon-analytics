<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Facades\Analytics;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

/**
 * Work the package leaves for after the response must still finish when the
 * connection closes first · PHP is told so before the response goes.
 *
 * No test here closes a real connection · each reads the setting PHP will run
 * the rest of the request under, which is what decides it.
 */
final class WorkAfterTheResponseOutlivesAClosedConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ignore_user_abort(false);

        Route::get('/_analytics_record_test', function () {
            Analytics::record('Lead');

            return response()->noContent();
        })->middleware('web');
    }

    protected function tearDown(): void
    {
        ignore_user_abort(false);

        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'sent_at' => 1000,
            'events' => [['type' => 'pageview', 'ts' => 1000, 'route' => 'home', 'url' => 'https://boutique.test/']],
        ];
    }

    public function test_an_accepted_beacon_is_recorded_even_if_the_connection_closes(): void
    {
        $this->withoutDefer()
            ->withHeader('Origin', config('app.url'))
            ->postJson('/__analytics', $this->payload())
            ->assertNoContent();

        $this->assertSame(1, ignore_user_abort());
    }

    public function test_a_server_event_is_recorded_even_if_the_connection_closes(): void
    {
        $this->withoutDefer()->get('/_analytics_record_test')->assertNoContent();

        $this->assertSame(1, ignore_user_abort());
    }

    public function test_a_screen_catches_the_maintenance_up_even_if_the_connection_closes(): void
    {
        $this->actingAs(TestAdmin::create(['email' => 'admin@example.test']), 'admin')
            ->get(route('analytics.admin.overview'))
            ->assertSuccessful();

        $this->assertSame(1, ignore_user_abort());
    }

    public function test_a_dropped_beacon_has_nothing_to_finish(): void
    {
        config(['analytics.enabled' => false]);

        $this->withoutDefer()
            ->withHeader('Origin', config('app.url'))
            ->postJson('/__analytics', $this->payload())
            ->assertNoContent();

        $this->assertSame(0, ignore_user_abort());
    }

    public function test_a_screen_without_the_catch_up_has_nothing_to_finish(): void
    {
        config(['analytics.internal.maintenance.on_screen_load' => false]);

        $this->actingAs(TestAdmin::create(['email' => 'admin@example.test']), 'admin')
            ->get(route('analytics.admin.overview'))
            ->assertSuccessful();

        $this->assertSame(0, ignore_user_abort());
    }
}
