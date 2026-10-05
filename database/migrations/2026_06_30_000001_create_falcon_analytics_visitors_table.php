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
            $table->engine('InnoDB');

            $table->id();

            // The fa_vid cookie with consent, a per-session UUID without it. A char(36)
            // and not `uuid()`, which MariaDB turns into a type of its own: the column
            // reads and compares alike on both engines.
            $table->char('uuid', 36)->unique();

            // Not TIMESTAMP, in every package table: the engine converts it against the session time zone.
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');

            // Not a foreign key: the subject lives in the host application.
            $table->string('subject_type', 32)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->unsignedInteger('session_count')->default(0);

            $table->foreignId('merged_into_id')
                ->nullable()
                ->constrained('falcon_analytics_visitors')
                ->nullOnDelete();

            $table->index(['subject_type', 'subject_id'], 'fa_visitors_subject_idx');

            // Range-filtered and grouped by day on the overview and the visitors list.
            $table->index('first_seen_at', 'fa_visitors_first_seen_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('falcon_analytics_visitors');
    }
};
