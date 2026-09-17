<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How many clicks a session held, so it can still say so once its clicks are
 * gone.
 *
 * A session already carried its page views. Its clicks were only ever countable
 * by counting rows, which stops working the day those rows are erased — and
 * "this visitor clicked three times" is exactly what stays worth knowing about
 * a session whose step-by-step is no longer kept.
 *
 * **Named events are not counted here**, and they do not need to be: they are
 * never erased, so their number is always readable from the rows themselves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('falcon_analytics_sessions', function (Blueprint $table): void {
            $table->unsignedInteger('click_count')->default(0)->after('pageview_count');
        });

        /*
         * Filled from the rows that are still there. A correlated count rather
         * than a join: sessions whose clicks have already been pruned keep the
         * zero the column defaults to, which is the truth we have about them.
         */
        DB::statement(
            'UPDATE falcon_analytics_sessions SET click_count = ('
            .'SELECT COUNT(*) FROM falcon_analytics_events '
            ."WHERE falcon_analytics_events.session_id = falcon_analytics_sessions.id AND type = 'click'"
            .')'
        );
    }

    public function down(): void
    {
        Schema::table('falcon_analytics_sessions', function (Blueprint $table): void {
            $table->dropColumn('click_count');
        });
    }
};
