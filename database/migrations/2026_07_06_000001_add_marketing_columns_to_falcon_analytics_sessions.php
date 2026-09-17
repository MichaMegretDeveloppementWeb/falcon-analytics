<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raw campaign/ad identifiers read from the landing URL's query string, so paid
 * traffic can be attributed to a named ad (and its objectives) at report time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('falcon_analytics_sessions', function (Blueprint $table): void {
            $table->string('mkt_campaign', 150)->nullable()->after('landing_url');
            $table->string('mkt_ad', 150)->nullable()->after('mkt_campaign');
            $table->index(['mkt_campaign', 'mkt_ad'], 'fa_sessions_marketing_idx');
        });
    }

    public function down(): void
    {
        Schema::table('falcon_analytics_sessions', function (Blueprint $table): void {
            $table->dropIndex('fa_sessions_marketing_idx');
            $table->dropColumn(['mkt_campaign', 'mkt_ad']);
        });
    }
};
