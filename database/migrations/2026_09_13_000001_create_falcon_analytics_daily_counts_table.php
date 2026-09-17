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

            /*
             * The whole identity of a row, as one hash · kind, label, route
             * and subject together.
             *
             * **One hashed column rather than the four real ones**, and it is
             * not a nicety. An index over the four runs to some 1 200 bytes,
             * which InnoDB accepts and **MyISAM refuses at 1 000** — measured
             * on a local MySQL still defaulting to MyISAM, where the migration
             * stopped dead. A host is free to run whichever engine it likes,
             * and a package that only migrates on one of them is a package
             * that fails on somebody's afternoon.
             *
             * Day plus this is 259 bytes, which every engine takes.
             */
            $table->char('signature', 64);

            $table->text('label');

            // Where a click sits. Null for a page, and for a click whose page
            // carried no route name.
            $table->string('route', 191)->nullable();

            $table->string('subject_type', 32)->nullable();
            $table->unsignedInteger('total')->default(0);

            /*
             * The hash carries the nulls too, so unlike an index over the raw
             * columns this one does not let two rows with a null route sit side
             * by side — MySQL counting NULLs as distinct. The archiving writes
             * a day wholesale anyway, deleting it then inserting it, so the
             * index is here to catch a mistake rather than to arbitrate one.
             */
            $table->unique(['day', 'signature'], 'fa_daily_counts_unique');

            // The reading always starts from a date range and a kind.
            $table->index(['kind', 'day'], 'fa_daily_counts_kind_day_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('falcon_analytics_daily_counts');
    }
};
