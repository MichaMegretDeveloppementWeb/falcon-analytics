<?php

use Falcon\Analytics\Facades\Analytics;
use Illuminate\Support\Facades\Blade;

/*
 * `@analyticsConfig` ne porte plus que des donnees du serveur.
 *
 * Le code du collecteur est importe par l'hote dans son entree JavaScript
 * publique, et compile par son build. Ce qui reste ici ne peut pas l'etre · le
 * nom de la route change a chaque page, et le suivi se coupe quand
 * l'administratrice est connectee.
 */

it('renders the collector config when enabled', function () {
    config(['analytics.enabled' => true, 'analytics.endpoint' => '__analytics']);

    $html = Blade::render('@analyticsConfig');

    expect($html)->toContain('window.__falconAnalytics')
        ->toContain('__analytics');
});

it('no longer emits a script tag', function () {
    config(['analytics.enabled' => true, 'analytics.endpoint' => '__analytics']);

    // Le collecteur venait d'une balise `<script src>` servie par une route du
    // paquet. Elle a ete retiree le 2026-09-06 : deux exemplaires du meme
    // collecteur sur une page compteraient chaque visite deux fois.
    expect(Blade::render('@analyticsConfig'))->not->toContain('<script src');
});

it('renders nothing when disabled', function () {
    config(['analytics.enabled' => false]);

    expect(Blade::render('@analyticsConfig'))->toBe('');
});

it('renders nothing for an excluded context', function () {
    config(['analytics.enabled' => true]);
    Analytics::excludeUsing(fn () => true);

    expect(Blade::render('@analyticsConfig'))->toBe('');
});

/*
 * Ne rien emettre vaut suivi coupe.
 *
 * Le collecteur est desormais dans le bundle de l'hote, donc toujours charge.
 * C'est son premier test qui l'arrete · il lit `window.__falconAnalytics` et
 * sort quand il est absent. L'hote n'a aucune condition a ecrire de son cote.
 */
it('leaves the collector without its configuration when tracking is off', function () {
    config(['analytics.enabled' => false]);

    expect(Blade::render('@analyticsConfig'))->not->toContain('__falconAnalytics');
});
