<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite lookup indexes the dashboard reads rely on: topPages/topClicks filter
 * events by type over a date range, funnels match by name over a date range. The
 * table previously indexed occurred_at alone, forcing a scan-then-filter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('falcon_analytics_events', function (Blueprint $table): void {
            $table->index(['type', 'occurred_at'], 'fa_events_type_occurred_idx');
            $table->index(['name', 'occurred_at'], 'fa_events_name_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::table('falcon_analytics_events', function (Blueprint $table): void {
            $table->dropIndex('fa_events_type_occurred_idx');
            $table->dropIndex('fa_events_name_occurred_idx');
        });
    }
};
