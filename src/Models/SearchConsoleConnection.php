<?php

declare(strict_types=1);

namespace Falcon\Analytics\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * The (single) Google Search Console connection. Tokens are encrypted at rest
 * through the encrypted cast and never exposed to views; the property is the
 * GSC siteUrl the admin attached ("sc-domain:example.com" or a URL prefix).
 *
 * @property int $id
 * @property string|null $property
 * @property string $refresh_token
 * @property string|null $access_token
 * @property CarbonImmutable|null $token_expires_at
 * @property string $status
 * @property string|null $last_error
 * @property CarbonImmutable|null $last_synced_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class SearchConsoleConnection extends Model
{
    public const STATUS_PENDING_PROPERTY = 'pending_property';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_ERROR = 'error';

    protected $table = 'falcon_analytics_search_console';

    /** @var list<string> */
    protected $fillable = ['property', 'refresh_token', 'access_token', 'token_expires_at', 'status', 'last_error', 'last_synced_at'];

    public static function current(): ?self
    {
        return self::query()->latest('id')->first();
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED && $this->property !== null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'refresh_token' => 'encrypted',
            'access_token' => 'encrypted',
            'token_expires_at' => 'immutable_datetime',
            'last_synced_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
