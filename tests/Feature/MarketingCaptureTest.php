<?php

use Falcon\Analytics\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function ingestLanding(string $url): void
{
    test()->withoutDefer()
        ->withHeader('Origin', config('app.url'))
        ->postJson('/__analytics', [
            'sent_at' => 1000,
            'events' => [['type' => 'pageview', 'ts' => 1000, 'route' => 'home', 'url' => $url]],
        ])
        ->assertNoContent();
}

/**
 * L'ordre des cles d'un objet JSON ne porte aucun sens, et MySQL le change.
 *
 * Il normalise a l'ecriture, triant par longueur de cle puis alphabetiquement ·
 * `?utm_source=meta&src=meta_ete` ressort `src` avant `utm_source`. SQLite
 * gardait le texte tel quel, et l'assertion s'appuyait sans le savoir sur cette
 * particularite d'un moteur qui n'est pas celui de production.
 *
 * On trie les deux cotes plutot que de canonicaliser · `assertEqualsCanonicalizing`
 * passe par `sort()`, qui jette les cles, et un essai qui ne compare plus que
 * les valeurs accepterait `src` a la place de `creative`.
 */
it('captures the landing url parameters through the full pipeline', function (string $url, array $expected) {
    ingestLanding($url);

    $session = Session::query()->latest('id')->firstOrFail();

    $captured = $session->mkt_params ?? [];

    ksort($captured);
    ksort($expected);

    expect($captured)->toBe($expected);
})->with([
    'marketing params' => ['https://vantadrive.ch/?src=meta_ete&creative=cabrio', ['src' => 'meta_ete', 'creative' => 'cabrio']],
    'no params' => ['https://vantadrive.ch/voitures', []],
    'deep path keeps params' => ['https://vantadrive.ch/voitures/cabriolet?src=meta_ete', ['src' => 'meta_ete']],
    'coexists with utm' => ['https://vantadrive.ch/?utm_source=meta&src=meta_ete', ['utm_source' => 'meta', 'src' => 'meta_ete']],
    'accented value (percent-encoded)' => ['https://vantadrive.ch/?campaign=%C3%A9te-2026', ['campaign' => 'éte-2026']],
    'value with a space (plus-encoded)' => ['https://vantadrive.ch/?campaign=ete+2026', ['campaign' => 'ete 2026']],
    'empty values dropped' => ['https://vantadrive.ch/?a=&b=x', ['b' => 'x']],
    'parameter names are case-sensitive' => ['https://vantadrive.ch/?Src=Meta&AD=Cabrio', ['Src' => 'Meta', 'AD' => 'Cabrio']],
]);

it('caps an overly long value at 150 characters', function () {
    ingestLanding('https://vantadrive.ch/?campaign='.str_repeat('x', 200).'&ad=cabrio');

    $session = Session::query()->latest('id')->firstOrFail();

    expect(mb_strlen(($session->mkt_params ?? [])['campaign']))->toBe(150)
        ->and(($session->mkt_params ?? [])['ad'])->toBe('cabrio');
});

it('keeps distinct params on separate sessions', function () {
    ingestLanding('https://vantadrive.ch/?src=meta_ete&creative=cabrio');
    $this->flushSession();

    ingestLanding('https://vantadrive.ch/?src=meta_ete&creative=suv');

    $sessions = Session::query()->orderBy('id')->get();

    expect($sessions)->toHaveCount(2)
        ->and($sessions[0]->mkt_params)->toBe(['src' => 'meta_ete', 'creative' => 'cabrio'])
        ->and($sessions[1]->mkt_params)->toBe(['src' => 'meta_ete', 'creative' => 'suv']);
});
