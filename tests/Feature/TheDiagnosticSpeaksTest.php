<?php

use Falcon\Analytics\Support\AnalyticsAssets;
use Falcon\UiKit\Installer\AssetEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

/**
 * `analytics:check` · ce qu un hote apprend de son installation.
 *
 * Le paquet echoue en silence, et c est ce qui rend ce diagnostic utile. Un
 * collecteur jamais rendu, un interrupteur laisse a false, une route qui
 * repond ailleurs : chacun laisse des ecrans qui fonctionnent devant un
 * tableau de bord vide, et un tableau de bord vide ne dit pas la difference
 * entre « personne n est venu » et « rien n a ete mesure ».
 *
 * Chaque essai coupe **un** point et verifie que la commande le nomme · une
 * commande qui echoue pour une raison qu elle n annonce pas ne vaut pas mieux
 * que le silence.
 *
 * Une seule attente de sous-chaine par appel · Mockery donne un ecrit a la
 * premiere attente qui l accepte, et deux sous-chaines de la meme ligne en
 * laisseraient une sans appel.
 */
beforeEach(function (): void {
    // Une installation saine, pour que chaque essai echoue pour la raison dont
    // il parle et non a cause d un autre point resté ouvert.
    soundInstallation();
});

afterEach(function (): void {
    File::deleteDirectory(resource_path('views/layouts'));

    foreach (['css/app.css', 'js/app.js', 'js/admin.js'] as $entry) {
        File::delete(resource_path($entry));
    }
});

/**
 * Ce que l hote a a faire, et rien de plus · les deux imports la ou la
 * configuration dit qu ils sont, et la directive dans une vue.
 */
function soundInstallation(): void
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

it('registers the command', function () {
    expect(Artisan::all())->toHaveKey('analytics:check');
});

it('passes on a sound installation', function () {
    $this->artisan('analytics:check')
        ->expectsOutputToContain('Installation valide.')
        ->assertSuccessful();
});

/**
 * **Le point qui manque le plus souvent**, et le seul dont le symptome soit
 * rigoureusement invisible · les ecrans fonctionnent, les routes repondent,
 * les tables existent, et pas une visite n arrive.
 */
it('says when no view carries the collector directive', function () {
    File::put(resource_path('views/layouts/web.blade.php'), '<body></body>');

    $this->artisan('analytics:check')
        ->expectsOutputToContain('@analyticsConfig')
        ->assertFailed();
});

/** La directive posee dans un `vendor/` n est pas la notre. */
it('does not accept the directive found in a package view', function () {
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
});

it('says when the master switch is off', function () {
    config(['analytics.enabled' => false]);

    $this->artisan('analytics:check')
        ->expectsOutputToContain('ANALYTICS_ENABLED')
        ->assertFailed();
});

/**
 * Le premier lecteur de `analytics.assets`.
 *
 * L installateur ecrivait ce bloc et personne ne le relisait · un hote qui
 * deplacait une entree n avait aucun moyen d apprendre que l import etait
 * reste derriere.
 */
it('says which import is missing from which entry', function () {
    File::put(resource_path('css/app.css'), "@import 'tailwindcss';");
    File::put(resource_path('js/app.js'), "import './bootstrap';");

    $this->artisan('analytics:check')
        ->expectsOutputToContain('npm run build')
        ->assertFailed();
});

/** Une cle de configuration absente est un defaut, pas une dispense. */
it('says when an assets key was never filled in', function () {
    config(['analytics.assets.web_js' => '']);

    $this->artisan('analytics:check')
        ->expectsOutputToContain('analytics:install')
        ->assertFailed();
});

/**
 * On ne mesure pas les visites de la personne qui administre.
 *
 * Le collecteur importe par le script du back-office ne casse rien · il fausse
 * chaque chiffre du tableau de bord, ce qui est pire, personne n allant
 * chercher une erreur.
 */
it('says when the collector is imported by the back office script', function () {
    // Le meme fichier sert de script public et d administration par defaut ·
    // on les separe pour pouvoir poser le defaut sur un seul.
    config(['analytics.assets.admin_js' => 'resources/js/admin.js']);

    AssetEntry::js('resources/js/admin.js')->import(AnalyticsAssets::hostImports()['web_js'][1]);

    $this->artisan('analytics:check')
        ->expectsOutputToContain('back-office')
        ->assertFailed();
});

it('says when a module is mounted without any middleware', function () {
    config(['analytics.dashboard.middleware' => []]);

    $this->artisan('analytics:check')
        ->expectsOutputToContain('Tableau de bord')
        ->assertFailed();
});

it('says when a named host layout does not exist', function () {
    config(['analytics.dashboard.layout' => 'layouts.absent']);

    $this->artisan('analytics:check')
        ->expectsOutputToContain('layouts.absent')
        ->assertFailed();
});

/** Un gabarit non nomme monte la coquille du paquet · ce n est pas un defaut. */
it('accepts a module that uses the package shell', function () {
    config(['analytics.dashboard.layout' => null, 'analytics.marketing.layout' => null]);

    $this->artisan('analytics:check')->assertSuccessful();
});

it('says when the ingestion endpoint answers nowhere', function () {
    config(['analytics.endpoint' => 'une-route-qui-n-existe-pas']);

    $this->artisan('analytics:check')
        ->expectsOutputToContain('route:clear')
        ->assertFailed();
});

/**
 * La geolocalisation n est un defaut que si on l a demandee.
 *
 * Sans cle, la fonctionnalite est eteinte et son absence est normale · une cle
 * posee sans base telechargee laisse une colonne « pays » vide que rien
 * n explique.
 */
it('stays quiet about geolocation until a licence key is set', function () {
    config(['analytics.geoip.license_key' => '']);

    $this->artisan('analytics:check')->assertSuccessful();
});

it('says when a licence key is set without the database', function () {
    config([
        'analytics.geoip.license_key' => 'une-cle',
        'analytics.geoip.database_path' => sys_get_temp_dir().'/absente.mmdb',
    ]);

    $this->artisan('analytics:check')
        ->expectsOutputToContain('geoip:download')
        ->assertFailed();
});
