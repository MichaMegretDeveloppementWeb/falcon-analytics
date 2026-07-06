<?php

use Falcon\Analytics\Services\LandingParamsResolver;

beforeEach(function () {
    $this->resolver = new LandingParamsResolver;
});

it('extracts the landing url query parameters', function () {
    expect($this->resolver->resolve('https://vantadrive.ch/voitures?src=meta_ete&creative=cabrio'))
        ->toBe(['src' => 'meta_ete', 'creative' => 'cabrio']);
});

it('returns an empty array without a query string or url', function () {
    expect($this->resolver->resolve('https://vantadrive.ch/voitures'))->toBe([])
        ->and($this->resolver->resolve(null))->toBe([]);
});

it('drops empty values and array-style params', function () {
    expect($this->resolver->resolve('https://vantadrive.ch/?a=1&b=&c[]=x&d=2'))
        ->toBe(['a' => '1', 'd' => '2']);
});

it('caps overly long values', function () {
    $params = $this->resolver->resolve('https://vantadrive.ch/?campaign='.str_repeat('x', 200));

    expect(mb_strlen($params['campaign']))->toBe(150);
});

it('caps the number of parameters', function () {
    $query = collect(range(1, 40))->map(fn (int $i): string => "p{$i}=v{$i}")->implode('&');

    expect($this->resolver->resolve("https://vantadrive.ch/?{$query}"))->toHaveCount(30);
});
