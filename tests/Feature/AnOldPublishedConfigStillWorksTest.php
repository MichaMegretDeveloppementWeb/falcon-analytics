<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\TestCase;
use Falcon\Ui\Config\Defaults;

/**
 * Un hôte qui tient une copie publiée d'avant les espaces.
 *
 * Publier la configuration est un geste courant, et une copie publiée ne se
 * met pas à jour toute seule. Celle de notre propre hôte date d'avant le
 * sous-chantier des espaces · elle porte `dashboard`, `marketing` et `assets`
 * au premier niveau, et ne connaît ni `admin` ni `web`.
 *
 * **Le paquet doit continuer de marcher dans cet état**, sans quoi une mise à
 * jour casserait les écrans de quiconque a publié sa configuration un jour.
 * C'est exactement ce que `completeConfigFrom` achète, et cet essai vérifie
 * que le fichier du paquet en tire bien ce qu'il faut.
 *
 * Ce qui est vérifié ici n'est pas le mécanisme du kit — il a ses propres
 * essais — mais **notre fichier passé dedans** · une clé qu'on aurait rangée
 * au mauvais endroit ne se verrait pas autrement.
 *
 * L'application est montée, sans la base · le fichier du paquet appelle
 * `storage_path()` pour la base de géolocalisation, et ça ne se lit pas hors
 * d'une application.
 */
final class AnOldPublishedConfigStillWorksTest extends TestCase
{
    /**
     * La forme qu'avait la configuration avant les espaces, réduite à ce qui
     * compte · c'est la copie que notre hôte tient aujourd'hui.
     *
     * @return array<string, mixed>
     */
    private function theConfigAHostPublishedBeforeTheAreas(): array
    {
        return [
            'enabled' => true,
            'assets' => [
                'admin_css' => 'resources/css/app.css',
                'web_js' => 'resources/js/app.js',
            ],
            'dashboard' => [
                'route_prefix' => 'admin/analytics',
                'route_name' => 'analytics',
                'middleware' => ['web', 'auth:admin'],
                'layout' => 'layouts.analytics-admin',
                'layout_section' => 'content',
            ],
            'marketing' => [
                'route_prefix' => 'admin/marketing',
                'route_name' => 'marketing',
                'middleware' => ['web', 'auth:admin'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function completed(): array
    {
        /** @var array<string, mixed> $defaults */
        $defaults = require dirname(__DIR__, 2).'/config/analytics.php';

        return Defaults::completeMissing($defaults, $this->theConfigAHostPublishedBeforeTheAreas());
    }

    public function test_the_areas_appear_even_though_the_published_copy_ignores_them(): void
    {
        $completed = $this->completed();

        $this->assertSame('admin/analytics', $completed['admin']['route_prefix']);
        $this->assertSame('admin/marketing', $completed['admin']['marketing']['route_prefix']);
        $this->assertIsArray($completed['web']['middleware']);
        $this->assertNotSame([], $completed['web']['middleware'], "L'ingestion doit garder une pile de session.");
    }

    /**
     * Ce que l'hôte avait choisi ne doit pas être écrasé par la complétion.
     *
     * C'est l'autre moitié du contrat · compléter ce qui manque, sans jamais
     * reprendre la main sur ce qui est écrit.
     */
    public function test_it_leaves_what_the_host_had_chosen_alone(): void
    {
        $completed = $this->completed();

        $this->assertSame(['web', 'auth:admin'], $completed['dashboard']['middleware']);
        $this->assertSame('layouts.analytics-admin', $completed['dashboard']['layout']);
    }

    /**
     * Les blocs périmés survivent, et c'est sans conséquence · plus rien ne les
     * lit. Le dire ici évite qu'on s'en inquiète en les voyant.
     */
    public function test_the_stale_blocks_survive_harmlessly(): void
    {
        $completed = $this->completed();

        $this->assertArrayHasKey('assets', $completed);
        $this->assertArrayHasKey('dashboard', $completed);
    }
}
