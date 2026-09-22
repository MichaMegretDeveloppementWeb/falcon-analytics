<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which days have been summarised, and when.
 *
 * **This is what makes a dead scheduler harmless.** The purge refuses to erase
 * a day this table does not hold, so an archiving that never ran stops the
 * erasing too: nothing is summarised, nothing is lost, and the backlog is
 * caught up whenever the scheduler — or an administrator opening a screen —
 * gets the chance.
 *
 * **A day is recorded even when it holds nothing.** Deducing "archived" from
 * the presence of counts would leave a day without traffic looking untreated
 * forever, and the purge would then never move past it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('falcon_analytics_daily_archives', function (Blueprint $table): void {
            $table->id();
            $table->date('day')->unique('fa_daily_archives_day_unique');
            // Written by hand rather than through the timestamps helper, which
            // lays down a type the engine converts against the session time
            // zone. See the visitors table.
            $table->dateTime('archived_at');

            /*
             * When the day's anonymous detail was erased, and null while it is
             * still there.
             *
             * **This is what the reading splits on**, and it has to be recorded
             * rather than deduced. A day whose detail is gone must be read from
             * its summary; a day that still has its detail must be read from
             * the detail — reading both would count a kept named click twice,
             * since the summary counted it too.
             *
             * Deducing the boundary from the retention would be wrong the
             * moment the two disagree: a scheduler that stopped for a month
             * leaves days that are past the retention and still intact, and a
             * retention shortened yesterday moves a line that erasing has not
             * crossed yet.
             */
            $table->dateTime('pruned_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('falcon_analytics_daily_archives');
    }
};
