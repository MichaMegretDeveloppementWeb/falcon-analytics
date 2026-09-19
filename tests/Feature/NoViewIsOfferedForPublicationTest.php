<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\AnalyticsServiceProvider;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Support\ServiceProvider;

/**
 * The package offers none of its views for publication.
 *
 * Its screens belong to it: every version updates them, and a copy kept by a
 * host would stop receiving those updates without a word. A host dresses every
 * screen at once through its own layout, which is the host's.
 *
 * @see ScreenMountingTest for that layout.
 */
final class NoViewIsOfferedForPublicationTest extends TestCase
{
    public function test_nothing_the_package_publishes_lands_among_the_host_views(): void
    {
        $published = ServiceProvider::pathsToPublish(AnalyticsServiceProvider::class);
        $views = $this->folder(resource_path('views'));

        $this->assertNotSame([], $published, 'Nothing was read: the guard would watch nothing.');

        foreach ($published as $source => $destination) {
            $this->assertStringStartsNotWith(
                $views,
                $this->folder($destination),
                "{$source} is offered to the host's views: a copy there would stop being updated.",
            );
        }
    }

    /**
     * A path as a folder, so that a prefix stops at a folder's edge.
     *
     * Windows writes the separators the other way round.
     *
     * @return non-empty-string
     */
    private function folder(string $path): string
    {
        return str_replace('\\', '/', $path).'/';
    }
}
