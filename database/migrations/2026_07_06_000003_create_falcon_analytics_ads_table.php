<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ads belonging to a campaign, keyed by the raw ad value seen in landing URLs
 * (config analytics.marketing.params.ad). Each ad carries its own conversion
 * objectives.
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
            $table->string('key', 150);            // raw ad param value, e.g. "cabrio"
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['campaign_id', 'key'], 'fa_ads_campaign_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('falcon_analytics_ads');
    }
};
