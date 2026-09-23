<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Unit;

use Falcon\Analytics\Enums\GeoStatus;
use Falcon\Analytics\Support\GeoResolver;
use PHPUnit\Framework\TestCase;

final class GeoResolverTest extends TestCase
{
    public function test_it_returns_an_empty_location_when_no_database_is_configured(): void
    {
        $location = (new GeoResolver(null))->locate('203.0.113.66');

        $this->assertNull($location->country);
        $this->assertNull($location->city);
        $this->assertNull($location->latitude);
    }

    public function test_it_returns_an_empty_location_when_the_database_file_is_missing(): void
    {
        $location = (new GeoResolver('/does/not/exist.mmdb'))->locate('203.0.113.66');

        $this->assertNull($location->country);
        $this->assertNull($location->city);
    }

    public function test_it_returns_an_empty_location_for_a_missing_ip(): void
    {
        $resolver = new GeoResolver('/does/not/exist.mmdb');

        $this->assertNull($resolver->locate(null)->country);
        $this->assertNull($resolver->locate('')->country);
    }

    public function test_it_substitutes_the_development_ip_for_private_and_reserved_ips_only(): void
    {
        $resolver = new GeoResolver(null, '203.0.113.66');

        $this->assertSame('203.0.113.66', $resolver->effectiveIp('127.0.0.1'));
        $this->assertSame('203.0.113.66', $resolver->effectiveIp('192.168.1.20'));
        $this->assertSame('203.0.113.66', $resolver->effectiveIp('10.0.0.5'));
        $this->assertSame('203.0.113.66', $resolver->effectiveIp('169.254.7.8'), 'reserved, link-local');
        $this->assertSame('198.51.100.20', $resolver->effectiveIp('198.51.100.20'), 'a real public one: untouched');
    }

    public function test_it_leaves_every_ip_untouched_without_a_development_ip(): void
    {
        $resolver = new GeoResolver(null);

        $this->assertSame('127.0.0.1', $resolver->effectiveIp('127.0.0.1'));
        $this->assertNull($resolver->effectiveIp(null));
    }

    /**
     * `locate()` degrades to an empty location whatever the cause, which is
     * right for a request and useless for whoever reads the screen: an absent
     * database and a 127.0.0.1 both show a blank column, and nothing says
     * which one to fix.
     */
    public function test_it_names_the_reason_an_address_does_not_resolve(): void
    {
        $this->assertSame(GeoStatus::NoDatabase, (new GeoResolver(null))->status('203.0.113.66'));
        $this->assertSame(GeoStatus::NoDatabase, (new GeoResolver('/does/not/exist.mmdb'))->status('203.0.113.66'));
    }

    public function test_it_reports_an_unreadable_database_apart_from_a_missing_one(): void
    {
        $path = sys_get_temp_dir().'/not-a-database-'.uniqid().'.mmdb';
        file_put_contents($path, 'NOT AN MMDB');

        $this->assertSame(GeoStatus::UnreadableDatabase, (new GeoResolver($path))->status('203.0.113.66'));

        @unlink($path);
    }

    /** A development address only replaces private ones, so it cannot mask a real failure. */
    public function test_it_keeps_a_public_address_out_of_the_development_substitution_when_checking(): void
    {
        $resolver = new GeoResolver(null, '203.0.113.66');

        $this->assertSame('9.9.9.9', $resolver->effectiveIp('9.9.9.9'));
        $this->assertSame(GeoStatus::NoDatabase, $resolver->status('9.9.9.9'));
    }
}
