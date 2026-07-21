<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local cache of the Search Console "Search Analytics" rows: one row per day
 * and query, upserted by the daily sync (GSC data settles over ~3 days, so
 * recent days are re-written). The dashboard only ever reads this table; it
 * never calls the API at display time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('falcon_analytics_search_queries', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('query', 255);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('position', 6, 2)->nullable(); // average position, e.g. 3.42

            $table->unique(['date', 'query'], 'fa_search_queries_date_query_unique');
            $table->index('date', 'fa_search_queries_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('falcon_analytics_search_queries');
    }
};
