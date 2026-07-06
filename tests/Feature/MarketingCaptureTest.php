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

it('captures the marketing tags from the entry URL through the full pipeline', function (string $url, ?string $campaign, ?string $ad) {
    ingestLanding($url);

    $session = Session::query()->latest('id')->firstOrFail();

    expect($session->mkt_campaign)->toBe($campaign)
        ->and($session->mkt_ad)->toBe($ad);
})->with([
    'both params' => ['https://vantadrive.ch/?campaign=ete&ad=cabrio', 'ete', 'cabrio'],
    'campaign only' => ['https://vantadrive.ch/?campaign=ete', 'ete', null],
    'ad only' => ['https://vantadrive.ch/?ad=cabrio', null, 'cabrio'],
    'no marketing params' => ['https://vantadrive.ch/?utm_source=x', null, null],
    'plain url' => ['https://vantadrive.ch/voitures', null, null],
    'deep path keeps the params' => ['https://vantadrive.ch/voitures/cabriolet?campaign=ete&ad=cabrio', 'ete', 'cabrio'],
    'extra unrelated params ignored' => ['https://vantadrive.ch/?foo=bar&campaign=ete&ad=cabrio&baz=1', 'ete', 'cabrio'],
    'coexists with utm' => ['https://vantadrive.ch/?utm_source=meta&utm_medium=cpc&campaign=ete&ad=cabrio', 'ete', 'cabrio'],
    'accented value (percent-encoded)' => ['https://vantadrive.ch/?campaign=%C3%A9te-2026&ad=cabrio', 'éte-2026', 'cabrio'],
    'value with a space (plus-encoded)' => ['https://vantadrive.ch/?campaign=ete+2026&ad=cabrio', 'ete 2026', 'cabrio'],
    'empty campaign value is null' => ['https://vantadrive.ch/?campaign=&ad=cabrio', null, 'cabrio'],
    'repeated param takes the last (parse_str)' => ['https://vantadrive.ch/?campaign=a&campaign=b', 'b', null],
    'param names are case-sensitive' => ['https://vantadrive.ch/?Campaign=ete&AD=cabrio', null, null],
]);

it('caps an overly long value at 150 characters', function () {
    ingestLanding('https://vantadrive.ch/?campaign='.str_repeat('x', 200).'&ad=cabrio');

    $session = Session::query()->latest('id')->firstOrFail();

    expect(mb_strlen((string) $session->mkt_campaign))->toBe(150)
        ->and($session->mkt_ad)->toBe('cabrio');
});

it('honours custom parameter names from config', function () {
    config(['analytics.marketing.params' => ['campaign' => 'fb_campaign', 'ad' => 'fb_ad']]);

    ingestLanding('https://vantadrive.ch/?fb_campaign=hiver&fb_ad=suv&campaign=ignored');

    $session = Session::query()->latest('id')->firstOrFail();

    expect($session->mkt_campaign)->toBe('hiver')
        ->and($session->mkt_ad)->toBe('suv');
});

it('does not confuse two different ads arriving on separate sessions', function () {
    ingestLanding('https://vantadrive.ch/?campaign=ete&ad=cabrio');
    $this->flushSession(); // new visitor/session for the second landing

    ingestLanding('https://vantadrive.ch/?campaign=ete&ad=suv');

    $sessions = Session::query()->orderBy('id')->get();

    expect($sessions)->toHaveCount(2)
        ->and($sessions[0]->mkt_ad)->toBe('cabrio')
        ->and($sessions[1]->mkt_ad)->toBe('suv')
        ->and($sessions[0]->mkt_campaign)->toBe('ete')
        ->and($sessions[1]->mkt_campaign)->toBe('ete');
});
