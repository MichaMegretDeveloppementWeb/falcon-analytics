<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A campaign and an ad are now identified by free URL-parameter conditions
 * (all must match, AND) rather than a single fixed key value, so any parameter
 * scheme can be used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('falcon_analytics_campaigns', function (Blueprint $table): void {
            $table->dropUnique('fa_campaigns_key_unique');
        });

        Schema::table('falcon_analytics_campaigns', function (Blueprint $table): void {
            $table->dropColumn('key');
            $table->json('match_conditions')->nullable()->after('name');
        });

        // Give the campaign_id foreign key its own index before dropping the
        // composite unique that currently backs it (MySQL requires one for the FK).
        Schema::table('falcon_analytics_ads', function (Blueprint $table): void {
            $table->index('campaign_id', 'fa_ads_campaign_idx');
        });

        Schema::table('falcon_analytics_ads', function (Blueprint $table): void {
            $table->dropUnique('fa_ads_campaign_key_unique');
        });

        Schema::table('falcon_analytics_ads', function (Blueprint $table): void {
            $table->dropColumn('key');
            $table->json('match_conditions')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('falcon_analytics_campaigns', function (Blueprint $table): void {
            $table->dropColumn('match_conditions');
            $table->string('key', 150)->after('name');
            $table->unique('key', 'fa_campaigns_key_unique');
        });

        Schema::table('falcon_analytics_ads', function (Blueprint $table): void {
            $table->dropColumn('match_conditions');
            $table->string('key', 150)->after('name');
            $table->unique(['campaign_id', 'key'], 'fa_ads_campaign_key_unique');
        });

        Schema::table('falcon_analytics_ads', function (Blueprint $table): void {
            $table->dropIndex('fa_ads_campaign_idx');
        });
    }
};
