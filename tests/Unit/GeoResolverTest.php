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
        $location = (new GeoResolver(null))->locate('85.4.12.66');

        $this->assertNull($location->country);
        $this->assertNull($location->city);
        $this->assertNull($location->latitude);
    }

    public function test_it_returns_an_empty_location_when_the_database_file_is_missing(): void
    {
        $location = (new GeoResolver('/does/not/exist.mmdb'))->locate('85.4.12.66');

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
        $resolver = new GeoResolver(null, '85.4.12.66');

        $this->assertSame('85.4.12.66', $resolver->effectiveIp('127.0.0.1'));
        $this->assertSame('85.4.12.66', $resolver->effectiveIp('192.168.1.20'));
        $this->assertSame('85.4.12.66', $resolver->effectiveIp('10.0.0.5'));
        $this->assertSame('85.4.12.66', $resolver->effectiveIp('169.254.7.8'), 'réservée, lien local');
        $this->assertSame('84.253.10.20', $resolver->effectiveIp('84.253.10.20'), 'publique réelle : intacte');
    }

    public function test_it_leaves_every_ip_untouched_without_a_development_ip(): void
    {
        $resolver = new GeoResolver(null);

        $this->assertSame('127.0.0.1', $resolver->effectiveIp('127.0.0.1'));
        $this->assertNull($resolver->effectiveIp(null));
    }

    /**
     * `locate()` se dégrade en localisation vide quelle qu'en soit la cause, ce
     * qui est juste pour une requête et inutile pour qui lit l'écran : une base
     * absente et un 127.0.0.1 montraient tous deux une colonne blanche, et rien
     * ne disait laquelle corriger.
     */
    public function test_it_names_the_reason_an_address_does_not_resolve(): void
    {
        $this->assertSame(GeoStatus::NoDatabase, (new GeoResolver(null))->status('85.4.12.66'));
        $this->assertSame(GeoStatus::NoDatabase, (new GeoResolver('/does/not/exist.mmdb'))->status('85.4.12.66'));
    }

    public function test_it_reports_an_unreadable_database_apart_from_a_missing_one(): void
    {
        $path = sys_get_temp_dir().'/not-a-database-'.uniqid().'.mmdb';
        file_put_contents($path, 'NOT AN MMDB');

        $this->assertSame(GeoStatus::UnreadableDatabase, (new GeoResolver($path))->status('85.4.12.66'));

        @unlink($path);
    }

    /** Une adresse de développement ne remplace que les privées, donc elle ne peut pas masquer une vraie panne. */
    public function test_it_keeps_a_public_address_out_of_the_development_substitution_when_checking(): void
    {
        $resolver = new GeoResolver(null, '85.4.12.66');

        $this->assertSame('9.9.9.9', $resolver->effectiveIp('9.9.9.9'));
        $this->assertSame(GeoStatus::NoDatabase, $resolver->status('9.9.9.9'));
    }
}
