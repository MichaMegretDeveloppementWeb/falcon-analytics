<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ads belonging to a campaign, each carrying its own conversion objectives.
 *
 * Like a campaign, an ad is identified by free URL-parameter conditions.
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

            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            // The foreign key needs an index of its own: no other index starts with campaign_id.
            $table->index('campaign_id', 'fa_ads_campaign_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('falcon_analytics_ads');
    }
};
