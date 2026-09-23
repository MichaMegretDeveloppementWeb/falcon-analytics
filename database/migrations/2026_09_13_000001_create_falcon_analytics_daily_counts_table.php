<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the anonymous page views and clicks of a day amounted to, so that
 * erasing them later costs nothing.
 *
 * Two blocks of the overview count those rows — the most seen pages and the
 * most clicked elements — and they are the only aggregated readings the purge
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

            // Not a boolean: a kind names itself, and a third one fits the same column.
            $table->string('kind', 16);

            // Kind, label, route and subject hashed as one: the unique key stays under MyISAM's 1000-byte limit.
            $table->char('signature', 64);

            $table->text('label');

            // Null for a page, and for a click whose page has no route name.
            $table->string('route', 191)->nullable();

            $table->string('subject_type', 32)->nullable();
            $table->unsignedInteger('total')->default(0);

            // A safety net: the hash covers null routes, which MySQL would let repeat in a raw-column unique.
            $table->unique(['day', 'signature'], 'fa_daily_counts_unique');

            // The reading always starts from a date range and a kind.
            $table->index(['kind', 'day'], 'fa_daily_counts_kind_day_idx');
        });

        // Mirrors the `KIND_` constants of `DailyCount`; named so a refusal names the rule.
        DB::statement("ALTER TABLE falcon_analytics_daily_counts ADD CONSTRAINT fa_daily_counts_kind_check CHECK (`kind` IN ('page', 'click'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('falcon_analytics_daily_counts');
    }
};
