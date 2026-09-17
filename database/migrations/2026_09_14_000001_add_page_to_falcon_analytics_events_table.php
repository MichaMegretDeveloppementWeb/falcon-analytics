<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The page an event belongs to · the path of its route, and nothing else.
 *
 * **The address stays whole**, as the visitor opened it — that is what a
 * session's journey shows. But « les pages les plus vues » asks which PAGE was
 * seen, and answering that from the address split one page into as many rows
 * as it had campaign links, anchor links or hosts, every row displaying the
 * same path. So the page is written down once, at ingestion, by the same
 * reading the screen makes to display an address, and every count groups on
 * it.
 *
 * **Filled from the rows already there, in batches**, so an installation that
 * measured before this keeps its depth. And the daily summaries that were
 * written on the whole address are folded onto the page, since a summary
 * grouped differently from the reading it stands in for would make the block
 * jump the day the erasing crossed it.
 */
return new class extends Migration
{
    private const BATCH = 500;

    public function up(): void
    {
        Schema::table('falcon_analytics_events', function (Blueprint $table): void {
            $table->string('page', 2048)->nullable()->after('url');
        });

        $this->fillThePageOfExistingRows();
        $this->foldTheSummariesOntoThePage();
    }

    public function down(): void
    {
        Schema::table('falcon_analytics_events', function (Blueprint $table): void {
            $table->dropColumn('page');
        });
    }

    /**
     * One bulk statement per batch rather than one per row · a year of page
     * views is hundreds of thousands of rows, and a deployment waits on this.
     */
    private function fillThePageOfExistingRows(): void
    {
        DB::table('falcon_analytics_events')
            ->select(['id', 'url'])
            ->whereNull('page')
            ->whereNotNull('url')
            ->orderBy('id')
            ->chunkById(self::BATCH, function ($rows): void {
                $cases = [];
                $bindings = [];
                $ids = [];

                foreach ($rows as $row) {
                    $page = $this->pageOf((string) $row->url);

                    if ($page === null) {
                        continue;
                    }

                    $cases[] = 'WHEN ? THEN ?';
                    $bindings[] = (int) $row->id;
                    $bindings[] = $page;
                    $ids[] = (int) $row->id;
                }

                if ($ids === []) {
                    return;
                }

                $placeholders = implode(', ', array_fill(0, count($ids), '?'));

                DB::update(
                    'UPDATE falcon_analytics_events SET page = CASE id '.implode(' ', $cases).' END '
                    ."WHERE id IN ({$placeholders})",
                    [...$bindings, ...$ids],
                );
            });
    }

    /**
     * Summaries written on the whole address, folded onto the page.
     *
     * Two rows of one day that name the same page and the same subject become
     * one, their totals added · adding is exact here, these being plain counts.
     * The signature is rebuilt the way the model builds it, and written here
     * as well so that this migration keeps meaning what it meant.
     */
    private function foldTheSummariesOntoThePage(): void
    {
        if (! Schema::hasTable('falcon_analytics_daily_counts')) {
            return;
        }

        $days = DB::table('falcon_analytics_daily_counts')
            ->where('kind', 'page')
            ->distinct()
            ->orderBy('day')
            ->pluck('day');

        foreach ($days as $day) {
            $rows = DB::table('falcon_analytics_daily_counts')
                ->where('kind', 'page')
                ->where('day', $day)
                ->get(['id', 'label', 'subject_type', 'total']);

            $folded = [];
            $untouched = true;

            foreach ($rows as $row) {
                $page = $this->pageOf((string) $row->label) ?? (string) $row->label;
                $subject = $row->subject_type !== null ? (string) $row->subject_type : null;
                $key = $page."\0".($subject ?? '');

                if ($page !== (string) $row->label || isset($folded[$key])) {
                    $untouched = false;
                }

                $folded[$key] ??= [
                    'day' => $day,
                    'kind' => 'page',
                    'signature' => hash('sha256', json_encode(['page', $page, null, $subject], JSON_THROW_ON_ERROR)),
                    'label' => $page,
                    'route' => null,
                    'subject_type' => $subject,
                    'total' => 0,
                ];

                $folded[$key]['total'] += (int) $row->total;
            }

            if ($untouched) {
                continue;
            }

            DB::transaction(function () use ($day, $folded): void {
                DB::table('falcon_analytics_daily_counts')->where('kind', 'page')->where('day', $day)->delete();

                foreach (array_chunk(array_values($folded), self::BATCH) as $chunk) {
                    DB::table('falcon_analytics_daily_counts')->insert($chunk);
                }
            });
        }
    }

    /**
     * The twin of `StoredUrl::page()`, written here so the migration does not
     * change meaning if the helper ever does.
     */
    private function pageOf(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : null;
    }
};
