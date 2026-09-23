<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\AnalyticsServiceProvider;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

final class PackageInstallationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_merges_the_package_configuration(): void
    {
        $this->assertTrue(config('analytics.enabled'));
        $this->assertSame(90, config('analytics.retention_days'));
        $this->assertSame(5, config('analytics.session.timeout_minutes'));
        $this->assertSame(20, config('analytics.session.heartbeat_seconds'));
        $this->assertTrue(config('analytics.privacy.anonymize_ip'));
    }

    public function test_it_creates_the_core_analytics_tables(): void
    {
        $this->assertTrue(Schema::hasTable('falcon_analytics_visitors'));
        $this->assertTrue(Schema::hasTable('falcon_analytics_sessions'));
        $this->assertTrue(Schema::hasTable('falcon_analytics_events'));
    }

    public function test_it_defines_the_expected_session_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('falcon_analytics_sessions', [
            'visitor_id', 'started_at', 'last_activity_at', 'ended_at',
            'ip', 'country', 'city', 'latitude', 'longitude',
            'device_type', 'browser', 'os', 'is_bot',
            'source', 'utm_source', 'subject_type', 'subject_id',
            'pageview_count', 'event_count',
        ]));
    }

    public function test_it_defines_the_expected_event_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('falcon_analytics_events', [
            'session_id', 'visitor_id', 'occurred_at', 'type', 'name',
            'route', 'url', 'target_selector', 'target_text', 'props', 'value',
        ]));
    }

    public function test_it_registers_the_install_command(): void
    {
        $this->assertArrayHasKey('analytics:install', Artisan::all());
    }

    public function test_it_adds_the_audit_indexes_on_events_and_sessions(): void
    {
        $eventIndexes = Collection::make(Schema::getIndexes('falcon_analytics_events'))->pluck('name');
        $sessionIndexes = Collection::make(Schema::getIndexes('falcon_analytics_sessions'))->pluck('name');

        $this->assertContains('fa_events_route_occurred_idx', $eventIndexes);
        $this->assertContains('fa_events_visitor_occurred_idx', $eventIndexes);
        $this->assertContains('fa_sessions_country_idx', $sessionIndexes);
    }

    /**
     * `auth:admin` and `auth` both replay on each Livewire update as the class
     * Livewire compares · the session stack stays out, Livewire always runs it.
     */
    public function test_it_registers_the_configured_module_middleware_as_livewire_persistent(): void
    {
        $persistent = Livewire::getPersistentMiddleware();

        $this->assertContains(Authenticate::class, $persistent);
        $this->assertNotContains('auth:admin', $persistent);
        $this->assertNotContains('auth', $persistent);
        $this->assertNotContains(StartSession::class, $persistent);
    }

    /**
     * An empty list mounts screens with no session and no auth, which shows as a
     * working page and not as an error. The two mount points are configured
     * apart, so each one warns for itself.
     */
    public function test_it_warns_when_a_screen_group_is_mounted_with_an_empty_middleware_list(): void
    {
        config(['analytics.admin.middleware' => [], 'analytics.admin.marketing.middleware' => []]);

        Log::shouldReceive('channel')->twice()->andReturnSelf();
        Log::shouldReceive('warning')
            ->twice()
            ->withArgs(fn (string $message): bool => str_contains($message, 'empty middleware list'));

        $this->app->register(AnalyticsServiceProvider::class, force: true);

        $this->assertTrue(Route::has('analytics.admin.overview'));
        $this->assertTrue(Route::has('analytics.admin.marketing.campaigns'));
    }

    public function test_it_runs_analytics_install_for_real_against_a_temporary_base_path(): void
    {
        $base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'fa-install-'.uniqid();
        File::makeDirectory($base.DIRECTORY_SEPARATOR.'config', 0755, true);
        File::put($base.DIRECTORY_SEPARATOR.'.env', "APP_NAME=Host\n");
        File::put($base.DIRECTORY_SEPARATOR.'.env.example', "APP_NAME=Host\n");

        $this->app->setBasePath($base);
        // setBasePath() keeps the bench's per-worker public path, so it moves too.
        $this->app->usePublicPath($base.DIRECTORY_SEPARATOR.'public');
        // Registered again so the publication target follows the new root.
        $this->app->register(AnalyticsServiceProvider::class, force: true);

        try {
            $this->artisan('analytics:install')->assertSuccessful();

            $this->assertFileExists($base.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'analytics.php');

            // Without them the first screen raises: the kit does not address an unpublished file.
            foreach (['analytics.css', 'analytics.js'] as $shipped) {
                $this->assertFileExists(
                    $base.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'
                    .DIRECTORY_SEPARATOR.'falcon'.DIRECTORY_SEPARATOR.'analytics'
                    .DIRECTORY_SEPARATOR.$shipped,
                    "A fresh installation has to publish {$shipped}.",
                );
            }

            $this->assertStringContainsString('# --- Falcon Analytics', File::get($base.DIRECTORY_SEPARATOR.'.env'));
            $this->assertStringContainsString('ANALYTICS_ENABLED=true', File::get($base.DIRECTORY_SEPARATOR.'.env'));
            $this->assertStringContainsString('ANALYTICS_GSC_CLIENT_ID=', File::get($base.DIRECTORY_SEPARATOR.'.env.example'));
        } finally {
            File::deleteDirectory($base);
        }
    }

    /**
     * The refusal comes before anything is written: a later one would leave an
     * installation that looks done and is not. A fresh Laravel application
     * defaults to SQLite, so this is the likeliest first contact.
     */
    public function test_the_installer_refuses_an_engine_the_package_does_not_promise(): void
    {
        $base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'fa-refus-'.uniqid();
        File::makeDirectory($base.DIRECTORY_SEPARATOR.'config', 0755, true);
        File::put($base.DIRECTORY_SEPARATOR.'.env', "APP_NAME=Host\n");

        $this->app->setBasePath($base);
        $this->app->register(AnalyticsServiceProvider::class, force: true);

        $original = config('database.default');

        try {
            // Restored in `finally`: the bench rolls its transaction back on the default connection.
            config([
                'database.connections.epreuve_sqlite' => ['driver' => 'sqlite', 'database' => ':memory:'],
                'database.default' => 'epreuve_sqlite',
            ]);

            $this->artisan('analytics:install')
                ->expectsOutputToContain('sqlite')
                ->assertFailed();

            $this->assertFileDoesNotExist(
                $base.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'analytics.php',
                'Le refus doit arriver avant la moindre publication.',
            );

            $this->assertStringNotContainsString(
                'ANALYTICS_ENABLED',
                File::get($base.DIRECTORY_SEPARATOR.'.env'),
                'Le refus doit arriver avant que le fichier d’environnement soit touché.',
            );
        } finally {
            config(['database.default' => $original]);
            File::deleteDirectory($base);
        }
    }

    /**
     * The package compiles and publishes its own files, so there is no path to
     * ask for. Read from the command's definition, so a path option shows here.
     */
    public function test_it_asks_for_no_path_at_all(): void
    {
        $options = array_keys(Artisan::all()['analytics:install']->getDefinition()->getOptions());

        $this->assertNotContains('admin-css', $options);
        $this->assertNotContains('admin-js', $options);
        $this->assertNotContains('web-js', $options);
        $this->assertNotContains('web-css', $options);

        $this->assertContains('force', $options, 'The only option left.');
    }
}
