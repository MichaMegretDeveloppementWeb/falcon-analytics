<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\AnalyticsServiceProvider;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Support\ServiceProvider;

/**
 * The package has two settings files, and only one of them is the host's.
 *
 * `config/analytics.php` holds what a host answers · its guards, where the
 * screens hang, how long the detail is kept. It is published, and it is the
 * only one.
 *
 * `config/internal.php` holds what the PACKAGE answers · how often the
 * maintenance may catch itself up, how much it takes on at a time. **Two
 * reasonable hosts would not answer those differently**, so they are not
 * questions — a value that suits nobody is a defect to fix here rather than a
 * question to ask of every project.
 *
 * The distinction is only worth anything if it is held ·
 *
 * - the internal file must never be publishable, or a host would find it in
 *   its own `config/` and reasonably take it for an invitation ;
 * - it must win over anything a host writes, or the distinction would be a
 *   convention rather than a rule ;
 * - and the published file must not carry a copy of it, which is how such a
 *   separation usually rots.
 */
final class TwoConfigurationsAndOnlyOneIsYoursTest extends TestCase
{
    /**
     * What `vendor:publish` can ever reach, whatever the tag.
     *
     * Slashes normalised · the registry holds the paths as the provider wrote
     * them, with forward slashes, while the platform may use the other.
     */
    private function everythingPublishable(): string
    {
        $paths = array_keys(ServiceProvider::pathsToPublish(AnalyticsServiceProvider::class));

        return str_replace('\\', '/', implode(' ', $paths));
    }

    public function test_the_internal_settings_are_not_publishable(): void
    {
        $this->assertStringNotContainsString(
            'config/internal.php',
            $this->everythingPublishable(),
            'A host would find it in its own config/ and take it for an invitation.',
        );

        // And the host's own file is, or the package would be unconfigurable.
        $this->assertStringContainsString('config/analytics.php', $this->everythingPublishable());
    }

    /**
     * A host that invents the key anyway is overruled.
     *
     * **This is the difference between a rule and a convention**, and it rests
     * on one line of the provider: the internal settings are SET, not merged.
     * A merge would fill in only what is missing, so a host writing
     * `analytics.internal.…` into its published copy would win — and would then
     * be steering something the package answers for.
     *
     * The provider is registered again over a configuration that already holds
     * the invented value, which is exactly the situation a boot would meet.
     */
    public function test_a_host_that_invents_the_key_is_overruled(): void
    {
        $this->assertSame(7, config('analytics.internal.maintenance.days_per_run'));

        config(['analytics.internal.maintenance.days_per_run' => 999]);

        (new AnalyticsServiceProvider(app()))->register();

        $this->assertSame(
            7,
            config('analytics.internal.maintenance.days_per_run'),
            'The package decides this one, whatever a host writes.',
        );
    }

    /**
     * The published file holds no copy of them.
     *
     * This is how the separation rots · a key drifts into the host's file,
     * someone sets it, nothing happens, and nobody can say why.
     */
    public function test_the_published_file_carries_no_copy_of_them(): void
    {
        $host = require dirname(__DIR__, 2).'/config/analytics.php';
        $internal = require dirname(__DIR__, 2).'/config/internal.php';

        foreach (array_keys($internal) as $key) {
            $this->assertArrayNotHasKey(
                $key,
                $host,
                "« {$key} » est dans les deux fichiers : l'hôte croira le régler.",
            );
        }

        $this->assertArrayNotHasKey('internal', $host, 'The host file never holds the package’s own.');
    }
}
