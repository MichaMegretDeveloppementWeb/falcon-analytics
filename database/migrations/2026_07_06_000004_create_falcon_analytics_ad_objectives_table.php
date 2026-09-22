<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conversion objectives of an ad: either a funnel (the visitor completed its
 * steps in order) or a named custom event (the visitor fired it).
 *
 * Either way the ad is credited one converting visitor, scoped to its own
 * attributed traffic so one ad never scores another's conversions. **There is
 * no per-objective weight**: marketing conversions count distinct converting
 * visitors, each worth one, and a weight that no score reads would silently do
 * nothing. The funnels screen weights its own steps, which is a different
 * question.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('falcon_analytics_ad_objectives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ad_id')
                ->constrained('falcon_analytics_ads')
                ->cascadeOnDelete();
            $table->string('type', 20);            // funnel | event
            $table->string('reference', 191);      // funnel key or event name

            // Written by hand rather than through the timestamps helper: see the
            // visitors table.
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique(['ad_id', 'type', 'reference'], 'fa_ad_objectives_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('falcon_analytics_ad_objectives');
    }
};
