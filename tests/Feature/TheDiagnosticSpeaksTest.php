<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * `analytics:check` · what a host learns about its installation.
 *
 * The package fails silently, and that is what makes this diagnostic useful. A
 * collector never rendered, a switch left at false, a route answering
 * elsewhere: each leaves working screens in front of an empty dashboard, and an
 * empty dashboard does not say the difference between "nobody came" and
 * "nothing was measured".
 *
 * Each test cuts **one** point and checks that the command names it: a command
 * that fails for a reason it does not announce is no better than silence.
 *
 * One substring expectation per call: Mockery hands a write to the first
 * expectation that accepts it, and two substrings from the same line would
 * leave one of them uncalled.
 */
final class TheDiagnosticSpeaksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A sound installation, so each test fails for the reason it speaks of
        // and not because of another point left open.
        $this->soundInstallation();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(resource_path('views/layouts'));

        foreach (['css/app.css', 'js/app.js', 'js/admin.js'] as $entry) {
            File::delete(resource_path($entry));
        }

        parent::tearDown();
    }

    /**
     * What the host has to do, and nothing more: the directive in a view.
     *
     * The two imports this setup used to write went with the model that asked
     * for them: the package compiles and publishes its files, and the host
     * imports none of them.
     */
    private function soundInstallation(): void
    {
        File::ensureDirectoryExists(resource_path('views/layouts'));
        File::put(resource_path('views/layouts/web.blade.php'), '<body>@analyticsCollector</body>');
    }

    public function test_it_registers_the_command(): void
    {
        $this->assertArrayHasKey('analytics:check', Artisan::all());
    }

    public function test_it_passes_on_a_sound_installation(): void
    {
        $this->artisan('analytics:check')
            ->expectsOutputToContain('Installation valide.')
            ->assertSuccessful();
    }

    /**
     * **The point that is missing most often**, and the only one whose symptom
     * is strictly invisible: the screens work, the routes answer, the tables
     * exist, and not a single visit arrives.
     */
    public function test_it_says_when_no_view_carries_the_collector_directive(): void
    {
        File::put(resource_path('views/layouts/web.blade.php'), '<body></body>');

        $this->artisan('analytics:check')
            ->expectsOutputToContain('@analyticsCollector')
            ->assertFailed();
    }

    /**
     * The case every existing installation lands in, and the one where a
     * generic answer would be least useful.
     *
     * Blade does not reject an unknown directive: it copies it to the output
     * as it stands, which was measured rather than assumed. So a host left on
     * the old name does not merely stop measuring — it prints the raw text
     * `@analyticsConfig` on its public pages. The diagnostic has to name the
     * view, or the reader goes looking for something that is already there.
     */
    public function test_it_names_the_view_left_on_the_former_directive(): void
    {
        File::put(resource_path('views/layouts/web.blade.php'), '<body>@analyticsConfig</body>');

        $this->artisan('analytics:check')
            ->expectsOutputToContain('layouts/web.blade.php')
            ->assertFailed();
    }

    /**
     * A rename done halfway, which is what a rename actually looks like.
     *
     * The sound layout is not the answer here: the other one still prints the
     * old directive to whoever opens the page it carries. Reporting a sound
     * installation because one file is right would hide exactly the state
     * someone needs told about.
     */
    public function test_it_still_says_it_when_only_one_of_two_views_was_renamed(): void
    {
        File::put(resource_path('views/layouts/web.blade.php'), '<body>@analyticsCollector</body>');
        File::put(resource_path('views/layouts/boutique.blade.php'), '<body>@analyticsConfig</body>');

        $this->artisan('analytics:check')
            ->expectsOutputToContain('layouts/boutique.blade.php')
            ->assertFailed();
    }

    /** The directive laid inside a `vendor/` is not ours. */
    public function test_it_does_not_accept_the_directive_found_in_a_package_view(): void
    {
        File::put(resource_path('views/layouts/web.blade.php'), '<body></body>');

        $vendorViews = base_path('vendor/quelqu-un/paquet/resources/views');
        File::ensureDirectoryExists($vendorViews);
        File::put($vendorViews.'/shell.blade.php', '@analyticsCollector');

        view()->addLocation($vendorViews);

        try {
            $this->artisan('analytics:check')->assertFailed();
        } finally {
            File::deleteDirectory(base_path('vendor/quelqu-un'));
        }
    }

    public function test_it_says_when_the_master_switch_is_off(): void
    {
        config(['analytics.enabled' => false]);

        $this->artisan('analytics:check')
            ->expectsOutputToContain('ANALYTICS_ENABLED')
            ->assertFailed();
    }

    /**
     * The copy being served, and not the shipped file.
     *
     * The package compiles and ships; the application publishes a copy. A
     * deployment that updates the package without republishing leaves last
     * month's stylesheet in place, and **nothing says so until somebody opens a
     * screen** — the kit raises then, but that can come long afterwards.
     *
     * **The expected directory is moved rather than the copy deleted.** The
     * tests run in parallel and share one public directory: removing the files
     * from it brought down, at random, another test in the middle of drawing a
     * screen. A configuration setting does not leave the process.
     */
    public function test_it_says_when_the_compiled_sheet_was_never_published(): void
    {
        config(['ui.assets.path' => 'vendor/falcon-jamais-publie']);

        $this->artisan('analytics:check')
            ->expectsOutputToContain('vendor:publish')
            ->assertFailed();
    }

    public function test_it_says_when_a_screen_group_is_mounted_without_any_middleware(): void
    {
        config(['analytics.admin.middleware' => []]);

        $this->artisan('analytics:check')
            ->expectsOutputToContain('Tableau de bord')
            ->assertFailed();
    }

    public function test_it_says_when_a_named_host_layout_does_not_exist(): void
    {
        config(['analytics.admin.layout' => 'layouts.absent']);

        $this->artisan('analytics:check')
            ->expectsOutputToContain('layouts.absent')
            ->assertFailed();
    }

    /** An unnamed layout mounts the package's own shell: that is not a defect. */
    public function test_it_accepts_screens_that_use_the_package_shell(): void
    {
        config(['analytics.admin.layout' => null]);

        $this->artisan('analytics:check')->assertSuccessful();
    }

    public function test_it_says_when_the_ingestion_endpoint_answers_nowhere(): void
    {
        config(['analytics.endpoint' => 'une-route-qui-n-existe-pas']);

        $this->artisan('analytics:check')
            ->expectsOutputToContain('route:clear')
            ->assertFailed();
    }

    /**
     * Geolocation is only a defect if it was asked for.
     *
     * With no key the feature is off and its absence is normal; a key set
     * without a downloaded database leaves a "country" column empty that
     * nothing explains.
     */
    public function test_it_stays_quiet_about_geolocation_until_a_licence_key_is_set(): void
    {
        config(['analytics.geoip.license_key' => '']);

        $this->artisan('analytics:check')->assertSuccessful();
    }

    public function test_it_says_when_a_licence_key_is_set_without_the_database(): void
    {
        config([
            'analytics.geoip.license_key' => 'une-cle',
            'analytics.geoip.database_path' => sys_get_temp_dir().'/absente.mmdb',
        ]);

        $this->artisan('analytics:check')
            ->expectsOutputToContain('geoip:download')
            ->assertFailed();
    }
}
