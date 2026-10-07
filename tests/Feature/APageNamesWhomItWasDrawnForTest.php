<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\DTOs\PageContext;
use Falcon\Analytics\Facades\Analytics;
use Falcon\Analytics\Services\PageContextSealer;
use Falcon\Analytics\Tests\Fixtures\Models\TestClient;
use Falcon\Analytics\Tests\TestCase;
use Falcon\Analytics\View\Collector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * What a page hands its collector · a sealed context for a signed-in subject,
 * naming them and the browser the host's session knows, and nothing more for
 * anybody else.
 */
final class APageNamesWhomItWasDrawnForTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_analytics_page_test', fn () => response(Collector::configuration() ?? 'null'))->middleware('web');
    }

    public function test_the_page_of_a_signed_in_subject_names_them_and_their_browser(): void
    {
        $cabinet = TestClient::create(['first_name' => 'Cabinet']);
        $this->actingAs($cabinet, 'client');

        $configuration = $this->pageConfiguration();
        $context = $this->app->make(PageContextSealer::class)->open((string) $configuration['context']);

        $this->assertEquals(new PageContext('client', $cabinet->id, (string) session('fa_vid')), $context);
        $this->assertTrue(Str::isUuid((string) session('fa_vid')), 'The browser is named before its first send.');
    }

    /** A public page, an anonymous visitor: the page sends exactly what it always did. */
    public function test_the_page_of_an_anonymous_visitor_carries_no_context(): void
    {
        $this->assertSame(['endpoint', 'route', 'heartbeat', 'flush'], array_keys($this->pageConfiguration()));
    }

    /** With consent, the browser is the one its cookie names. */
    public function test_with_consent_the_context_names_the_cookie(): void
    {
        config(['analytics.identity.consent_cookie' => 'fa_consent']);
        $browser = (string) Str::uuid();
        $this->actingAs(TestClient::create(['first_name' => 'Cabinet']), 'client');

        // The request as the host's middleware leaves it: a session, and both cookies read.
        request()->setLaravelSession($this->app->make('session')->driver());
        request()->cookies->set('fa_consent', '1');
        request()->cookies->set('fa_vid', $browser);

        $configuration = json_decode((string) Collector::configuration(), true);

        $this->assertIsArray($configuration);
        $this->assertSame($browser, $this->app->make(PageContextSealer::class)->open((string) $configuration['context'])?->browserKey);
    }

    /** Outside a session, the subject has no browser to be named on · the collector is drawn without a context. */
    public function test_a_request_without_a_session_still_gets_its_collector(): void
    {
        Analytics::resolveSubjectUsing(fn (): array => ['type' => 'client', 'id' => 4]);
        Log::shouldReceive('channel')->never();

        $configuration = json_decode((string) Collector::configuration(), true);

        $this->assertIsArray($configuration);
        $this->assertArrayHasKey('endpoint', $configuration);
        $this->assertArrayNotHasKey('context', $configuration);
    }

    /** A context that cannot be sealed costs the context, never the collector. */
    public function test_a_context_that_cannot_be_sealed_leaves_the_collector_whole(): void
    {
        $this->app->bind(PageContextSealer::class, fn () => throw new RuntimeException('aucune clé'));
        $this->actingAs(TestClient::create(['first_name' => 'Cabinet']), 'client');

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('warning')->once();

        $configuration = $this->pageConfiguration();

        $this->assertArrayHasKey('endpoint', $configuration);
        $this->assertArrayNotHasKey('context', $configuration);
    }

    /** @return array<string, mixed> */
    private function pageConfiguration(): array
    {
        $configuration = $this->get('/_analytics_page_test')->assertOk()->json();

        $this->assertIsArray($configuration, 'The page drew no collector.');

        return $configuration;
    }
}
