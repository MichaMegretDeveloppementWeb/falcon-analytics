<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing tagging moves from a fixed campaign/ad parameter to free, arbitrary
 * conditions: the session now stores the landing URL's query parameters verbatim,
 * so any (param, value) rule can be matched against them at report time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('falcon_analytics_sessions', function (Blueprint $table): void {
            $table->dropIndex('fa_sessions_marketing_idx');
        });

        Schema::table('falcon_analytics_sessions', function (Blueprint $table): void {
            $table->dropColumn(['mkt_campaign', 'mkt_ad']);
        });

        Schema::table('falcon_analytics_sessions', function (Blueprint $table): void {
            $table->json('mkt_params')->nullable()->after('landing_url');
        });
    }

    public function down(): void
    {
        Schema::table('falcon_analytics_sessions', function (Blueprint $table): void {
            $table->dropColumn('mkt_params');
        });

        Schema::table('falcon_analytics_sessions', function (Blueprint $table): void {
            $table->string('mkt_campaign', 150)->nullable()->after('landing_url');
            $table->string('mkt_ad', 150)->nullable()->after('mkt_campaign');
            $table->index(['mkt_campaign', 'mkt_ad'], 'fa_sessions_marketing_idx');
        });
    }
};
