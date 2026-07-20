<?php

use Falcon\Analytics\Support\GeoResolver;

it('returns an empty location when no database is configured', function () {
    $location = (new GeoResolver(null))->locate('85.4.12.66');

    expect($location->country)->toBeNull()
        ->and($location->city)->toBeNull()
        ->and($location->latitude)->toBeNull();
});

it('returns an empty location when the database file is missing', function () {
    $location = (new GeoResolver('/does/not/exist.mmdb'))->locate('85.4.12.66');

    expect($location->country)->toBeNull()
        ->and($location->city)->toBeNull();
});

it('returns an empty location for a missing ip', function () {
    $resolver = new GeoResolver('/does/not/exist.mmdb');

    expect($resolver->locate(null)->country)->toBeNull()
        ->and($resolver->locate('')->country)->toBeNull();
});

it('substitutes the development ip for private and reserved ips only', function () {
    $resolver = new GeoResolver(null, '85.4.12.66');

    expect($resolver->effectiveIp('127.0.0.1'))->toBe('85.4.12.66')
        ->and($resolver->effectiveIp('192.168.1.20'))->toBe('85.4.12.66')
        ->and($resolver->effectiveIp('10.0.0.5'))->toBe('85.4.12.66')
        ->and($resolver->effectiveIp('169.254.7.8'))->toBe('85.4.12.66') // reserved (link-local)
        ->and($resolver->effectiveIp('84.253.10.20'))->toBe('84.253.10.20'); // real public ip: untouched
});

it('leaves every ip untouched without a development ip', function () {
    $resolver = new GeoResolver(null);

    expect($resolver->effectiveIp('127.0.0.1'))->toBe('127.0.0.1')
        ->and($resolver->effectiveIp(null))->toBeNull();
});
