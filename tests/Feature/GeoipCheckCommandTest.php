<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Enums\GeoStatus;
use Falcon\Analytics\Tests\TestCase;

/**
 * La geolocalisation se degrade en localisation vide quoi qu'il arrive · pas de
 * base, une base tronquee, une adresse privee. Les trois montraient la meme
 * colonne blanche, et le seul moyen de les distinguer etait de lire la source.
 * Cette commande dit laquelle.
 */
final class GeoipCheckCommandTest extends TestCase
{
    public function test_it_reports_a_missing_database_instead_of_a_blank_result(): void
    {
        config(['analytics.geoip.database_path' => '/does/not/exist.mmdb']);

        $this->artisan('analytics:geoip:check')
            ->expectsOutputToContain('no GeoIP database')
            ->assertFailed();
    }

    public function test_it_reports_a_file_the_reader_refuses_apart_from_a_missing_one(): void
    {
        $path = storage_path('app/analytics/not-a-database-'.uniqid().'.mmdb');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0o755, true);
        }

        file_put_contents($path, 'NOT AN MMDB');
        config(['analytics.geoip.database_path' => $path]);

        $this->artisan('analytics:geoip:check')
            ->expectsOutputToContain('unreadable')
            ->assertFailed();

        @unlink($path);
    }

    /** Chaque etat porte de quoi le lire, et seuls ceux qui appellent une action portent un conseil. */
    public function test_it_says_what_to_do_about_the_states_that_can_be_fixed(): void
    {
        $this->assertNotNull(GeoStatus::NoDatabase->hint());
        $this->assertNotNull(GeoStatus::UnreadableDatabase->hint());
        $this->assertNotNull(GeoStatus::PrivateAddress->hint());
        $this->assertNull(GeoStatus::Ready->hint());
        $this->assertNull(GeoStatus::NotInDatabase->hint());

        foreach (GeoStatus::cases() as $case) {
            $this->assertNotSame('', $case->label());
        }
    }
}
