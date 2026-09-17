<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\DailyCount;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionMethod;

/**
 * La migration qui pose la page remplit ce qui était déjà là.
 *
 * **La suite migre une base vide**, donc les deux remplissages de cette
 * migration — la page des lignes existantes, et les résumés écrits sur
 * l'adresse entière repliés sur la page — ne tournent jamais sur une seule
 * ligne pendant les essais ordinaires. C'est ici qu'ils tournent, sur des
 * lignes posées à la main dans la forme d'avant.
 *
 * **Les deux comptent** · une installation qui a mesuré avant garde sa
 * profondeur, et un résumé groupé autrement que la lecture qu'il remplace
 * ferait sauter le bloc le jour où l'effacement le traverse.
 */
final class TheUpgradeWritesThePageOfWhatWasThereTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = __DIR__.'/../../database/migrations/2026_09_14_000001_add_page_to_falcon_analytics_events_table.php';

    /**
     * The migration is an anonymous class, and the base class declares neither
     * `up()` nor `down()` · the direction is called by name, which is also what
     * the migrator does.
     */
    private function migrate(string $direction): void
    {
        $migration = require self::MIGRATION;

        $this->assertInstanceOf(Migration::class, $migration);

        (new ReflectionMethod($migration, $direction))->invoke($migration);
    }

    private function newSession(): Session
    {
        $visitor = Visitor::create(['uuid' => (string) Str::uuid(), 'first_seen_at' => now(), 'last_seen_at' => now()]);

        return Session::create(['visitor_id' => $visitor->id, 'started_at' => now(), 'last_activity_at' => now(), 'is_bot' => false]);
    }

    /**
     * A row in the shape of before · an address, and no page. Written past the
     * model so that its hook does not fill what the migration is meant to.
     */
    private function oldRow(Session $session, string $url): int
    {
        return DB::table(Event::TABLE)->insertGetId([
            'session_id' => $session->id,
            'visitor_id' => $session->visitor_id,
            'type' => EventType::Pageview->value,
            'url' => $url,
            'page' => null,
            'occurred_at' => CarbonImmutable::parse('2026-06-10 09:00:00')->toDateTimeString(),
        ]);
    }

    /** @param  array<string, mixed>  $columns */
    private function oldCount(array $columns): void
    {
        DB::table(DailyCount::TABLE)->insert([
            'day' => '2026-06-10',
            'kind' => DailyCount::KIND_PAGE,
            'signature' => hash('sha256', (string) json_encode(['page', $columns['label'], null, $columns['subject_type'] ?? null])),
            'route' => null,
            'subject_type' => null,
            ...$columns,
        ]);
    }

    /**
     * Run the migration again over seeded rows, and put everything back.
     *
     * **Two DDL statements, and a `finally`.** Dropping and adding a column
     * commits the transaction `RefreshDatabase` holds open, so the rows seeded
     * here would survive into every later test of this process ; the cleanup
     * is what keeps them from doing so.
     */
    private function upgradeAgain(callable $assertions): void
    {
        try {
            $this->migrate('down');
            $this->migrate('up');

            $assertions();
        } finally {
            DB::table(Event::TABLE)->delete();
            DB::table(DailyCount::TABLE)->delete();
            DB::table(Session::TABLE)->delete();
            DB::table((new Visitor)->getTable())->delete();
        }
    }

    public function test_it_writes_the_page_of_every_row_that_had_an_address(): void
    {
        $session = $this->newSession();

        $bare = $this->oldRow($session, 'https://www.exemple.fr/tarifs');
        $campaign = $this->oldRow($session, 'https://www.exemple.fr/tarifs?fbclid=IwAR0aaa#prix');
        $root = $this->oldRow($session, 'https://exemple.fr/');

        $this->upgradeAgain(function () use ($bare, $campaign, $root): void {
            $pages = DB::table(Event::TABLE)->whereIn('id', [$bare, $campaign, $root])->orderBy('id')->pluck('page', 'id');

            $this->assertSame(['/tarifs', '/tarifs', '/'], array_values($pages->all()));
        });
    }

    /**
     * Summaries written on the whole address fold onto the page, totals added.
     *
     * Three rows of one day for what is one page · the bare address, a campaign
     * link, and the same site under another host. And a row that already names
     * a page, which must come out untouched.
     */
    public function test_it_folds_the_summaries_written_on_the_address_onto_the_page(): void
    {
        $this->oldCount(['label' => 'https://www.exemple.fr/tarifs', 'total' => 3]);
        $this->oldCount(['label' => 'https://www.exemple.fr/tarifs?fbclid=IwAR0aaa', 'total' => 2]);
        $this->oldCount(['label' => 'https://exemple.fr/tarifs', 'total' => 1]);
        $this->oldCount(['label' => '/contact', 'total' => 4]);

        // The same page, seen by a signed-in subject · a different row, and it
        // must stay one.
        $this->oldCount(['label' => 'https://www.exemple.fr/tarifs', 'subject_type' => 'client', 'total' => 5]);

        $this->upgradeAgain(function (): void {
            $rows = DB::table(DailyCount::TABLE)
                ->where('day', '2026-06-10')
                ->orderBy('label')
                ->orderBy('subject_type')
                ->get(['label', 'subject_type', 'total', 'signature'])
                ->map(fn (object $row): array => [
                    'label' => (string) $row->label,
                    'subject_type' => $row->subject_type !== null ? (string) $row->subject_type : null,
                    'total' => (int) $row->total,
                ])
                ->all();

            $this->assertSame([
                ['label' => '/contact', 'subject_type' => null, 'total' => 4],
                ['label' => '/tarifs', 'subject_type' => null, 'total' => 6],
                ['label' => '/tarifs', 'subject_type' => 'client', 'total' => 5],
            ], $rows);

            // And the signature is the one the model would write, or the next
            // archiving of that day could not replace these rows cleanly.
            $this->assertTrue(
                DB::table(DailyCount::TABLE)
                    ->where('day', '2026-06-10')
                    ->where('signature', DailyCount::signature(DailyCount::KIND_PAGE, '/tarifs', null, null))
                    ->exists(),
                'The folded row must carry the signature the model computes.',
            );
        });
    }
}
