<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\TestCase;
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
     * Ce que l'hote a a faire, et rien de plus · la directive dans une vue.
     *
     * Les deux imports que ce montage ecrivait ont disparu avec le modele qui
     * les demandait · le paquet compile et publie ses fichiers, l'hote n'en
     * importe aucun.
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
     * **Le point qui manque le plus souvent**, et le seul dont le symptome soit
     * rigoureusement invisible · les ecrans fonctionnent, les routes repondent,
     * les tables existent, et pas une visite n'arrive.
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

    /** La directive posee dans un `vendor/` n'est pas la notre. */
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
     * La copie servie, et non le fichier livré.
     *
     * Le paquet compile et livre ; l'application publie une copie. Un
     * déploiement qui met le paquet à jour sans republier laisse la feuille du
     * mois dernier en place, et **rien ne le dit tant que personne n'ouvre un
     * écran** · le kit lève alors, mais ça peut venir longtemps après.
     *
     * **On déplace le dossier attendu plutôt que d'effacer la copie.** Les
     * essais tournent en parallèle et partagent un même dossier public · en
     * retirer les fichiers faisait tomber, au hasard, un autre essai en train
     * de dessiner un écran. Un réglage ne sort pas du processus.
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

    /** Un gabarit non nomme monte la coquille du paquet · ce n'est pas un defaut. */
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
