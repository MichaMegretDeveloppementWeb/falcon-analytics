<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\TestCase;

/**
 * Le collecteur ne passe pas par PHP.
 *
 * Il l'a fait · une route le servait, avec un an de cache et une empreinte dans
 * l'adresse. Elle a été retirée le 2026-09-06. L'hôte l'a ensuite importé dans
 * son entrée JavaScript, et depuis le 2026-09-12 le paquet le compile et le
 * livre lui-même · `public/analytics.js`, publié dans le dossier public de
 * l'hôte et servi par le serveur web.
 *
 * Cet essai garde la route retirée · si elle revenait, deux exemplaires du même
 * collecteur pourraient se retrouver sur une page et compter chaque visite deux
 * fois.
 */
final class CollectorScriptRouteTest extends TestCase
{
    public function test_it_no_longer_serves_the_collector_script_itself(): void
    {
        $this->get('/__analytics.js')->assertNotFound();
    }

    /**
     * La source reste autonome · aucun `import`, aucune dépendance npm.
     *
     * Ce n'est plus l'hôte qui l'importe, mais la compilation en dépend quand
     * même · le kit émet une balise **classique** pour le fichier d'un paquet,
     * et un module y lèverait dans le navigateur. Une dépendance introduite ici
     * ferait sortir un module du compilateur.
     */
    public function test_the_source_stays_self_contained(): void
    {
        $path = dirname(__DIR__, 2).'/resources/js/collector.js';

        $this->assertFileExists($path);

        $source = (string) file_get_contents($path);

        $this->assertStringContainsString('sendBeacon', $source);
        $this->assertStringContainsString('__falconAnalytics', $source);

        // Le repli sur `fetch` avale son rejet · un endpoint injoignable ne
        // doit jamais faire remonter une promesse non geree dans la console de
        // l'hote.
        $this->assertStringContainsString('.catch(', $source);

        $this->assertSame(0, preg_match('/^\s*(import|export)\s/m', $source));
    }

    /**
     * Et le fichier livré en sort bien en fonction immédiate.
     *
     * C'est la contrepartie de l'essai ci-dessus, du côté du compilé · une
     * configuration de build changée pour produire un module ne se verrait
     * autrement qu'à l'exécution, dans une console d'hôte.
     */
    public function test_the_shipped_collector_is_a_classic_script(): void
    {
        $shipped = (string) file_get_contents(dirname(__DIR__, 2).'/public/analytics.js');

        $this->assertStringStartsWith('(function()', $shipped);
        $this->assertSame(0, preg_match('/\b(import|export)\s*[{*]/', $shipped));
    }
}
