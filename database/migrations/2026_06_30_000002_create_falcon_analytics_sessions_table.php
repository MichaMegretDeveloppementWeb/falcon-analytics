<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A session: one visit, by one browser.
 *
 * A session remembers the physical browser that produced it (browser_key = the
 * browser's uuid), so two devices of the same person browsing at once still
 * yield two distinct sessions even once their profiles are merged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('falcon_analytics_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('visitor_id')
                ->constrained('falcon_analytics_visitors')
                ->cascadeOnDelete();

            $table->string('browser_key', 36)->nullable();

            $this->timing($table);
            $this->locality($table);
            $this->device($table);
            $this->acquisition($table);
            $this->subjectAndCounters($table);
            $this->indexes($table);
        });
    }

    private function timing(Blueprint $table): void
    {
        $table->dateTime('started_at');
        // Updated by every event and heartbeat; source of truth for closure.
        $table->dateTime('last_activity_at');
        // Null while open. Set to last_activity_at + timeout by the sweep.
        $table->dateTime('ended_at')->nullable();
    }

    private function locality(Blueprint $table): void
    {
        $table->string('ip', 45)->nullable();
        $table->string('country', 2)->nullable();
        $table->string('region', 100)->nullable();
        $table->string('city', 100)->nullable();
        $table->decimal('latitude', 10, 7)->nullable();
        $table->decimal('longitude', 10, 7)->nullable();
    }

    private function device(Blueprint $table): void
    {
        $table->string('device_type', 20)->nullable();
        $table->string('device_brand', 60)->nullable();
        $table->string('device_model', 60)->nullable();
        $table->string('browser', 60)->nullable();
        $table->string('browser_version', 30)->nullable();
        $table->string('os', 60)->nullable();
        $table->string('os_version', 30)->nullable();
        $table->boolean('is_bot')->default(false);
    }

    private function acquisition(Blueprint $table): void
    {
        $table->string('referrer', 2048)->nullable();
        $table->string('source', 60)->nullable();
        $table->string('utm_source', 150)->nullable();
        $table->string('utm_medium', 150)->nullable();
        $table->string('utm_campaign', 150)->nullable();
        $table->string('utm_content', 150)->nullable();
        $table->string('utm_term', 150)->nullable();

        $table->string('landing_route', 191)->nullable();
        $table->string('landing_url', 2048)->nullable();

        // The landing URL's query parameters, verbatim: marketing matches its rules against them at report time.
        $table->json('mkt_params')->nullable();

        // Lets the ingestion collapse a reload of the same page into one view.
        $table->string('last_pageview_url', 2048)->nullable();
    }

    private function subjectAndCounters(Blueprint $table): void
    {
        $table->string('subject_type', 32)->nullable();
        $table->unsignedBigInteger('subject_id')->nullable();

        $table->unsignedInteger('pageview_count')->default(0);
        // Counted on arrival, named or not, so the total outlives the purge of anonymous clicks.
        $table->unsignedInteger('click_count')->default(0);
        $table->unsignedInteger('event_count')->default(0);
    }

    private function indexes(Blueprint $table): void
    {
        // Sweep and "active now": WHERE ended_at IS NULL AND last_activity_at ...
        $table->index('last_activity_at', 'fa_sessions_last_activity_idx');
        // Ingestion hot path: open-session lookup by visitor ordered by activity.
        $table->index(['visitor_id', 'last_activity_at'], 'fa_sessions_visitor_activity_idx');
        $table->index(['subject_type', 'subject_id'], 'fa_sessions_subject_idx');

        // Dashboard reads filter or order by started_at, per visitor or across all sessions by is_bot.
        $table->index(['visitor_id', 'started_at'], 'fa_sessions_visitor_started_idx');
        $table->index(['is_bot', 'started_at'], 'fa_sessions_bot_started_idx');

        // The country-name search matches the stored ISO codes on every keystroke.
        $table->index('country', 'fa_sessions_country_idx');
    }

    public function down(): void
    {
        Schema::dropIfExists('falcon_analytics_sessions');
    }
};
