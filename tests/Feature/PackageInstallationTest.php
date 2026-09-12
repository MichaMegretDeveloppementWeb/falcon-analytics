<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\AnalyticsServiceProvider;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertFalse(config('analytics.privacy.anonymize_ip'));
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

    public function test_it_registers_the_configured_module_middleware_as_livewire_persistent(): void
    {
        // The bench mounts the dashboard behind ['web', 'auth:admin'] and the
        // marketing module keeps its default ['web', 'auth']; both have to
        // replay on /livewire/update, while "web" stays out, Livewire always
        // running it.
        $persistent = Livewire::getPersistentMiddleware();

        $this->assertContains('auth:admin', $persistent);
        $this->assertContains('auth', $persistent);
        $this->assertNotContains('web', $persistent);
    }

    public function test_it_warns_when_a_screen_group_is_mounted_with_an_empty_middleware_list(): void
    {
        config(['analytics.admin.middleware' => [], 'analytics.admin.marketing.middleware' => []]);

        Log::shouldReceive('channel')->twice()->andReturnSelf();
        Log::shouldReceive('warning')
            ->twice()
            ->withArgs(fn (string $message): bool => str_contains($message, 'empty middleware list'));

        require dirname(__DIR__, 2).'/routes/admin.php';

        $this->assertTrue(Route::has('analytics.admin.overview'));
    }

    public function test_it_runs_analytics_install_for_real_against_a_temporary_base_path(): void
    {
        $base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'fa-install-'.uniqid();
        File::makeDirectory($base.DIRECTORY_SEPARATOR.'config', 0755, true);
        File::put($base.DIRECTORY_SEPARATOR.'.env', "APP_NAME=Host\n");
        File::put($base.DIRECTORY_SEPARATOR.'.env.example', "APP_NAME=Host\n");

        $this->app->setBasePath($base);
        // The provider is registered again so the publication target follows
        // the new root.
        $this->app->register(AnalyticsServiceProvider::class, force: true);

        try {
            // No options: the command asks for nothing any more. It publishes,
            // scaffolds the environment and migrates, and that is all it
            // touches.
            $this->artisan('analytics:install')->assertSuccessful();

            $this->assertFileExists($base.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'analytics.php');

            /*
             * The compiled files, without which the first screen raises: the
             * kit refuses to build the address of a file the host has not
             * published. The kit's installer publishes its own; nobody else
             * publishes ours.
             *
             * Both are named: a shipped file missing from this list would never
             * be claimed, and its absence would show on the first visit rather
             * than here.
             */
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
     * The installer asks for no path at all any more.
     *
     * It used to ask for three, and wrote them into the host's entries: that
     * model went with the rebuild of the suite. The package compiles and
     * publishes its files, so there is nothing left to import and nothing to
     * ask for.
     *
     * The test is on the command's definition rather than on a call: an option
     * reintroduced would show here, instead of being discovered the day
     * somebody wonders what it is for.
     */
    public function test_it_asks_for_no_path_at_all(): void
    {
        $options = array_keys(Artisan::all()['analytics:install']->getDefinition()->getOptions());

        $this->assertNotContains('admin-css', $options);
        $this->assertNotContains('admin-js', $options);
        $this->assertNotContains('web-js', $options);
        $this->assertNotContains('web-css', $options);

        $this->assertContains('force', $options, 'La seule option qui reste.');
    }
}
