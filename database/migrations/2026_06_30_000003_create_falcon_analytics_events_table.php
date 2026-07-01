<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('falcon_analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')
                ->constrained('falcon_analytics_sessions')
                ->cascadeOnDelete();
            // Denormalised from the session to serve visitor-scoped reads
            // (timeline, rollups) without a join.
            $table->foreignId('visitor_id')
                ->constrained('falcon_analytics_visitors')
                ->cascadeOnDelete();

            $table->timestamp('occurred_at');
            $table->string('type', 20);            // pageview | click | custom
            $table->string('name', 120)->nullable(); // data-track-event, funnel join key
            $table->string('route', 191)->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('target_selector', 255)->nullable();
            $table->string('target_text', 255)->nullable();
            $table->json('props')->nullable();
            $table->decimal('value', 12, 2)->nullable();

            $table->string('subject_type', 32)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->index('occurred_at', 'fa_events_occurred_idx');
            // Name/type indexes are added in the aggregation lot, justified by
            // the rollup and funnel queries that consume them.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('falcon_analytics_events');
    }
};
