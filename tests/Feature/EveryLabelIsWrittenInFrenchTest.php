<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The package writes its dates, numbers and country names in French whatever
 * language the host speaks: its labels are fixed in French, and a value
 * following the host's locale would make a page that mixes the two.
 */
final class EveryLabelIsWrittenInFrenchTest extends TestCase
{
    use RefreshDatabase;

    /** The one file allowed to hand a date to Carbon's translations, in French. */
    private const string DATE_LABEL = 'src/Support/DateLabel.php';

    /** Nothing reads the host's language, and every date goes through `DateLabel`. */
    public function test_nothing_is_written_in_the_host_language(): void
    {
        $root = dirname(__DIR__, 2);
        $offenders = [];

        foreach (['src', 'resources/views'] as $directory) {
            /** @var SplFileInfo $file */
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory)) as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                $lines = file($file->getPathname());

                foreach ($lines === false ? [] : $lines as $number => $line) {
                    $readsTheHost = str_contains($line, 'getLocale(');
                    $formatsADate = preg_match('/->(translatedFormat|isoFormat)\(/', $line) === 1 && $path !== self::DATE_LABEL;

                    if ($readsTheHost || $formatsADate) {
                        $offenders[] = $path.':'.($number + 1);
                    }
                }
            }
        }

        $this->assertSame([], $offenders, 'These lines write in the host language.');
    }

    /** A host in English still reads the list of sessions in French · its dates and its countries. */
    public function test_the_sessions_read_in_french_for_a_host_in_english(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
        app()->setLocale('en');

        Session::factory()->at(CarbonImmutable::parse('2026-06-12 09:00:00'))
            ->create(['pageview_count' => 1, 'country' => 'DE']);

        $this->actingAs(TestAdmin::create([]), 'admin')
            ->get(route('analytics.admin.sessions'))
            ->assertSuccessful()
            ->assertSeeText('12 juin')
            ->assertSeeText('Allemagne')
            ->assertDontSeeText('Jun')
            ->assertDontSeeText('Germany');
    }
}
