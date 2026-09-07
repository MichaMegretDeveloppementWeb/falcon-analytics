<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Facades\Analytics;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Support\Facades\Blade;

/**
 * `@analyticsConfig` ne porte plus que des donnees du serveur.
 *
 * Le code du collecteur est importe par l'hote dans son entree JavaScript
 * publique, et compile par son build. Ce qui reste ici ne peut pas l'etre · le
 * nom de la route change a chaque page, et le suivi se coupe quand
 * l'administratrice est connectee.
 */
final class CollectorDirectiveTest extends TestCase
{
    public function test_it_renders_the_collector_config_when_enabled(): void
    {
        config(['analytics.enabled' => true, 'analytics.endpoint' => '__analytics']);

        $html = Blade::render('@analyticsConfig');

        $this->assertStringContainsString('window.__falconAnalytics', $html);
        $this->assertStringContainsString('__analytics', $html);
    }

    public function test_it_no_longer_emits_a_script_tag(): void
    {
        config(['analytics.enabled' => true, 'analytics.endpoint' => '__analytics']);

        // Le collecteur venait d'une balise `<script src>` servie par une route
        // du paquet. Elle a ete retiree le 2026-09-06 : deux exemplaires du
        // meme collecteur sur une page compteraient chaque visite deux fois.
        $this->assertStringNotContainsString('<script src', Blade::render('@analyticsConfig'));
    }

    public function test_it_renders_nothing_when_disabled(): void
    {
        config(['analytics.enabled' => false]);

        $this->assertSame('', Blade::render('@analyticsConfig'));
    }

    public function test_it_renders_nothing_for_an_excluded_context(): void
    {
        config(['analytics.enabled' => true]);
        Analytics::excludeUsing(fn () => true);

        $this->assertSame('', Blade::render('@analyticsConfig'));
    }

    /**
     * Ne rien emettre vaut suivi coupe.
     *
     * Le collecteur est desormais dans le bundle de l'hote, donc toujours
     * charge. C'est son premier test qui l'arrete · il lit
     * `window.__falconAnalytics` et sort quand il est absent. L'hote n'a aucune
     * condition a ecrire de son cote.
     */
    public function test_it_leaves_the_collector_without_its_configuration_when_tracking_is_off(): void
    {
        config(['analytics.enabled' => false]);

        $this->assertStringNotContainsString('__falconAnalytics', Blade::render('@analyticsConfig'));
    }
}
