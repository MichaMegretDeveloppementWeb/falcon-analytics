<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-v1 audit indexes. Events: the funnel and marketing reads match pageviews
 * by route over a date range, and walk a visitor's journey ordered by
 * occurred_at — both previously fell back to range scans plus filesort.
 * Sessions: the country-name search resolves the stored ISO codes on every
 * keystroke, so `country` needs its own index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('falcon_analytics_events', function (Blueprint $table): void {
            $table->index(['route', 'occurred_at'], 'fa_events_route_occurred_idx');
            $table->index(['visitor_id', 'occurred_at'], 'fa_events_visitor_occurred_idx');
        });

        Schema::table('falcon_analytics_sessions', function (Blueprint $table): void {
            $table->index('country', 'fa_sessions_country_idx');
        });
    }

    public function down(): void
    {
        Schema::table('falcon_analytics_events', function (Blueprint $table): void {
            $table->dropIndex('fa_events_route_occurred_idx');
            $table->dropIndex('fa_events_visitor_occurred_idx');
        });

        Schema::table('falcon_analytics_sessions', function (Blueprint $table): void {
            $table->dropIndex('fa_sessions_country_idx');
        });
    }
};
