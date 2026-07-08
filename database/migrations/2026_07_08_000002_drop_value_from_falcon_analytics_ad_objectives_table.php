<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The per-objective "value" (points per event) was stored and editable but never
 * read by any conversion score: marketing conversions count distinct converting
 * visitors, each worth one. Drop the dead column rather than keep a field that
 * silently does nothing. Weighting can be reintroduced deliberately if it is ever
 * needed (the funnels screen already weights its own steps).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('falcon_analytics_ad_objectives', function (Blueprint $table): void {
            $table->dropColumn('value');
        });
    }

    public function down(): void
    {
        Schema::table('falcon_analytics_ad_objectives', function (Blueprint $table): void {
            $table->decimal('value', 12, 2)->nullable();
        });
    }
};
