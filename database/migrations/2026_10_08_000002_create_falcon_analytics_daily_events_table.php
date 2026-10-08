<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the named events of a day amounted to, so that erasing them later costs
 * no figure that adds up · by name, and by the subject of the visitor who sent
 * them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('falcon_analytics_daily_events', function (Blueprint $table): void {
            $table->engine('InnoDB');

            $table->id();
            $table->date('day');

            // Subject and name hashed as one: a null subject would repeat in a raw-column unique.
            $table->char('signature', 64);

            // The visitor's, as the events screen reads it, on the day the summary was written.
            $table->string('subject_type', 32)->nullable();

            $table->string('name', 120);
            $table->unsignedInteger('total')->default(0);

            // The scores the occurrences carried, and how many carried one · the declared score covers the others.
            $table->bigInteger('value_sum')->default(0);
            $table->unsignedInteger('value_count')->default(0);

            // The reading starts from a date range, which this key's first column serves.
            $table->unique(['day', 'signature'], 'fa_daily_events_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('falcon_analytics_daily_events');
    }
};
