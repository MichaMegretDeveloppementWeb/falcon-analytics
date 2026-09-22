<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing campaigns: a named grouping of ads.
 *
 * A campaign is identified by free URL-parameter conditions — all of them must
 * match — rather than a single fixed key, so any parameter scheme can be used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('falcon_analytics_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->json('match_conditions')->nullable();
            $table->string('platform', 60)->nullable(); // Meta, Google, ...
            $table->boolean('is_active')->default(true);

            // Written by hand rather than through the timestamps helper, which
            // lays down a type the engine converts against the session time
            // zone. See the visitors table.
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('falcon_analytics_campaigns');
    }
};
