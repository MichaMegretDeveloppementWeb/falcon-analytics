<?php

use Falcon\Analytics\Enums\GeoStatus;

/*
| Geolocation degrades to an empty location whatever goes wrong: no database, a truncated one, a
| private address. All three showed the same blank column, and the only way to tell them apart was
| to read the source. This command says which.
*/

it('reports a missing database instead of a blank result', function () {
    config(['analytics.geoip.database_path' => '/does/not/exist.mmdb']);

    $this->artisan('analytics:geoip:check')
        ->expectsOutputToContain('no GeoIP database')
        ->assertFailed();
});

it('reports a file the reader refuses apart from a missing one', function () {
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
});

/* Chaque état porte de quoi le lire, et seuls ceux qui appellent une action portent un conseil. */
it('says what to do about the states that can be fixed', function () {
    expect(GeoStatus::NoDatabase->hint())->not->toBeNull()
        ->and(GeoStatus::UnreadableDatabase->hint())->not->toBeNull()
        ->and(GeoStatus::PrivateAddress->hint())->not->toBeNull()
        ->and(GeoStatus::Ready->hint())->toBeNull()
        ->and(GeoStatus::NotInDatabase->hint())->toBeNull();

    foreach (GeoStatus::cases() as $case) {
        expect($case->label())->not->toBe('');
    }
});
