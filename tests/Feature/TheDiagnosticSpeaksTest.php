<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\Attributes\DefineEnvironment;

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

        // Sound by default, so each test fails only on the point it cuts.
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

    /** What the host has to do, and nothing more: the directive in a view. */
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
     * The engine is checked before the migrations, since it decides whether they
     * mean anything, and on every run, since a host can move its database.
     */
    public function test_it_names_the_database_engine_it_found(): void
    {
        $this->artisan('analytics:check')
            ->expectsOutputToContain('mysql')
            ->assertSuccessful();
    }

    /**
     * The expected word is MariaDB, which only this refusal writes · an empty
     * database also fails the migrations and the summaries, so the exit code or
     * the driver name alone would pass without the engine line.
     */
    public function test_it_falls_on_an_engine_the_package_does_not_promise(): void
    {
        $original = config('database.default');

        try {
            config([
                'database.connections.epreuve_sqlite' => ['driver' => 'sqlite', 'database' => ':memory:'],
                'database.default' => 'epreuve_sqlite',
            ]);

            $this->artisan('analytics:check')
                ->expectsOutputToContain('MariaDB')
                ->assertFailed();
        } finally {
            config(['database.default' => $original]);
        }
    }

    /**
     * Its symptom is invisible: the screens work, the routes answer, the tables
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
     * Blade copies an unknown directive to the output as it stands, so a view
     * left on the former name prints `@analyticsConfig` on public pages. The
     * diagnostic names the view, or the reader looks for what is already there.
     */
    public function test_it_names_the_view_left_on_the_former_directive(): void
    {
        File::put(resource_path('views/layouts/web.blade.php'), '<body>@analyticsConfig</body>');

        $this->artisan('analytics:check')
            ->expectsOutputToContain('layouts/web.blade.php')
            ->assertFailed();
    }

    /**
     * One sound layout does not make a sound installation · the other one still
     * prints the former directive on the pages it carries.
     */
    public function test_it_still_says_it_when_only_one_of_two_views_was_renamed(): void
    {
        File::put(resource_path('views/layouts/web.blade.php'), '<body>@analyticsCollector</body>');
        File::put(resource_path('views/layouts/boutique.blade.php'), '<body>@analyticsConfig</body>');

        $this->artisan('analytics:check')
            ->expectsOutputToContain('layouts/boutique.blade.php')
            ->assertFailed();
    }

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
     * The copy being served is read, not the shipped file · a deployment that
     * skips the republication keeps an old stylesheet nothing reports until a
     * screen opens. The expected directory is moved by configuration, which
     * stays in this process, so no published file is deleted under another test.
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
        config(['analytics.layouts.admin' => 'layouts.absent']);

        $this->artisan('analytics:check')
            ->expectsOutputToContain('layouts.absent')
            ->assertFailed();
    }

    /** An unnamed layout mounts the package's own shell: that is not a defect. */
    public function test_it_accepts_screens_that_use_the_package_shell(): void
    {
        config(['analytics.layouts.admin' => null]);

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
     * Without consent, the default, the visitor's identifier lives in the session,
     * so every beacon fails. The stack is fixed when the routes load, so the
     * configuration is laid before boot and the route itself is read.
     */
    #[DefineEnvironment('withACollectorStackWithoutSession')]
    public function test_it_says_when_the_collector_stack_carries_no_session(): void
    {
        $this->artisan('analytics:check')
            ->expectsOutputToContain('StartSession')
            ->assertFailed();
    }

    #[DefineEnvironment('withAnEmptyCollectorStack')]
    public function test_it_says_when_the_collector_stack_is_empty(): void
    {
        $this->artisan('analytics:check')
            ->expectsOutputToContain('StartSession')
            ->assertFailed();
    }

    /** A group that carries the session counts for what it carries. */
    #[DefineEnvironment('withTheWebGroupOnTheCollector')]
    public function test_it_accepts_a_collector_stack_named_by_its_group(): void
    {
        $this->artisan('analytics:check')->assertSuccessful();
    }

    /**
     * What precedes the error is kept and the rest ignored, so the conversions
     * declared after it vanish from the screens and only the log says so.
     */
    public function test_it_names_an_events_file_that_does_not_load_whole(): void
    {
        config(['analytics.events_path' => __DIR__.'/../Fixtures/analytics-events-broken.php']);
        $this->app->forgetInstance(EventRegistry::class);

        $this->artisan('analytics:check')
            ->expectsOutputToContain('analytics-events-broken.php')
            ->assertFailed();
    }

    public function test_it_names_a_funnels_file_that_does_not_load_whole(): void
    {
        config(['analytics.funnels_path' => __DIR__.'/../Fixtures/analytics-funnels-broken.php']);
        $this->app->forgetInstance(FunnelRegistry::class);

        $this->artisan('analytics:check')
            ->expectsOutputToContain('analytics-funnels-broken.php')
            ->assertFailed();
    }

    protected function withACollectorStackWithoutSession(Application $app): void
    {
        $app['config']->set('analytics.web.middleware', [EncryptCookies::class, AddQueuedCookiesToResponse::class]);
    }

    protected function withAnEmptyCollectorStack(Application $app): void
    {
        $app['config']->set('analytics.web.middleware', []);
    }

    protected function withTheWebGroupOnTheCollector(Application $app): void
    {
        $app['config']->set('analytics.web.middleware', ['web']);
    }

    /**
     * The subject resolution drops a guard missing from `auth.guards`, so a typo
     * breaks no page · every visitor then stays anonymous and nothing says why.
     */
    public function test_it_says_when_a_named_guard_does_not_exist(): void
    {
        config(['analytics.identity.subject_guards' => ['client', 'cliennt']]);

        $this->artisan('analytics:check')
            ->expectsOutputToContain('cliennt')
            ->assertFailed();
    }

    /** An excluded guard that does not exist excludes nobody, just as quietly. */
    public function test_it_says_when_an_excluded_guard_does_not_exist(): void
    {
        config(['analytics.identity.exclude_guards' => ['admin', 'staf']]);

        $this->artisan('analytics:check')
            ->expectsOutputToContain('staf')
            ->assertFailed();
    }

    /**
     * The screens would show the label and the id, « Client #12 ». The table is
     * asked of the resolver, so the diagnostic and the reads cannot disagree.
     */
    public function test_it_says_when_a_name_column_does_not_exist(): void
    {
        config(['analytics.identity.subjects.client' => [
            'label' => 'Client',
            'name' => ['first_name', 'nom_de_famille'],
        ]]);

        $this->artisan('analytics:check')
            ->expectsOutputToContain('nom_de_famille')
            ->assertFailed();
    }

    public function test_it_accepts_an_installation_that_tracks_no_subject(): void
    {
        config([
            'analytics.identity.subject_guards' => [],
            'analytics.identity.subjects' => [],
        ]);

        $this->artisan('analytics:check')->assertSuccessful();
    }

    /**
     * Zero is refused, so the nightly erasing fails and says so only to a log ·
     * this is where it shows.
     */
    public function test_it_blocks_on_a_retention_that_is_not_a_number_of_days(): void
    {
        config(['analytics.retention_days' => 0]);

        $this->artisan('analytics:check')
            ->expectsOutputToContain('Conservation')
            ->assertFailed();
    }

    /** A ceiling that means nothing stops the marketing screens. */
    public function test_it_blocks_on_a_marketing_ceiling_that_is_not_a_number_of_sessions(): void
    {
        config(['analytics.marketing.max_sessions' => 0]);

        $this->artisan('analytics:check')
            ->expectsOutputToContain('marketing.max_sessions')
            ->assertFailed();
    }

    public function test_it_names_the_marketing_ceiling_it_found(): void
    {
        config(['analytics.marketing.max_sessions' => 50000]);

        $this->artisan('analytics:check')
            ->expectsOutputToContain("50\u{202F}000")
            ->assertSuccessful();
    }

    /** The screens write their numbers through intl, which the package requires and the diagnostic reads again. */
    public function test_it_says_the_intl_extension_is_there(): void
    {
        $this->artisan('analytics:check')
            ->expectsOutputToContain('Extension intl')
            ->assertSuccessful();
    }

    public function test_it_accepts_an_installation_that_never_erases(): void
    {
        config(['analytics.retention_days' => null]);

        $this->artisan('analytics:check')->assertSuccessful();
    }

    /**
     * A backlog of summaries is how a stopped scheduler shows, the screens going
     * on answering from the rows. It does not block · an installation catching
     * up on older history shows the same backlog.
     */
    public function test_it_points_at_a_backlog_of_summaries_without_blocking_on_it(): void
    {
        Event::factory()->for(Session::factory()->at(now()->subDays(10)))->create(['occurred_at' => now()->subDays(10)]);

        $this->artisan('analytics:check')
            ->expectsOutputToContain('résumés')
            ->assertSuccessful();
    }

    public function test_it_stays_quiet_about_summaries_once_they_are_up_to_date(): void
    {
        $this->artisan('analytics:archive')->assertSuccessful();

        $this->artisan('analytics:check')
            ->expectsOutputToContain('À jour')
            ->assertSuccessful();
    }

    /**
     * Trusted proxies cannot be settled from a console, so the point never
     * blocks. Without that setting behind a reverse proxy, every visit carries
     * the proxy's address: one visitor, one country, for the whole site.
     */
    public function test_it_points_at_the_proxy_setting_without_blocking_on_it(): void
    {
        config(['trustedproxy.proxies' => null, 'app.trusted_proxies' => null]);

        $this->artisan('analytics:check')
            ->expectsOutputToContain('proxy')
            ->assertSuccessful();
    }

    /**
     * With no key the feature is off and its absence is normal · a key without a
     * downloaded database leaves the country column empty with nothing to say why.
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
