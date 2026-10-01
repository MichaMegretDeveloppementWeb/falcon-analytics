<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * A list that filters a screen says what it filters: a screen reader reads its
 * name before its value, and a list without one is only « liste déroulante ».
 */
final class EveryFilterListIsNamedTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_list_written_in_a_view_carries_a_name(): void
    {
        $views = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../resources/views', RecursiveDirectoryIterator::SKIP_DOTS));
        $unnamed = [];

        /** @var SplFileInfo $view */
        foreach ($views as $view) {
            preg_match_all('/<x-ui::select\b[^>]*>/s', (string) file_get_contents($view->getPathname()), $tags);

            foreach ($tags[0] as $tag) {
                if (preg_match('/\s:?(aria-label|id)=/', $tag) !== 1) {
                    $unnamed[] = $view->getFilename().' · '.preg_replace('/\s+/', ' ', $tag);
                }
            }
        }

        $this->assertSame([], $unnamed, 'Une liste ne dit pas ce qu’elle filtre · ni aria-label, ni id qu’un libellé atteindrait.');
    }

    /** @return array<string, array{string, list<string>}> */
    public static function screens(): array
    {
        return [
            'la vue d’ensemble' => ['analytics.admin.overview', ['Période', 'Visiteurs']],
            'les visiteurs' => ['analytics.admin.visitors', ['Visiteurs', 'Période']],
            'les sessions' => ['analytics.admin.sessions', ['Période', 'Visiteurs', 'Appareil', 'Source']],
        ];
    }

    /** @param  list<string>  $names */
    #[DataProvider('screens')]
    public function test_each_list_of_a_screen_is_named_by_what_it_filters(string $route, array $names): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
        Session::factory()->create(['pageview_count' => 1, 'device_type' => 'mobile', 'source' => 'google']);

        $html = $this->actingAs(TestAdmin::create([]), 'admin')
            ->get(route($route))
            ->assertSuccessful()
            ->getContent();

        preg_match_all('/<select\b[^>]*\baria-label="([^"]*)"/', (string) $html, $named);

        $this->assertSame($names, $named[1]);
        $this->assertSame(count($names), substr_count((string) $html, '<select'), 'Une liste de l’écran n’a pas de nom.');
    }
}
