<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\TestCase;

/**
 * The collector does not go through PHP.
 *
 * The package ships it as `public/analytics.js`, published into the host's
 * public directory and served by the web server. A PHP route serving it too
 * could put two collectors on one page and count every visit twice.
 */
final class CollectorScriptRouteTest extends TestCase
{
    public function test_it_no_longer_serves_the_collector_script_itself(): void
    {
        $this->get('/__analytics.js')->assertNotFound();
    }

    /**
     * No `import` and no npm dependency: the kit emits a classic tag for a
     * package's file, where a module throws, and a dependency makes the
     * compiler emit a module.
     */
    public function test_the_source_stays_self_contained(): void
    {
        $path = dirname(__DIR__, 2).'/resources/js/collector.js';

        $this->assertFileExists($path);

        $source = (string) file_get_contents($path);

        $this->assertStringContainsString('sendBeacon', $source);
        $this->assertStringContainsString('__falconAnalytics', $source);

        // The `fetch` fallback swallows its rejection, so no unhandled promise reaches the console.
        $this->assertStringContainsString('.catch(', $source);

        $this->assertSame(0, preg_match('/^\s*(import|export)\s/m', $source));
    }

    /** A build that emits a module would otherwise only show at runtime, in the browser console. */
    public function test_the_shipped_collector_is_a_classic_script(): void
    {
        $shipped = (string) file_get_contents(dirname(__DIR__, 2).'/public/analytics.js');

        $this->assertStringStartsWith('(function()', $shipped);
        $this->assertSame(0, preg_match('/\b(import|export)\s*[{*]/', $shipped));
    }
}
