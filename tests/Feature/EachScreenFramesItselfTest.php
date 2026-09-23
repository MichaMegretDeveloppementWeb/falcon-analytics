<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\TestCase;
use FilesystemIterator;
use Illuminate\Support\Facades\Blade;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * A screen frames itself, on its own container.
 *
 * The thin view states what the screen takes of the space it is given, through
 * `<x-analytics::page class="…">`. Those classes cross the attribute bag down
 * to `.an-root`, the only place a width is decided. A page that loses half its
 * width shrinks without overflowing, so nothing else would notice.
 *
 * @see ScreenMountingTest for the layer above: the host's layout, which gives
 *      the space without deciding its use.
 */
final class EachScreenFramesItselfTest extends TestCase
{
    /** An invented class: what holds is that a declaration arrives, whatever value a screen picks. */
    public function test_the_container_receives_what_the_screen_declares(): void
    {
        $html = Blade::render(
            '<x-analytics::page area="admin" class="an:max-w-[42em]">une page</x-analytics::page>'
        );

        $this->assertSame(1, preg_match('/<div\b[^>]*\bdata-an-area="admin"[^>]*>/', $html, $matches),
            "La page n'a pas rendu son conteneur.");

        $this->assertStringContainsString('an-root', $matches[0]);
        $this->assertStringContainsString('an:max-w-[42em]', $matches[0],
            "La classe déclarée par l'écran n'atteint plus son conteneur.");
    }

    /** A view that states nothing renders flush to both edges, with no error to say so. */
    public function test_every_admin_screen_states_its_frame(): void
    {
        $views = $this->adminViews();

        $this->assertNotSame([], $views, 'Aucune vue lue : le chemin est faux.');

        foreach ($views as $view) {
            $markup = (string) file_get_contents($view->getPathname());

            $this->assertMatchesRegularExpression(
                '/<x-analytics::page\b[^>]*\sclass="[^"]+"/',
                $markup,
                $view->getFilename().' ne dit pas ce qu\'il prend de la place qu\'on lui rend.',
            );
        }
    }

    /**
     * The admin area's thin views.
     *
     * @return list<SplFileInfo>
     */
    private function adminViews(): array
    {
        $found = [];

        $walk = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            dirname(__DIR__, 2).'/resources/views/admin',
            FilesystemIterator::SKIP_DOTS,
        ));

        foreach ($walk as $file) {
            if ($file instanceof SplFileInfo && str_ends_with($file->getFilename(), '.blade.php')) {
                $found[] = $file;
            }
        }

        return $found;
    }
}
