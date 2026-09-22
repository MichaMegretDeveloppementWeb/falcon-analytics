<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
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
     * Le premier des points, et il nomme le moteur trouvé.
     *
     * Avant les migrations, parce que le moteur décide si elles veulent seulement
     * dire quelque chose. Et à chaque déploiement plutôt qu'une seule fois : une
     * connexion change en cours de vie, quand un hôte déplace sa base ou en
     * pointe une seconde ailleurs.
     */
    public function test_it_names_the_database_engine_it_found(): void
    {
        $this->artisan('analytics:check')
            ->expectsOutputToContain('mysql')
            ->assertSuccessful();
    }

    /**
     * Et il **tombe** sur un moteur que le paquet ne promet pas.
     *
     * Une connexion de configuration suffit · la garde ne lit que le nom du
     * pilote, aucun schéma n'est touché. La valeur d'origine est remise dans le
     * `finally`, sans quoi le banc ne pourrait plus annuler sa transaction.
     *
     * **Ce qui est attendu est « MariaDB », et pas « sqlite ».** Une base vide
     * fait aussi tomber les migrations et les résumés · un essai qui se
     * contenterait du code de sortie, ou du nom du pilote qui paraît ailleurs,
     * passerait sans que la ligne du moteur existe. Le mot MariaDB n'est écrit
     * que dans ce refus-là, et il ne se coupe pas au retour à la ligne du
     * tableau. Vérifié en neutralisant la garde · l'essai tombe.
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
     * The collector's stack without a session, and every beacon then fails.
     *
     * Without consent — the default — the visitor's identifier lives in the
     * session. The stack is fixed when the routes load, so the configuration is
     * laid before the application boots, and the route itself is what is read.
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
     * A declarations file that stops on an error.
     *
     * What came before the error is kept and the rest is ignored, so the
     * conversions declared after it vanish from the screens, and the log is
     * the only other place that says so.
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
     * The identity block is the one thing a host still fills by hand, and a
     * typo in it is the quietest failure of the whole installation.
     *
     * The subject resolution filters the configured guards against
     * `auth.guards`, which is right at runtime — a typo must not break a page.
     * The cost is that every visitor then stays anonymous, no screen is empty,
     * and nothing says why.
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
     * A name column the guard's own table does not carry.
     *
     * The screens then show the label and the id — « Client #12 » — for as long
     * as nobody looks. The table is asked of the resolver, so the diagnostic
     * and the reads cannot come to disagree.
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

    /** Tracking nobody is a valid choice, and it is not reported as a fault. */
    public function test_it_accepts_an_installation_that_tracks_no_subject(): void
    {
        config([
            'analytics.identity.subject_guards' => [],
            'analytics.identity.subjects' => [],
        ]);

        $this->artisan('analytics:check')->assertSuccessful();
    }

    /**
     * Trusted proxies cannot be settled from a console, and the point says so
     * rather than pretending to a verdict · it never blocks.
     *
     * Behind a reverse proxy without that setting, every visit carries the
     * proxy's address: one visitor, one country, for the whole site. The
     * numbers stay plausible, which is what makes it expensive to find.
     */
    /**
     * A retention that cannot be read as a number of days is blocking.
     *
     * Zero used to mean « keep everything », which is the opposite of what one
     * writes it for. It is refused now, and the erasing fails every night while
     * saying so to a log nobody reads · this is what makes it visible.
     */
    public function test_it_blocks_on_a_retention_that_is_not_a_number_of_days(): void
    {
        config(['analytics.retention_days' => 0]);

        $this->artisan('analytics:check')
            ->expectsOutputToContain('Conservation')
            ->assertFailed();
    }

    /**
     * A marketing ceiling that means nothing stops the marketing screens, and
     * this is where it is said.
     */
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

    /** Never erasing is a choice, not a defect. */
    public function test_it_accepts_an_installation_that_never_erases(): void
    {
        config(['analytics.retention_days' => null]);

        $this->artisan('analytics:check')->assertSuccessful();
    }

    /**
     * A backlog of summaries is how a dead scheduler shows, and nothing else
     * says it.
     *
     * When the scheduler stops, the erasing stops with it — nothing is lost —
     * and every screen goes on answering from the rows. The only visible trace
     * is this number, which is why the diagnostic carries it.
     *
     * Not blocking · an installation working through the history it had before
     * the summaries existed shows the same thing, and it is catching up.
     */
    public function test_it_points_at_a_backlog_of_summaries_without_blocking_on_it(): void
    {
        $visitor = Visitor::create(['uuid' => 'u-'.uniqid(), 'first_seen_at' => now(), 'last_seen_at' => now()]);
        $session = Session::create([
            'visitor_id' => $visitor->id,
            'started_at' => now()->subDays(10),
            'last_activity_at' => now()->subDays(10),
            'is_bot' => false,
        ]);
        Event::create([
            'session_id' => $session->id,
            'visitor_id' => $visitor->id,
            'type' => EventType::Pageview,
            'occurred_at' => now()->subDays(10),
        ]);

        $this->artisan('analytics:check')
            ->expectsOutputToContain('résumés')
            ->assertSuccessful();
    }

    /** And it says nothing about it once the summarising has caught up. */
    public function test_it_stays_quiet_about_summaries_once_they_are_up_to_date(): void
    {
        $this->artisan('analytics:archive')->assertSuccessful();

        $this->artisan('analytics:check')
            ->expectsOutputToContain('À jour')
            ->assertSuccessful();
    }

    public function test_it_points_at_the_proxy_setting_without_blocking_on_it(): void
    {
        config(['trustedproxy.proxies' => null, 'app.trusted_proxies' => null]);

        $this->artisan('analytics:check')
            ->expectsOutputToContain('proxy')
            ->assertSuccessful();
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
