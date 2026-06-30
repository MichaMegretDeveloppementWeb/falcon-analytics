<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('falcon_analytics_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')
                ->constrained('falcon_analytics_visitors')
                ->cascadeOnDelete();

            $table->timestamp('started_at');
            // Updated by every event and heartbeat; source of truth for closure.
            $table->timestamp('last_activity_at');
            // Null while open. Set to last_activity_at + timeout by the sweep.
            $table->timestamp('ended_at')->nullable();

            // Network and locality (resolved from a local GeoIP database).
            $table->string('ip', 45)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Parsed user agent.
            $table->string('device_type', 20)->nullable();
            $table->string('device_brand', 60)->nullable();
            $table->string('device_model', 60)->nullable();
            $table->string('browser', 60)->nullable();
            $table->string('browser_version', 30)->nullable();
            $table->string('os', 60)->nullable();
            $table->string('os_version', 30)->nullable();
            $table->boolean('is_bot')->default(false);

            // Acquisition.
            $table->string('referrer', 1024)->nullable();
            $table->string('source', 60)->nullable();
            $table->string('utm_source', 150)->nullable();
            $table->string('utm_medium', 150)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->string('utm_content', 150)->nullable();
            $table->string('utm_term', 150)->nullable();

            $table->string('landing_route', 191)->nullable();
            $table->string('landing_url', 1024)->nullable();

            // Logical reference to the host subject when identified.
            $table->string('subject_type', 32)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->unsignedInteger('pageview_count')->default(0);
            $table->unsignedInteger('event_count')->default(0);

            // Sweep and "active now": WHERE ended_at IS NULL AND last_activity_at ...
            $table->index('last_activity_at', 'fa_sessions_last_activity_idx');
            $table->index(['subject_type', 'subject_id'], 'fa_sessions_subject_idx');
            // Further query-specific indexes (started_at, geo, source) are added
            // in the aggregation lot, justified by their rollup queries and a
            // query budget test.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('falcon_analytics_sessions');
    }
};
