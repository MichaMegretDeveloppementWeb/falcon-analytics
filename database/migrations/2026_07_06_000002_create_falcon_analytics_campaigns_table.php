<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing campaigns: a named grouping of ads, keyed by the raw campaign value
 * seen in landing URLs (config analytics.marketing.params.campaign).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('falcon_analytics_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 150);            // raw campaign param value, e.g. "ete"
            $table->string('name', 150);
            $table->string('platform', 60)->nullable(); // Meta, Google, ...
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('key', 'fa_campaigns_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('falcon_analytics_campaigns');
    }
};
