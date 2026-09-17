<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dashboard read path indexes on started_at (promised by the create migration):
 * every dashboard query filters/orders by started_at, and the visitor list
 * resolves the latest/first session per visitor with ORDER BY started_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('falcon_analytics_sessions', function (Blueprint $table): void {
            // Period-scoped-by-visitor filters (whereHas/withCount) and the
            // correlated latest/first-session subqueries (ORDER BY started_at).
            $table->index(['visitor_id', 'started_at'], 'fa_sessions_visitor_started_idx');
            // Full-table period aggregates that also discriminate on is_bot.
            $table->index(['is_bot', 'started_at'], 'fa_sessions_bot_started_idx');
        });
    }

    public function down(): void
    {
        Schema::table('falcon_analytics_sessions', function (Blueprint $table): void {
            $table->dropIndex('fa_sessions_visitor_started_idx');
            $table->dropIndex('fa_sessions_bot_started_idx');
        });
    }
};
