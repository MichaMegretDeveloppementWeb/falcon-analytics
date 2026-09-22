<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ads belonging to a campaign, each carrying its own conversion objectives.
 *
 * Like a campaign, an ad is identified by free URL-parameter conditions rather
 * than a fixed key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('falcon_analytics_ads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')
                ->constrained('falcon_analytics_campaigns')
                ->cascadeOnDelete();
            $table->string('name', 150);
            $table->json('match_conditions')->nullable();
            $table->boolean('is_active')->default(true);

            // Written by hand rather than through the timestamps helper: see the
            // visitors table.
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            // The foreign key needs an index of its own: nothing else backs it
            // now that the ads are matched by conditions rather than by a
            // (campaign_id, key) unique.
            $table->index('campaign_id', 'fa_ads_campaign_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('falcon_analytics_ads');
    }
};
