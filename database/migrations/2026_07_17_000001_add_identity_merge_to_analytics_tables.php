<?php

declare(strict_types=1);

use Falcon\Analytics\Services\VisitorMerger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Identity merging: one known person = one visitor profile. A folded visitor
 * keeps its uuid and points to the canonical profile through merged_into_id, so
 * beacons from that browser keep landing on the person. Sessions remember the
 * physical browser that produced them (browser_key = the browser's uuid), so two
 * devices of the same person browsing at once still yield two distinct sessions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('falcon_analytics_visitors', function (Blueprint $table): void {
            $table->foreignId('merged_into_id')
                ->nullable()
                ->after('session_count')
                ->constrained('falcon_analytics_visitors')
                ->nullOnDelete();
        });

        Schema::table('falcon_analytics_sessions', function (Blueprint $table): void {
            $table->string('browser_key', 36)->nullable()->after('visitor_id');
        });

        // Existing sessions belong to the browser their visitor represented
        // before any merge: seed the browser key from that visitor's uuid.
        DB::table('falcon_analytics_sessions')->update([
            'browser_key' => DB::raw('(SELECT uuid FROM falcon_analytics_visitors WHERE falcon_analytics_visitors.id = falcon_analytics_sessions.visitor_id)'),
        ]);

        // Fold the duplicate profiles accumulated before identity merging.
        app(VisitorMerger::class)->consolidateExisting();
    }

    public function down(): void
    {
        Schema::table('falcon_analytics_sessions', function (Blueprint $table): void {
            $table->dropColumn('browser_key');
        });

        Schema::table('falcon_analytics_visitors', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('merged_into_id');
        });
    }
};
