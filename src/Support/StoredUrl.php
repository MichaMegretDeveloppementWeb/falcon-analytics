<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

/**
 * What counts as « the same page » when stored addresses are grouped.
 *
 * The address is stored whole, as the visitor opened it, query string and
 * fragment included, so a session's journey shows the exact link. Grouped on
 * the whole address, one page would split into as many rows as it has
 * variants: `fbclid` is unique per click, anchor links vary the fragment, and
 * two hosts serving one site vary the host.
 *
 * A page is the path of its address, and nothing else: no host, no query
 * string, no fragment. It is computed once, when the row is written, by the
 * same function the screen uses to display an address, so what is grouped and
 * what is shown cannot part company, on any engine.
 *
 * @internal
 */
final class StoredUrl
{
    /**
     * The page an address belongs to · its path, or null when the address has
     * none worth grouping on.
     *
     * `PageUrl::resolve()` displays an address through this same reading.
     */
    public static function page(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : null;
    }
}
