<?php

use Falcon\Analytics\Support\PageUrl;
use Illuminate\Support\Facades\Route;

it('uses the real url path, dropping the domain and query', function () {
    expect(PageUrl::resolve('listing.detail', 'https://vantadrive.test/listings/23?utm_source=x'))->toBe('/listings/23')
        ->and(PageUrl::resolve('listing.detail', 'https://vantadrive.test/listings/ma-super-annonce'))->toBe('/listings/ma-super-annonce');
});

it('falls back to the route uri pattern when no url is stored', function () {
    // The 'catalog' route is registered by the package test case.
    expect(PageUrl::resolve('catalog', null))->toBe('/catalog');
});

it('cleans {param} placeholders in the route pattern with an ellipsis', function () {
    Route::get('/listings/{listing}', fn () => '')->name('page-url.listing.test');
    Route::getRoutes()->refreshNameLookups();

    expect(PageUrl::resolve('page-url.listing.test', null))->toBe('/listings/…');
});

it('falls back to the raw route name for an unknown route', function () {
    expect(PageUrl::resolve('ghost', null))->toBe('ghost');
});

it('returns an empty string when nothing is provided', function () {
    expect(PageUrl::resolve(null, null))->toBe('');
});
