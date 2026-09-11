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
        // Le banc monte le tableau de bord derrière ['web', 'auth:admin'] et le
        // module marketing garde son ['web', 'auth'] par défaut ; les deux
        // doivent rejouer sur /livewire/update, alors que « web » reste dehors,
        // Livewire l'exécutant toujours.
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
        // On rejoue le fournisseur pour que la cible de publication suive la
        // nouvelle racine.
        $this->app->register(AnalyticsServiceProvider::class, force: true);

        try {
            // Aucune option · la commande ne demande plus rien. Elle publie,
            // amorce l'environnement et migre, et c'est tout ce qu'elle touche.
            $this->artisan('analytics:install')->assertSuccessful();

            $this->assertFileExists($base.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'analytics.php');
            $this->assertStringContainsString('# --- Falcon Analytics', File::get($base.DIRECTORY_SEPARATOR.'.env'));
            $this->assertStringContainsString('ANALYTICS_ENABLED=true', File::get($base.DIRECTORY_SEPARATOR.'.env'));
            $this->assertStringContainsString('ANALYTICS_GSC_CLIENT_ID=', File::get($base.DIRECTORY_SEPARATOR.'.env.example'));
        } finally {
            File::deleteDirectory($base);
        }
    }

    /**
     * L'installateur ne demande plus aucun chemin.
     *
     * Il en demandait trois, et les ecrivait dans les entrees de l'hote · ce
     * modele a disparu avec la refonte de la suite. Le paquet compile et publie
     * ses fichiers, donc il n'y a plus rien a importer ni rien a demander.
     *
     * L'essai porte sur la definition de la commande plutot que sur un appel ·
     * une option reintroduite se verrait ici, au lieu d'etre decouverte le jour
     * ou quelqu'un se demande a quoi elle sert.
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
