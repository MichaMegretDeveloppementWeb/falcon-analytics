<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conversion objectives of an ad: either a funnel (credited its weighted score,
 * scoped to the ad's traffic) or a named custom event (credited its own value per
 * occurrence). Scoped per ad so one ad never scores another funnel's conversions.
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
            $table->decimal('value', 12, 2)->nullable(); // points per event (event objectives)
            $table->timestamps();

            $table->unique(['ad_id', 'type', 'reference'], 'fa_ad_objectives_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('falcon_analytics_ad_objectives');
    }
};
