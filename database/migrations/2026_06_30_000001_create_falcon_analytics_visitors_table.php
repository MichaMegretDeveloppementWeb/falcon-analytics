<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('falcon_analytics_visitors', function (Blueprint $table) {
            $table->id();

            // Persistent 1st-party identifier (cookie fa_vid when consent is
            // granted; a per-session UUID otherwise, giving a 1:1 visitor:session).
            $table->uuid('uuid')->unique();

            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');

            // Stitched identity. The subject lives in the host app, so this is a
            // logical reference (label + id), never a cross-package foreign key.
            $table->string('subject_type', 32)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->unsignedInteger('session_count')->default(0);

            $table->index(['subject_type', 'subject_id'], 'fa_visitors_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('falcon_analytics_visitors');
    }
};
