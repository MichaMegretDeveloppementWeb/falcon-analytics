<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\TestCase;
use Falcon\Ui\Config\Defaults;

/**
 * A host holding a published configuration from before the areas.
 *
 * A published copy does not update itself: this one carries `dashboard`,
 * `marketing` and `assets` at the top level, and knows neither `admin` nor
 * `web`. This checks the package's file put through the kit's completion, where
 * a key filed in the wrong place shows.
 *
 * The application is booted without the database: the package's file calls
 * `storage_path()`, which needs an application.
 */
final class AnOldPublishedConfigStillWorksTest extends TestCase
{
    /**
     * The shape the configuration had before the areas, cut down to what
     * matters.
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

    public function test_it_leaves_what_the_host_had_chosen_alone(): void
    {
        $completed = $this->completed();

        $this->assertSame(['web', 'auth:admin'], $completed['dashboard']['middleware']);
        $this->assertSame('layouts.analytics-admin', $completed['dashboard']['layout']);
    }

    /** Nothing reads the stale blocks, so keeping them is harmless. */
    public function test_the_stale_blocks_survive_harmlessly(): void
    {
        $completed = $this->completed();

        $this->assertArrayHasKey('assets', $completed);
        $this->assertArrayHasKey('dashboard', $completed);
    }
}
