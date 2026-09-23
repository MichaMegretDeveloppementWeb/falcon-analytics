<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * No instant of the package sits in a type the engine converts.
 *
 * It reads the engine's own catalogue, the schema a restored backup, a replica
 * or a reporting tool reads · the migrations would only agree with themselves.
 * It is scoped by the table prefix, so a new table is covered without being
 * listed here.
 */
final class NoInstantSitsInAConvertedTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_instant_column_of_the_package_is_of_a_type_the_engine_leaves_alone(): void
    {
        $converted = $this->instantColumns()
            ->filter(fn (string $type): bool => $type === 'timestamp')
            ->keys()
            ->all();

        $this->assertSame(
            [],
            $converted,
            'Ces colonnes sont dans un type que le moteur convertit à l\'écriture et à la lecture : le disque y porte autre chose que ce que l\'application écrit.',
        );
    }

    /**
     * An empty answer above then means that none is converted, not that the
     * query matched nothing.
     */
    public function test_the_probe_sees_the_columns_it_is_meant_to_judge(): void
    {
        $seen = $this->instantColumns()->keys()->all();

        $this->assertContains('falcon_analytics_sessions.started_at', $seen);
        $this->assertContains('falcon_analytics_events.occurred_at', $seen);
        $this->assertCount(17, $seen, 'Le paquet porte dix-sept instants, sur huit tables.');
    }

    /**
     * Every instant of the package, keyed by `table.column`, valued by the type
     * the engine holds it in.
     *
     * @return Collection<array-key, string>
     */
    private function instantColumns(): Collection
    {
        return DB::table('information_schema.COLUMNS')
            ->selectRaw("CONCAT(TABLE_NAME, '.', COLUMN_NAME) AS name, DATA_TYPE AS type")
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'like', 'falcon\_analytics\_%')
            ->whereIn('DATA_TYPE', ['timestamp', 'datetime'])
            ->orderBy('TABLE_NAME')
            ->orderBy('ORDINAL_POSITION')
            ->pluck('type', 'name')
            ->map(fn (mixed $type): string => (string) $type);
    }
}
