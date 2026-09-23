<?php

declare(strict_types=1);

namespace Falcon\Analytics\Database\Factories;

use Falcon\Analytics\Models\SearchConsoleConnection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A connection attached to a property, holding a token still valid for an hour.
 *
 * @extends Factory<SearchConsoleConnection>
 */
final class SearchConsoleConnectionFactory extends Factory
{
    protected $model = SearchConsoleConnection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property' => 'sc-domain:example.com',
            'refresh_token' => 'refresh-'.Str::random(16),
            'access_token' => 'access-'.Str::random(16),
            'token_expires_at' => now()->addHour(),
            'status' => SearchConsoleConnection::STATUS_CONNECTED,
            'last_error' => null,
            'last_synced_at' => null,
        ];
    }

    /** Authorised, but no property chosen yet. */
    public function pendingProperty(): self
    {
        return $this->state(fn (): array => ['property' => null, 'status' => SearchConsoleConnection::STATUS_PENDING_PROPERTY]);
    }
}
