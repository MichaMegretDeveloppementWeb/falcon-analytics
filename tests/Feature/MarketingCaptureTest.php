<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;

final class MarketingCaptureTest extends TestCase
{
    use RefreshDatabase;

    private function ingestLanding(string $url): void
    {
        $this->withoutDefer()
            ->withHeader('Origin', config('app.url'))
            ->postJson('/__analytics', [
                'sent_at' => 1000,
                'events' => [['type' => 'pageview', 'ts' => 1000, 'route' => 'home', 'url' => $url]],
            ])
            ->assertNoContent();
    }

    /**
     * @return array<string, array{string, array<string, string>}>
     */
    public static function landingUrls(): array
    {
        return [
            'marketing params' => ['https://vantadrive.ch/?src=meta_ete&creative=cabrio', ['src' => 'meta_ete', 'creative' => 'cabrio']],
            'no params' => ['https://vantadrive.ch/voitures', []],
            'deep path keeps params' => ['https://vantadrive.ch/voitures/cabriolet?src=meta_ete', ['src' => 'meta_ete']],
            'coexists with utm' => ['https://vantadrive.ch/?utm_source=meta&src=meta_ete', ['utm_source' => 'meta', 'src' => 'meta_ete']],
            'accented value (percent-encoded)' => ['https://vantadrive.ch/?campaign=%C3%A9te-2026', ['campaign' => 'éte-2026']],
            'value with a space (plus-encoded)' => ['https://vantadrive.ch/?campaign=ete+2026', ['campaign' => 'ete 2026']],
            'empty values dropped' => ['https://vantadrive.ch/?a=&b=x', ['b' => 'x']],
            'parameter names are case-sensitive' => ['https://vantadrive.ch/?Src=Meta&AD=Cabrio', ['Src' => 'Meta', 'AD' => 'Cabrio']],
        ];
    }

    /**
     * L'ordre des cles d'un objet JSON ne porte aucun sens, et MySQL le change.
     *
     * Il normalise a l'ecriture, triant par longueur de cle puis
     * alphabetiquement · `?utm_source=meta&src=meta_ete` ressort `src` avant
     * `utm_source`. SQLite gardait le texte tel quel, et l'assertion s'appuyait
     * sans le savoir sur cette particularite d'un moteur qui n'est pas celui de
     * production.
     *
     * On trie les deux cotes plutot que de canonicaliser ·
     * `assertEqualsCanonicalizing` passe par `sort()`, qui jette les cles, et
     * un essai qui ne compare plus que les valeurs accepterait `src` a la place
     * de `creative`.
     *
     * @param  array<string, string>  $expected
     */
    #[DataProvider('landingUrls')]
    public function test_it_captures_the_landing_url_parameters_through_the_full_pipeline(string $url, array $expected): void
    {
        $this->ingestLanding($url);

        $captured = Session::query()->latest('id')->firstOrFail()->mkt_params ?? [];

        ksort($captured);
        ksort($expected);

        $this->assertSame($expected, $captured);
    }

    public function test_it_caps_an_overly_long_value_at_150_characters(): void
    {
        $this->ingestLanding('https://vantadrive.ch/?campaign='.str_repeat('x', 200).'&ad=cabrio');

        $params = Session::query()->latest('id')->firstOrFail()->mkt_params ?? [];

        $this->assertSame(150, mb_strlen($params['campaign']));
        $this->assertSame('cabrio', $params['ad']);
    }

    public function test_it_keeps_distinct_params_on_separate_sessions(): void
    {
        $this->ingestLanding('https://vantadrive.ch/?src=meta_ete&creative=cabrio');
        $this->flushSession();

        $this->ingestLanding('https://vantadrive.ch/?src=meta_ete&creative=suv');

        $sessions = Session::query()->orderBy('id')->get();

        $this->assertCount(2, $sessions);
        $this->assertSame(['src' => 'meta_ete', 'creative' => 'cabrio'], $sessions[0]->mkt_params);
        $this->assertSame(['src' => 'meta_ete', 'creative' => 'suv'], $sessions[1]->mkt_params);
    }
}
