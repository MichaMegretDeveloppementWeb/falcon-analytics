<?php

use Falcon\Analytics\Support\PageUrl;

it('uses the real url path, dropping the domain and query', function () {
    expect(PageUrl::resolve('listing.detail', 'https://vantadrive.test/listings/23?utm_source=x'))->toBe('/listings/23')
        ->and(PageUrl::resolve('listing.detail', 'https://vantadrive.test/listings/ma-super-annonce'))->toBe('/listings/ma-super-annonce');
});

it('falls back to the route uri pattern when no url is stored', function () {
    // The 'catalog' route is registered by the package test case.
    expect(PageUrl::resolve('catalog', null))->toBe('/catalog');
});

it('falls back to the raw route name for an unknown route', function () {
    expect(PageUrl::resolve('ghost', null))->toBe('ghost');
});

it('returns an empty string when nothing is provided', function () {
    expect(PageUrl::resolve(null, null))->toBe('');
});
