<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            // Denormalised from the session so visitor-scoped reads need no join.
            $table->foreignId('visitor_id')
                ->constrained('falcon_analytics_visitors')
                ->cascadeOnDelete();

            $table->dateTime('occurred_at');

            $table->string('type', 20);
            $table->string('name', 120)->nullable();
            $table->string('route', 191)->nullable();
            $table->string('url', 2048)->nullable();

            // The path of `url` alone, so page counts do not split one page by query string, anchor or host.
            $table->string('page', 2048)->nullable();

            $table->string('target_selector', 255)->nullable();
            $table->string('target_text', 255)->nullable();
            $table->json('props')->nullable();

            // A score in points, never an amount; signed so a penalty can be written down.
            $table->integer('value')->nullable();

            $table->string('subject_type', 32)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->index('occurred_at', 'fa_events_occurred_idx');

            // Top pages and clicks filter by type, funnels by name, over a date range.
            $table->index(['type', 'occurred_at'], 'fa_events_type_occurred_idx');
            $table->index(['name', 'occurred_at'], 'fa_events_name_occurred_idx');

            // Funnels and marketing match page views by route, and walk a visitor's journey in time order.
            $table->index(['route', 'occurred_at'], 'fa_events_route_occurred_idx');
            $table->index(['visitor_id', 'occurred_at'], 'fa_events_visitor_occurred_idx');
        });

        // Mirrors `EventType::isStored()`; named so a refusal names the rule.
        DB::statement("ALTER TABLE falcon_analytics_events ADD CONSTRAINT fa_events_type_check CHECK (`type` IN ('pageview', 'click', 'custom'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('falcon_analytics_events');
    }
};
