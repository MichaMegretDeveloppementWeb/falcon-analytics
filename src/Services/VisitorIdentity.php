<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

final readonly class VisitorIdentity
{
    private const COOKIE = 'fa_vid';

    private const SESSION_KEY = 'fa_vid';

    private const LIFETIME_MINUTES = 60 * 24 * 365 * 2;

    /**
     * Resolve the visitor UUID. With consent it lives in a persistent httpOnly
     * cookie (server-managed, invisible to JS). Without consent it lives in the
     * server session, giving session-scoped dedup with no persistent client id.
     */
    public function resolve(Request $request, bool $consentGranted): string
    {
        if ($consentGranted) {
            $uuid = $request->cookie(self::COOKIE);

            if (! $this->isUuid($uuid)) {
                $uuid = (string) Str::uuid();
                Cookie::queue(self::COOKIE, $uuid, self::LIFETIME_MINUTES);
            }

            return $uuid;
        }

        $uuid = $request->session()->get(self::SESSION_KEY);

        if (! $this->isUuid($uuid)) {
            $uuid = (string) Str::uuid();
            $request->session()->put(self::SESSION_KEY, $uuid);
        }

        return $uuid;
    }

    /**
     * @phpstan-assert-if-true string $value
     */
    private function isUuid(mixed $value): bool
    {
        return is_string($value) && Str::isUuid($value);
    }
}
