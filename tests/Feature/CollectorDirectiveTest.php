<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Facades\Analytics;
use Falcon\Analytics\Tests\TestCase;
use Falcon\Analytics\View\Collector;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use RuntimeException;

/**
 * `@analyticsCollector` brings the collector, and what it needs to know.
 *
 * Two things travel, and not the same way: **the script** is compiled, shipped
 * by the package and published by the host, so it is declared to the kit which
 * places it; **the configuration** cannot be compiled — the route's name
 * changes on every page, and tracking is cut while an administrator is signed
 * in — so it is laid inline.
 *
 * The directive was called `@analyticsConfig` back when the collector's code
 * came from the host's JavaScript entry. The package ships it now, and the name
 * says so.
 */
final class CollectorDirectiveTest extends TestCase
{
    public function test_it_renders_the_collector_config_when_enabled(): void
    {
        config(['analytics.enabled' => true, 'analytics.endpoint' => '__analytics']);

        $html = Blade::render('@analyticsCollector');

        $this->assertStringContainsString('window.__falconAnalytics', $html);
        $this->assertStringContainsString('__analytics', $html);
    }

    /**
     * The collector posts where the ingestion route answers, read from the
     * route itself · a second derivation of the address would part from the
     * route the day they disagree, and the only trace would be visits that stop
     * arriving.
     */
    public function test_the_collector_posts_where_the_ingestion_route_answers(): void
    {
        config(['analytics.enabled' => true]);
        $mounted = route('analytics.web.ingest', [], absolute: false);

        config(['analytics.endpoint' => 'somewhere-else']);

        $configuration = json_decode((string) Collector::configuration(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertIsArray($configuration);
        $this->assertSame($mounted, $configuration['endpoint'] ?? null);
    }

    /**
     * **The test that counts**: a public page receives the collector, and
     * nothing else.
     *
     * The view declares the file to the kit, which builds its versioned address
     * and lays it before the body closes: the layout below renders no stack
     * directive, exactly like the public page of an ordinary site.
     *
     * The second half of the test is the one that protects the host, and it
     * holds a correction of the kit dated 2026-09-12. Its injection laid **its
     * own** tags as soon as a package had declared anything at all: a public
     * page ended up with the kit's reset, sixty kilobytes of administration
     * stylesheet, a script and a notification container. The host's site came
     * out redrawn.
     */
    public function test_a_public_page_receives_the_collector_and_nothing_else(): void
    {
        config(['analytics.enabled' => true, 'analytics.endpoint' => '__analytics']);

        Route::get('/une-page-publique', fn (): string => Blade::render(
            '<!DOCTYPE html><html><head><title>t</title></head><body><p>du contenu</p>@analyticsCollector</body></html>'
        ));

        $html = (string) $this->get('/une-page-publique')->assertSuccessful()->getContent();

        $this->assertStringContainsString('window.__falconAnalytics', $html);
        $this->assertMatchesRegularExpression(
            '#<script src="[^"]*analytics/analytics\.js\?v=[^"]+" defer#',
            $html,
            'The script has to arrive versioned and deferred.',
        );

        foreach (['ui.css', 'ui-base.css', 'ui.js'] as $ofTheKit) {
            $this->assertStringNotContainsString(
                $ofTheKit,
                $html,
                "A public page must receive nothing from the kit: {$ofTheKit} is there.",
            );
        }
    }

    public function test_it_renders_nothing_when_disabled(): void
    {
        config(['analytics.enabled' => false]);

        $this->assertSame('', trim(Blade::render('@analyticsCollector')));
    }

    public function test_it_renders_nothing_for_an_excluded_context(): void
    {
        config(['analytics.enabled' => true]);
        Analytics::excludeUsing(fn () => true);

        $this->assertSame('', trim(Blade::render('@analyticsCollector')));
    }

    /**
     * The promise the catch makes, and nothing proved it until now.
     *
     * This runs on every public page of the host, so a failure here has to cost
     * the measurement and nothing else. The closure below is the host's own —
     * the one place where someone else's code runs inside this call — and it is
     * the honest way to make the thing throw.
     */
    public function test_it_leaves_the_page_whole_when_something_throws(): void
    {
        config(['analytics.enabled' => true]);
        Analytics::excludeUsing(fn () => throw new RuntimeException('le contexte ne se lit pas'));

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('warning')->once();

        $this->assertSame('', trim(Blade::render('@analyticsCollector')));
    }

    /**
     * Tracking cut: the file is not even downloaded.
     *
     * This was the other half of the old model: the collector, imported by the
     * host, was always loaded, and it was its own first test that stopped it.
     * Now that it comes from the package, declaring nothing is enough — the
     * page does not ask for the file, and the collector's guard is only a
     * second line of defence.
     */
    public function test_it_asks_for_nothing_at_all_when_tracking_is_off(): void
    {
        config(['analytics.enabled' => false]);

        $html = Blade::render('@analyticsCollector');

        $this->assertStringNotContainsString('__falconAnalytics', $html);
        $this->assertStringNotContainsString('analytics.js', $html);
    }
}
