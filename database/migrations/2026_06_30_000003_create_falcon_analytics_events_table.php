<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a visitor did: a page view, a click, or a named event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('falcon_analytics_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id')
                ->constrained('falcon_analytics_sessions')
                ->cascadeOnDelete();
            // Denormalised from the session to serve visitor-scoped reads
            // (timeline, rollups) without a join.
            $table->foreignId('visitor_id')
                ->constrained('falcon_analytics_visitors')
                ->cascadeOnDelete();

            // Written by hand rather than through the timestamps helper: see the
            // visitors table.
            $table->dateTime('occurred_at');

            $table->string('type', 20);            // pageview | click | custom
            $table->string('name', 120)->nullable(); // data-track-event, funnel join key
            $table->string('route', 191)->nullable();
            $table->string('url', 2048)->nullable();

            /*
             * The page the event belongs to · the path of its address, and
             * nothing else.
             *
             * The address above stays whole, as the visitor opened it — that is
             * what a session's journey shows. But "the most seen pages" asks
             * which PAGE was seen, and answering that from the address splits
             * one page into as many rows as it has campaign links, anchor links
             * or hosts, every row displaying the same path. So the page is
             * written down once, at ingestion, by the same reading the screen
             * makes to display an address, and every count groups on it.
             */
            $table->string('page', 2048)->nullable();

            $table->string('target_selector', 255)->nullable();
            $table->string('target_text', 255)->nullable();
            $table->json('props')->nullable();

            // A score in points, never an amount: a whole number, signed so a
            // penalty can be written down.
            $table->integer('value')->nullable();

            $table->string('subject_type', 32)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->index('occurred_at', 'fa_events_occurred_idx');

            // The dashboard reads: topPages and topClicks filter by type over a
            // date range, funnels match by name over a date range.
            $table->index(['type', 'occurred_at'], 'fa_events_type_occurred_idx');
            $table->index(['name', 'occurred_at'], 'fa_events_name_occurred_idx');

            // The funnel and marketing reads match page views by route over a
            // date range, and walk a visitor's journey ordered by occurred_at.
            $table->index(['route', 'occurred_at'], 'fa_events_route_occurred_idx');
            $table->index(['visitor_id', 'occurred_at'], 'fa_events_visitor_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('falcon_analytics_events');
    }
};
