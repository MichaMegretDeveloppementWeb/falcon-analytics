<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the anonymous page views and clicks of a day amounted to, so that
 * erasing them later costs nothing.
 *
 * Two blocks of the overview count those rows — the most seen pages and the
 * most clicked elements — and they were the only aggregated readings the purge
 * could falsify. A count is additive, so a day plus a day makes two days: these
 * rows answer any depth exactly, forever, while the rows they summarise are
 * free to go.
 *
 * **The unique index carries a hash, not the label.** A page address runs to
 * 2048 characters and MySQL indexes 3072 bytes at most; hashing is what lets
 * the full address stay readable in a column nobody has to index.
 *
 * `subject_type` is read off the SESSION, not off the event, because that is
 * what the screens filter on — a visitor who signs in mid-session has all of
 * their views attributed to them, and the summary has to agree with the reading
 * it replaces.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('falcon_analytics_daily_counts', function (Blueprint $table): void {
            $table->id();
            $table->date('day');

            // 'page' or 'click'. A string rather than a boolean: a third kind
            // would otherwise force a migration on a column that means nothing.
            $table->string('kind', 16);

            $table->char('label_hash', 64);
            $table->text('label');

            // Where a click sits. Null for a page, and for a click whose page
            // carried no route name.
            $table->string('route', 191)->nullable();

            $table->string('subject_type', 32)->nullable();
            $table->unsignedInteger('total')->default(0);

            /*
             * MySQL treats NULLs as distinct in a unique index, so two rows
             * with a null route or a null subject could coexist. The archiving
             * writes a day wholesale — it deletes the day then inserts it — so
             * the index is here to catch a mistake, never to arbitrate one.
             */
            $table->unique(
                ['day', 'kind', 'label_hash', 'route', 'subject_type'],
                'fa_daily_counts_unique'
            );

            // The reading always starts from a date range and a kind.
            $table->index(['kind', 'day'], 'fa_daily_counts_kind_day_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('falcon_analytics_daily_counts');
    }
};
