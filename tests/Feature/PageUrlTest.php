<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Support\PageUrl;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Support\Facades\Route;

final class PageUrlTest extends TestCase
{
    public function test_it_uses_the_real_url_path_dropping_the_domain_and_query(): void
    {
        $this->assertSame(
            '/listings/23',
            PageUrl::resolve('listing.detail', 'https://vantadrive.test/listings/23?utm_source=x'),
        );

        $this->assertSame(
            '/listings/ma-super-annonce',
            PageUrl::resolve('listing.detail', 'https://vantadrive.test/listings/ma-super-annonce'),
        );
    }

    public function test_it_falls_back_to_the_route_uri_pattern_when_no_url_is_stored(): void
    {
        // La route « catalog » est enregistrée par le banc du paquet.
        $this->assertSame('/catalog', PageUrl::resolve('catalog', null));
    }

    public function test_it_cleans_param_placeholders_in_the_route_pattern_with_an_ellipsis(): void
    {
        Route::get('/listings/{listing}', fn () => '')->name('page-url.listing.test');
        Route::getRoutes()->refreshNameLookups();

        $this->assertSame('/listings/…', PageUrl::resolve('page-url.listing.test', null));
    }

    public function test_it_falls_back_to_the_raw_route_name_for_an_unknown_route(): void
    {
        $this->assertSame('ghost', PageUrl::resolve('ghost', null));
    }

    public function test_it_returns_an_empty_string_when_nothing_is_provided(): void
    {
        $this->assertSame('', PageUrl::resolve(null, null));
    }
}
