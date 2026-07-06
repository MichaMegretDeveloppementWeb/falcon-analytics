<?php

use Falcon\Analytics\Services\AdTagResolver;

beforeEach(function () {
    $this->resolver = new AdTagResolver;
});

it('extracts the campaign and ad from the configured params', function () {
    $tag = $this->resolver->resolve('https://vantadrive.ch/voitures?campaign=ete&ad=cabrio');

    expect($tag->campaign)->toBe('ete')
        ->and($tag->ad)->toBe('cabrio');
});

it('returns null when the marketing params are absent', function () {
    $tag = $this->resolver->resolve('https://vantadrive.ch/?utm_source=meta');

    expect($tag->campaign)->toBeNull()
        ->and($tag->ad)->toBeNull();
});

it('returns null for a null landing url', function () {
    $tag = $this->resolver->resolve(null);

    expect($tag->campaign)->toBeNull()
        ->and($tag->ad)->toBeNull();
});

it('honours custom param names from config', function () {
    config(['analytics.marketing.params' => ['campaign' => 'fbc', 'ad' => 'fba']]);

    $tag = $this->resolver->resolve('https://vantadrive.ch/?fbc=hiver&fba=suv');

    expect($tag->campaign)->toBe('hiver')
        ->and($tag->ad)->toBe('suv');
});

it('trims and caps overly long values', function () {
    $tag = $this->resolver->resolve('https://vantadrive.ch/?campaign='.str_repeat('x', 200));

    expect(mb_strlen((string) $tag->campaign))->toBe(150);
});
