<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * first_seen_at is range-filtered and grouped by day on the overview
 * (new-vs-returning) and the visitors list, both hot screens, but the table only
 * carried a (subject_type, subject_id) index. Add the missing index so those reads
 * do not scan the whole visitors table as it grows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('falcon_analytics_visitors', function (Blueprint $table): void {
            $table->index('first_seen_at', 'fa_visitors_first_seen_idx');
        });
    }

    public function down(): void
    {
        Schema::table('falcon_analytics_visitors', function (Blueprint $table): void {
            $table->dropIndex('fa_visitors_first_seen_idx');
        });
    }
};
