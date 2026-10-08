<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the sessions of a day amounted to, so that erasing them later costs no
 * figure that adds up · by subject, for the day as a whole and broken down by
 * device, by locality and by source.
 *
 * A figure that counts distinct people does not add up across days, so none is
 * kept here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('falcon_analytics_daily_sessions', function (Blueprint $table): void {
            $table->engine('InnoDB');

            $table->id();
            $table->date('day');

            // Which breakdown the row belongs to · `all` is the day as a whole.
            $table->string('dimension', 16);

            // Dimension, subject, keys and parameters hashed as one: nullable columns would repeat in a raw-column unique.
            $table->char('signature', 64);

            $table->string('subject_type', 32)->nullable();

            // The device type, the country or the source, and the city of a locality.
            $table->string('key_a', 191)->nullable();
            $table->string('key_b', 191)->nullable();

            // The parameters a session arrived with, for a source · a campaign claims them when a screen is read.
            $table->json('mkt_params')->nullable();

            $table->unsignedInteger('sessions')->default(0);
            $table->unsignedInteger('pageviews')->default(0);

            // Signed, as the duration it adds up is read by the screens.
            $table->bigInteger('seconds')->default(0);

            $table->unsignedInteger('bounces')->default(0);

            $table->unique(['day', 'signature'], 'fa_daily_sessions_unique');

            // The reading always starts from a breakdown and a date range.
            $table->index(['dimension', 'day'], 'fa_daily_sessions_dimension_day_idx');
        });

        // Named so a refusal names the rule.
        DB::statement("ALTER TABLE falcon_analytics_daily_sessions ADD CONSTRAINT fa_daily_sessions_dimension_check CHECK (`dimension` IN ('all', 'device', 'locality', 'source'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('falcon_analytics_daily_sessions');
    }
};
