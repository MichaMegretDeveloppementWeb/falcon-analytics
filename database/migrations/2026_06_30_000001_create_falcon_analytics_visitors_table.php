<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A visitor: one known person, one profile.
 *
 * Identity merging folds duplicate profiles into a canonical one. A folded
 * visitor keeps its uuid and points to the canonical profile through
 * merged_into_id, so beacons from that browser keep landing on the person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('falcon_analytics_visitors', function (Blueprint $table): void {
            $table->id();

            // Persistent 1st-party identifier (cookie fa_vid when consent is
            // granted; a per-session UUID otherwise, giving a 1:1 visitor:session).
            $table->uuid('uuid')->unique();

            // Written by hand rather than through the timestamps helper, which
            // lays down a type the engine converts against the session time
            // zone — the disk would then hold another instant than the one the
            // application means, and nothing would say so.
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');

            // Stitched identity. The subject lives in the host app, so this is a
            // logical reference (label + id), never a cross-package foreign key.
            $table->string('subject_type', 32)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->unsignedInteger('session_count')->default(0);

            $table->foreignId('merged_into_id')
                ->nullable()
                ->constrained('falcon_analytics_visitors')
                ->nullOnDelete();

            $table->index(['subject_type', 'subject_id'], 'fa_visitors_subject_idx');

            // first_seen_at is range-filtered and grouped by day on the overview
            // (new-vs-returning) and the visitors list, both hot screens.
            $table->index('first_seen_at', 'fa_visitors_first_seen_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('falcon_analytics_visitors');
    }
};
