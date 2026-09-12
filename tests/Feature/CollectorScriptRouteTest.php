<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\TestCase;

/**
 * The collector does not go through PHP.
 *
 * It did once: a route served it, with a year of cache and a fingerprint in the
 * address. That route was removed on 2026-09-06. The host then imported it into
 * its JavaScript entry, and since 2026-09-12 the package compiles and ships it
 * itself — `public/analytics.js`, published into the host's public directory
 * and served by the web server.
 *
 * This test holds the removed route out: if it came back, two copies of the
 * same collector could end up on one page and count every visit twice.
 */
final class CollectorScriptRouteTest extends TestCase
{
    public function test_it_no_longer_serves_the_collector_script_itself(): void
    {
        $this->get('/__analytics.js')->assertNotFound();
    }

    /**
     * The source stays self-contained: no `import`, no npm dependency.
     *
     * It is no longer the host that imports it, but the build depends on it all
     * the same: the kit emits a **classic** tag for a package's file, and a
     * module would throw there in the browser. A dependency introduced here
     * would make the compiler emit a module.
     */
    public function test_the_source_stays_self_contained(): void
    {
        $path = dirname(__DIR__, 2).'/resources/js/collector.js';

        $this->assertFileExists($path);

        $source = (string) file_get_contents($path);

        $this->assertStringContainsString('sendBeacon', $source);
        $this->assertStringContainsString('__falconAnalytics', $source);

        // The fallback on `fetch` swallows its rejection: an unreachable
        // endpoint must never surface an unhandled promise in the console of
        // the host's visitor.
        $this->assertStringContainsString('.catch(', $source);

        $this->assertSame(0, preg_match('/^\s*(import|export)\s/m', $source));
    }

    /**
     * And the shipped file does come out as an immediately invoked function.
     *
     * This is the counterpart of the test above, on the compiled side: a build
     * configuration changed to produce a module would otherwise only show at
     * runtime, in a host's console.
     */
    public function test_the_shipped_collector_is_a_classic_script(): void
    {
        $shipped = (string) file_get_contents(dirname(__DIR__, 2).'/public/analytics.js');

        $this->assertStringStartsWith('(function()', $shipped);
        $this->assertSame(0, preg_match('/\b(import|export)\s*[{*]/', $shipped));
    }
}
