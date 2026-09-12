<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Facades\Analytics;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use RuntimeException;

/**
 * `@analyticsCollector` apporte le collecteur, et ce qu'il a besoin de savoir.
 *
 * Deux choses voyagent, et pas de la même façon · **le script** est compilé,
 * livré par le paquet et publié par l'hôte, donc il est déclaré au kit qui le
 * place ; **la configuration** ne peut pas être compilée — le nom de la route
 * change à chaque page, et le suivi se coupe quand l'administratrice est
 * connectée — donc elle est posée en ligne.
 *
 * La directive s'appelait `@analyticsConfig` du temps où le code du collecteur
 * venait de l'entrée JavaScript de l'hôte. Le paquet le livre désormais, et le
 * nom le dit.
 */
final class CollectorDirectiveTest extends TestCase
{
    public function test_it_renders_the_collector_config_when_enabled(): void
    {
        config(['analytics.enabled' => true, 'analytics.endpoint' => '__analytics']);

        $html = Blade::render('@analyticsCollector');

        $this->assertStringContainsString('window.__falconAnalytics', $html);
        $this->assertStringContainsString('__analytics', $html);
    }

    /**
     * **L'essai qui compte** · une page publique reçoit le collecteur, et rien
     * d'autre.
     *
     * La vue déclare le fichier au kit, qui bâtit son adresse versionnée et la
     * pose avant la fermeture du corps · le gabarit ci-dessous ne rend aucune
     * directive de pile, exactement comme la page publique d'un site ordinaire.
     *
     * La seconde moitié de l'essai est celle qui protège l'hôte, et elle garde
     * une correction du kit datée du 2026-09-12. Son injection posait alors
     * **ses propres** balises dès qu'un paquet avait déclaré quoi que ce soit ·
     * une page publique se retrouvait avec le reset du kit, soixante
     * kilo-octets de feuille d'administration, un script et un conteneur de
     * notifications. Le site de l'hôte en sortait redessiné.
     */
    public function test_a_public_page_receives_the_collector_and_nothing_else(): void
    {
        config(['analytics.enabled' => true, 'analytics.endpoint' => '__analytics']);

        Route::get('/une-page-publique', fn (): string => Blade::render(
            '<!DOCTYPE html><html><head><title>t</title></head><body><p>du contenu</p>@analyticsCollector</body></html>'
        ));

        $html = (string) $this->get('/une-page-publique')->assertSuccessful()->getContent();

        $this->assertStringContainsString('window.__falconAnalytics', $html);
        $this->assertMatchesRegularExpression(
            '#<script src="[^"]*analytics/analytics\.js\?v=[^"]+" defer#',
            $html,
            'Le script doit arriver versionné et différé.',
        );

        foreach (['ui.css', 'ui-base.css', 'ui.js'] as $ofTheKit) {
            $this->assertStringNotContainsString(
                $ofTheKit,
                $html,
                "Une page publique ne doit rien recevoir du kit · {$ofTheKit} y est.",
            );
        }
    }

    public function test_it_renders_nothing_when_disabled(): void
    {
        config(['analytics.enabled' => false]);

        $this->assertSame('', trim(Blade::render('@analyticsCollector')));
    }

    public function test_it_renders_nothing_for_an_excluded_context(): void
    {
        config(['analytics.enabled' => true]);
        Analytics::excludeUsing(fn () => true);

        $this->assertSame('', trim(Blade::render('@analyticsCollector')));
    }

    /**
     * The promise the catch makes, and nothing proved it until now.
     *
     * This runs on every public page of the host, so a failure here has to cost
     * the measurement and nothing else. The closure below is the host's own —
     * the one place where someone else's code runs inside this call — and it is
     * the honest way to make the thing throw.
     */
    public function test_it_leaves_the_page_whole_when_something_throws(): void
    {
        config(['analytics.enabled' => true]);
        Analytics::excludeUsing(fn () => throw new RuntimeException('le contexte ne se lit pas'));

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('warning')->once();

        $this->assertSame('', trim(Blade::render('@analyticsCollector')));
    }

    /**
     * Suivi coupé · le fichier n'est même pas téléchargé.
     *
     * C'était l'autre moitié de l'ancien modèle · le collecteur, importé par
     * l'hôte, était toujours chargé, et c'est son premier test qui l'arrêtait.
     * Maintenant qu'il vient du paquet, ne rien déclarer suffit · la page ne
     * demande pas le fichier, et la garde du collecteur ne sert plus que de
     * seconde ligne.
     */
    public function test_it_asks_for_nothing_at_all_when_tracking_is_off(): void
    {
        config(['analytics.enabled' => false]);

        $html = Blade::render('@analyticsCollector');

        $this->assertStringNotContainsString('__falconAnalytics', $html);
        $this->assertStringNotContainsString('analytics.js', $html);
    }
}
