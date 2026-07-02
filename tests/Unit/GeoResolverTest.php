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
