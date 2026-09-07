<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\TestCase;

/**
 * Le collecteur n'est plus servi par le paquet.
 *
 * Il l'etait par une route, avec un an de cache et une empreinte dans
 * l'adresse. L'hote l'importe desormais dans son entree JavaScript publique ·
 *
 *     import '../../vendor/falcon/analytics/resources/js/collector.js';
 *
 * C'est son build qui le nomme, le versionne et le sert. Le fichier ne passe
 * plus par PHP, et une mise a jour du paquet le rejoue au prochain
 * `npm run build`.
 *
 * Cet essai garde la route retiree · si elle revenait, deux exemplaires du meme
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
     * Le fichier reste livre, et il reste autonome · aucun `import`, aucune
     * dependance npm. C'est ce qui permet a l'hote de l'importer tel quel, sans
     * rien installer.
     */
    public function test_it_ships_a_self_contained_collector_for_the_host_to_import(): void
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
}
