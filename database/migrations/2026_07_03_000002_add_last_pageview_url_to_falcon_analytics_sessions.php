<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The last page view URL of a session, so the ingestion can collapse a reload of
 * the same page (which is not a new view) while still counting real navigations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('falcon_analytics_sessions', function (Blueprint $table): void {
            $table->string('last_pageview_url', 2048)->nullable()->after('landing_url');
        });
    }

    public function down(): void
    {
        Schema::table('falcon_analytics_sessions', function (Blueprint $table): void {
            $table->dropColumn('last_pageview_url');
        });
    }
};
