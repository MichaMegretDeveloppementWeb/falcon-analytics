<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Support\AnalyticsAssets;
use Falcon\Analytics\Tests\TestCase;
use Falcon\UiKit\Installer\AssetEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * `analytics:check` · ce qu'un hote apprend de son installation.
 *
 * Le paquet echoue en silence, et c'est ce qui rend ce diagnostic utile. Un
 * collecteur jamais rendu, un interrupteur laisse a false, une route qui repond
 * ailleurs : chacun laisse des ecrans qui fonctionnent devant un tableau de
 * bord vide, et un tableau de bord vide ne dit pas la difference entre
 * « personne n'est venu » et « rien n'a ete mesure ».
 *
 * Chaque essai coupe **un** point et verifie que la commande le nomme · une
 * commande qui echoue pour une raison qu'elle n'annonce pas ne vaut pas mieux
 * que le silence.
 *
 * Une seule attente de sous-chaine par appel · Mockery donne un ecrit a la
 * premiere attente qui l'accepte, et deux sous-chaines de la meme ligne en
 * laisseraient une sans appel.
 */
final class TheDiagnosticSpeaksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Une installation saine, pour que chaque essai echoue pour la raison
        // dont il parle et non a cause d'un autre point reste ouvert.
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
     * Ce que l'hote a a faire, et rien de plus · les deux imports la ou la
     * configuration dit qu'ils sont, et la directive dans une vue.
     */
    private function soundInstallation(): void
    {
        File::ensureDirectoryExists(resource_path('css'));
        File::ensureDirectoryExists(resource_path('js'));
        File::ensureDirectoryExists(resource_path('views/layouts'));

        foreach (AnalyticsAssets::hostImports() as $key => [$kind, $vendorPath]) {
            $path = (string) config('analytics.assets.'.$key);

            ($kind === 'css' ? AssetEntry::css($path) : AssetEntry::js($path))->import($vendorPath);
        }

        File::put(resource_path('views/layouts/web.blade.php'), '<body>@analyticsConfig</body>');
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
     * **Le point qui manque le plus souvent**, et le seul dont le symptome soit
     * rigoureusement invisible · les ecrans fonctionnent, les routes repondent,
     * les tables existent, et pas une visite n'arrive.
     */
    public function test_it_says_when_no_view_carries_the_collector_directive(): void
    {
        File::put(resource_path('views/layouts/web.blade.php'), '<body></body>');

        $this->artisan('analytics:check')
            ->expectsOutputToContain('@analyticsConfig')
            ->assertFailed();
    }

    /** La directive posee dans un `vendor/` n'est pas la notre. */
    public function test_it_does_not_accept_the_directive_found_in_a_package_view(): void
    {
        File::put(resource_path('views/layouts/web.blade.php'), '<body></body>');

        $vendorViews = base_path('vendor/quelqu-un/paquet/resources/views');
        File::ensureDirectoryExists($vendorViews);
        File::put($vendorViews.'/shell.blade.php', '@analyticsConfig');

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
     * Le premier lecteur de `analytics.assets`.
     *
     * L'installateur ecrivait ce bloc et personne ne le relisait · un hote qui
     * deplacait une entree n'avait aucun moyen d'apprendre que l'import etait
     * reste derriere.
     */
    public function test_it_says_which_import_is_missing_from_which_entry(): void
    {
        File::put(resource_path('css/app.css'), "@import 'tailwindcss';");
        File::put(resource_path('js/app.js'), "import './bootstrap';");

        $this->artisan('analytics:check')
            ->expectsOutputToContain('npm run build')
            ->assertFailed();
    }

    /** Une cle de configuration absente est un defaut, pas une dispense. */
    public function test_it_says_when_an_assets_key_was_never_filled_in(): void
    {
        config(['analytics.assets.web_js' => '']);

        $this->artisan('analytics:check')
            ->expectsOutputToContain('analytics:install')
            ->assertFailed();
    }

    /**
     * On ne mesure pas les visites de la personne qui administre.
     *
     * Le collecteur importe par le script du back-office ne casse rien · il
     * fausse chaque chiffre du tableau de bord, ce qui est pire, personne
     * n'allant chercher une erreur.
     */
    public function test_it_says_when_the_collector_is_imported_by_the_back_office_script(): void
    {
        // Le meme fichier sert de script public et d'administration par
        // defaut · on les separe pour pouvoir poser le defaut sur un seul.
        config(['analytics.assets.admin_js' => 'resources/js/admin.js']);

        AssetEntry::js('resources/js/admin.js')->import(AnalyticsAssets::hostImports()['web_js'][1]);

        $this->artisan('analytics:check')
            ->expectsOutputToContain('back-office')
            ->assertFailed();
    }

    public function test_it_says_when_a_module_is_mounted_without_any_middleware(): void
    {
        config(['analytics.dashboard.middleware' => []]);

        $this->artisan('analytics:check')
            ->expectsOutputToContain('Tableau de bord')
            ->assertFailed();
    }

    public function test_it_says_when_a_named_host_layout_does_not_exist(): void
    {
        config(['analytics.dashboard.layout' => 'layouts.absent']);

        $this->artisan('analytics:check')
            ->expectsOutputToContain('layouts.absent')
            ->assertFailed();
    }

    /** Un gabarit non nomme monte la coquille du paquet · ce n'est pas un defaut. */
    public function test_it_accepts_a_module_that_uses_the_package_shell(): void
    {
        config(['analytics.dashboard.layout' => null, 'analytics.marketing.layout' => null]);

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
     * La geolocalisation n'est un defaut que si on l'a demandee.
     *
     * Sans cle, la fonctionnalite est eteinte et son absence est normale · une
     * cle posee sans base telechargee laisse une colonne « pays » vide que rien
     * n'explique.
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
