<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\AnalyticsServiceProvider;
use Falcon\Analytics\Support\AnalyticsAssets;
use Falcon\Analytics\Tests\TestCase;
use Falcon\UiKit\Installer\AssetEntry;
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

    public function test_it_warns_when_a_module_is_mounted_with_an_empty_middleware_list(): void
    {
        config(['analytics.dashboard.middleware' => [], 'analytics.marketing.middleware' => []]);

        Log::shouldReceive('channel')->twice()->andReturnSelf();
        Log::shouldReceive('warning')
            ->twice()
            ->withArgs(fn (string $message): bool => str_contains($message, 'empty middleware list'));

        require dirname(__DIR__, 2).'/routes/analytics.php';

        $this->assertTrue(Route::has('analytics.overview'));
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
            // Les trois chemins en options · sans eux la commande demande, et
            // un essai sans entrée interactive n'a personne pour répondre.
            $this->artisan('analytics:install', [
                '--admin-css' => 'resources/css/admin.css',
                '--admin-js' => 'resources/js/admin.js',
                '--web-js' => 'resources/js/web.js',
            ])->assertSuccessful();

            $this->assertFileExists($base.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'analytics.php');
            $this->assertStringContainsString('# --- Falcon Analytics', File::get($base.DIRECTORY_SEPARATOR.'.env'));
            $this->assertStringContainsString('ANALYTICS_ENABLED=true', File::get($base.DIRECTORY_SEPARATOR.'.env'));
            $this->assertStringContainsString('ANALYTICS_GSC_CLIENT_ID=', File::get($base.DIRECTORY_SEPARATOR.'.env.example'));

            // Chaque import de la liste est écrit dans l'entrée qui porte sa
            // clé. La liste plutôt que des chemins recopiés · un import ajouté
            // à AnalyticsAssets est vérifié ici sans qu'on touche à cet essai,
            // et un chemin qui y changerait ne pourrait pas diverger de ce
            // qu'on assère.
            foreach (AnalyticsAssets::hostImports() as $key => [$kind, $vendorPath]) {
                $path = (string) config('analytics.assets.'.$key);
                $entry = $kind === 'css' ? AssetEntry::css($path) : AssetEntry::js($path);

                $this->assertTrue($entry->alreadyImports($vendorPath));
            }

            // Le collecteur ne va jamais dans le script du back-office · on ne
            // mesure pas les visites de la personne qui administre.
            $this->assertFalse(
                AssetEntry::js('resources/js/admin.js')
                    ->alreadyImports('vendor/falcon/analytics/resources/js/collector.js'),
            );

            // Et les réponses sont retenues, pour que rien ne soit à redemander.
            $this->assertSame('resources/css/admin.css', config('analytics.assets.admin_css'));
            $this->assertSame('resources/js/admin.js', config('analytics.assets.admin_js'));
            $this->assertSame('resources/js/web.js', config('analytics.assets.web_js'));
        } finally {
            File::deleteDirectory($base);
        }
    }

    /**
     * L'installateur ne demande que ce dont il se sert.
     *
     * Un `--web-css` a existé jusqu'au 2026-09-07 · demandé, rangé en
     * configuration, et lu par personne. Le paquet n'a aucun CSS public à
     * importer, donc la question n'avait rien à écrire.
     *
     * L'essai porte sur la définition de la commande plutôt que sur un appel ·
     * une option retirée fait échouer un appel qui la passe, ce qui dit « cet
     * essai est périmé » et non « cette option a bien disparu ».
     */
    public function test_it_asks_only_for_the_entries_it_writes_into(): void
    {
        $options = array_keys(Artisan::all()['analytics:install']->getDefinition()->getOptions());

        $this->assertContains('admin-css', $options);
        $this->assertContains('admin-js', $options);
        $this->assertContains('web-js', $options);
        $this->assertNotContains('web-css', $options);

        // Et la configuration publiée ne porte plus la clé.
        $this->assertArrayNotHasKey('web_css', config('analytics.assets'));
    }
}
