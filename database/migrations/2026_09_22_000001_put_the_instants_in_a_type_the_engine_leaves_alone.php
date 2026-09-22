<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The eighteen instants leave the type the engine converts.
 *
 * A converted type is read and written against the session time zone. The
 * package writes UTC in plain, so the engine reads that as a local time and
 * stores something else; the reverse conversion on the way back hides it, which
 * is why the screens have always been right and the disk has always been wrong.
 *
 * Three things follow from that, and the third is the one that bites: any other
 * reader sees another instant; two hosts store the same instant differently;
 * and an hour a year — the one the local clock skips — is refused outright,
 * with nothing recorded at all.
 *
 * The change of type IS the conversion. The engine applies, row by row, the
 * offset it had applied itself, which no single UPDATE could do: it is two
 * hours in summer and one in winter.
 */
return new class extends Migration
{
    /**
     * Every instant of the package, by table, with the nullability the schema
     * builder has to be told again.
     *
     * A change replaces the whole column definition: a forgotten `nullable()`
     * turns a column NOT NULL, and the statement then fails on the first null
     * value — or worse, meets none that day.
     *
     * @var array<string, array<string, bool>>
     */
    private const INSTANTS = [
        'falcon_analytics_visitors' => ['first_seen_at' => false, 'last_seen_at' => false],
        'falcon_analytics_sessions' => ['started_at' => false, 'last_activity_at' => false, 'ended_at' => true],
        'falcon_analytics_events' => ['occurred_at' => false],
        'falcon_analytics_campaigns' => ['created_at' => true, 'updated_at' => true],
        'falcon_analytics_ads' => ['created_at' => true, 'updated_at' => true],
        'falcon_analytics_ad_objectives' => ['created_at' => true, 'updated_at' => true],
        'falcon_analytics_search_console' => ['token_expires_at' => true, 'last_synced_at' => true, 'created_at' => true, 'updated_at' => true],
        'falcon_analytics_daily_archives' => ['archived_at' => false, 'pruned_at' => true],
    ];

    public function up(): void
    {
        $this->retype('dateTime');
    }

    /**
     * Symmetric: going back puts the defect back, which is what going back
     * means. No data is destroyed, and the same guard watches the conversion.
     */
    public function down(): void
    {
        $this->retype('timestamp');
    }

    /**
     * @param  'dateTime'|'timestamp'  $type
     */
    private function retype(string $type): void
    {
        foreach (self::INSTANTS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $before = $this->boundsOf($table, array_key_first($columns));

            Schema::table($table, function (Blueprint $blueprint) use ($columns, $type): void {
                foreach ($columns as $column => $nullable) {
                    $definition = $type === 'dateTime'
                        ? $blueprint->dateTime($column)
                        : $blueprint->timestamp($column);

                    if ($nullable) {
                        $definition->nullable();
                    }

                    $definition->change();
                }
            });

            $this->refuseAShiftedTable($table, array_key_first($columns), $before);
        }
    }

    /**
     * The oldest and the newest value of a column, as the application reads
     * them. An empty table has none, and there is then nothing to compare.
     *
     * Through the builder's own aggregates rather than a raw expression: the
     * column name is wrapped by the grammar instead of being spliced into SQL.
     *
     * @return array{min: string|null, max: string|null}
     */
    private function boundsOf(string $table, string $column): array
    {
        $query = DB::table($table);

        $min = $query->clone()->min($column);
        $max = $query->clone()->max($column);

        return [
            'min' => is_scalar($min) ? (string) $min : null,
            'max' => is_scalar($max) ? (string) $max : null,
        ];
    }

    /**
     * A definition statement commits its own transaction, so a conversion run
     * under the wrong session time zone cannot be undone. It can be made loud,
     * and stopped before the next table: silence here would move a whole
     * history by an hour or two with nothing to show for it.
     *
     * @param  array{min: string|null, max: string|null}  $before
     */
    private function refuseAShiftedTable(string $table, string $column, array $before): void
    {
        if ($before['min'] === null) {
            return;
        }

        $after = $this->boundsOf($table, $column);

        if ($after === $before) {
            return;
        }

        throw new RuntimeException(sprintf(
            'La conversion de %s.%s a déplacé les instants : %s..%s est devenu %s..%s. '
            .'Le fuseau de la session n\'est pas celui que l\'application emploie pour lire. '
            .'Les tables suivantes n\'ont pas été touchées ; restaurez la sauvegarde avant de recommencer.',
            $table,
            $column,
            $before['min'],
            $before['max'] ?? '?',
            $after['min'] ?? '?',
            $after['max'] ?? '?',
        ));
    }
};
