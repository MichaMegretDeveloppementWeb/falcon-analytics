<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Google Search Console connection: a single row holding the OAuth tokens
 * (encrypted at the model layer) and the property the admin attached. Text
 * columns because encrypted payloads far exceed their plain length.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('falcon_analytics_search_console', function (Blueprint $table): void {
            $table->id();
            $table->string('property', 255)->nullable();
            $table->text('refresh_token');
            $table->text('access_token')->nullable();
            $table->dateTime('token_expires_at')->nullable();
            $table->string('status', 20)->default('pending_property');
            $table->string('last_error', 255)->nullable();
            $table->dateTime('last_synced_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('falcon_analytics_search_console');
    }
};
