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
 * The script is compiled and shipped by the package, so it is declared to the
 * kit, which places it. The configuration cannot be compiled — the route's
 * name changes on every page, and tracking is cut while an administrator is
 * signed in — so it is laid inline.
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
     * Read from the route itself: a second derivation of the address could
     * drift from it, and visits would silently stop arriving.
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
     * The kit builds the script's versioned address and lays it in the head,
     * although the layout writes none of its marks. None of the kit's own files
     * may reach the page, or they would redraw the host's site.
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

    /**
     * A layout that places the suite itself carries the collector written below
     * its head mark · once.
     *
     * The head mark has passed when the directive declares its file, and the
     * body's mark is the one that lays it. The kit's final pass is turned off,
     * so that the marks are the only ones that could.
     */
    public function test_a_layout_that_places_the_suite_carries_the_collector_written_below_its_head(): void
    {
        config(['analytics.enabled' => true, 'analytics.endpoint' => '__analytics', 'ui.inject' => false]);

        Route::get('/un-gabarit-qui-place', fn (): string => Blade::render(
            '<!DOCTYPE html><html><head><title>t</title>@falconStyles</head>'
            .'<body><p>du contenu</p>@analyticsCollector @falconScripts</body></html>'
        ));

        $html = (string) $this->get('/un-gabarit-qui-place')->assertSuccessful()->getContent();

        $this->assertSame(1, preg_match_all('#analytics/analytics\.js\?v=#', $html), 'The collector has to arrive, and once.');
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
     * The directive runs on every public page, so a failure costs the
     * measurement and nothing else. The host's closure is the one place foreign
     * code runs inside this call, so it is what throws here.
     */
    public function test_it_leaves_the_page_whole_when_something_throws(): void
    {
        config(['analytics.enabled' => true]);
        Analytics::excludeUsing(fn () => throw new RuntimeException('le contexte ne se lit pas'));

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('warning')->once();

        $this->assertSame('', trim(Blade::render('@analyticsCollector')));
    }

    /** The file is not even downloaded; the collector's own guard is only a second line of defence. */
    public function test_it_asks_for_nothing_at_all_when_tracking_is_off(): void
    {
        config(['analytics.enabled' => false]);

        $html = Blade::render('@analyticsCollector');

        $this->assertStringNotContainsString('__falconAnalytics', $html);
        $this->assertStringNotContainsString('analytics.js', $html);
    }
}
