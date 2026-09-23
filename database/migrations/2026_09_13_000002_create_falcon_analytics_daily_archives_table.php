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
            $table->dateTime('archived_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('falcon_analytics_daily_archives');
    }
};
