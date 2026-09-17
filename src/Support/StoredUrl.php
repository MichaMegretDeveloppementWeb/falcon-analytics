<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

/**
 * What counts as « the same page » when stored addresses are grouped.
 *
 * **The address is stored whole, as the visitor opened it** — query string and
 * fragment included — and that is right: a session's journey shows the exact
 * link. But « les pages les plus vues » asks a different question, and asking
 * it of the whole address answers badly.
 *
 * **Measured 2026-09-14** · one page opened three times, twice through a
 * campaign link, came back as three rows of one view each — and the screen
 * displayed the same path on all three, since it renders the path and grouped
 * on the address. `fbclid` is unique per click, so on campaign traffic the real
 * top page never reached the top of the list. Anchor links do the same with
 * `#`, and two hosts serving one site would do it with the host.
 *
 * **A page is the path of its route, and nothing else.** No host, no query
 * string, no fragment. It is computed once, when the row is written, by the
 * same function the screen uses to display an address — so what is grouped
 * and what is shown cannot part company, on any engine, without a line of SQL.
 *
 * It was a driver-specific SQL expression cutting the address at the `?` at
 * first. Correct for that one case, blind to the two others, and four dialects
 * to keep in step. Writing the page down is simpler and exact.
 *
 * @internal
 */
final class StoredUrl
{
    /**
     * The page an address belongs to · its path, or null when the address has
     * none worth grouping on.
     *
     * The same reading as `PageUrl::resolve()` makes for display, which is the
     * whole point.
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
