<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\TestCase;
use Falcon\Ui\Config\Defaults;

/**
 * A host holding a published copy from before the areas.
 *
 * Publishing the configuration is a common move, and a published copy does not
 * update itself. Our own host's dates from before the areas sub-chantier: it
 * carries `dashboard`, `marketing` and `assets` at the top level, and knows
 * neither `admin` nor `web`.
 *
 * **The package has to keep working in that state**, otherwise an update would
 * break the screens of anyone who published their configuration one day. That
 * is exactly what `completeConfigFrom` buys, and this test checks that the
 * package's file does draw what it needs from it.
 *
 * What is checked here is not the kit's mechanism — that has its own tests —
 * but **our file put through it**: a key filed in the wrong place would not
 * show any other way.
 *
 * The application is booted, without the database: the package's file calls
 * `storage_path()` for the geolocation database, and that cannot be read
 * outside an application.
 */
final class AnOldPublishedConfigStillWorksTest extends TestCase
{
    /**
     * The shape the configuration had before the areas, cut down to what
     * matters: this is the copy our host holds today.
     *
     * @return array<string, mixed>
     */
    private function theConfigAHostPublishedBeforeTheAreas(): array
    {
        return [
            'enabled' => true,
            'assets' => [
                'admin_css' => 'resources/css/app.css',
                'web_js' => 'resources/js/app.js',
            ],
            'dashboard' => [
                'route_prefix' => 'admin/analytics',
                'route_name' => 'analytics',
                'middleware' => ['web', 'auth:admin'],
                'layout' => 'layouts.analytics-admin',
                'layout_section' => 'content',
            ],
            'marketing' => [
                'route_prefix' => 'admin/marketing',
                'route_name' => 'marketing',
                'middleware' => ['web', 'auth:admin'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function completed(): array
    {
        /** @var array<string, mixed> $defaults */
        $defaults = require dirname(__DIR__, 2).'/config/analytics.php';

        return Defaults::completeMissing($defaults, $this->theConfigAHostPublishedBeforeTheAreas());
    }

    public function test_the_areas_appear_even_though_the_published_copy_ignores_them(): void
    {
        $completed = $this->completed();

        $this->assertSame('admin/analytics', $completed['admin']['route_prefix']);
        $this->assertSame('admin/marketing', $completed['admin']['marketing']['route_prefix']);
        $this->assertIsArray($completed['web']['middleware']);
        $this->assertNotSame([], $completed['web']['middleware'], 'Ingestion has to keep a session stack.');
    }

    /**
     * What the host had chosen must not be overwritten by the completion.
     *
     * This is the other half of the contract: complete what is missing, without
     * ever taking back control of what is written.
     */
    public function test_it_leaves_what_the_host_had_chosen_alone(): void
    {
        $completed = $this->completed();

        $this->assertSame(['web', 'auth:admin'], $completed['dashboard']['middleware']);
        $this->assertSame('layouts.analytics-admin', $completed['dashboard']['layout']);
    }

    /**
     * The stale blocks survive, and that is harmless: nothing reads them any
     * more. Saying so here saves worrying about them on sight.
     */
    public function test_the_stale_blocks_survive_harmlessly(): void
    {
        $completed = $this->completed();

        $this->assertArrayHasKey('assets', $completed);
        $this->assertArrayHasKey('dashboard', $completed);
    }
}
