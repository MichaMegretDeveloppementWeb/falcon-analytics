<?php

declare(strict_types=1);

namespace Falcon\Analytics\View;

use Falcon\Analytics\DTOs\PageContext;
use Falcon\Analytics\Facades\Analytics;
use Falcon\Analytics\Services\PageContextSealer;
use Falcon\Analytics\Services\VisitorIdentityResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Throwable;

/** @internal what a host writes is the `@analyticsCollector` directive. */
final class Collector
{
    /**
     * What `@analyticsCollector` needs to know, or null when nothing is to be
     * measured on this page.
     *
     * **Null means tracking is cut**, and the view then declares nothing at all:
     * no script is brought in, and the collector's own guard — it reads
     * `window.__falconAnalytics` and leaves when the object is absent — is never
     * even reached. The host writes no condition of its own.
     *
     * Two of these values cannot be bundled, so they travel inline, outside the
     * compiled file: the current route's name changes on every page, and the
     * cut applies per request. So does the page's sealed context, drawn only
     * for a signed-in subject · see {@see contextOfThisPage()}.
     *
     * Runs on every host page, so any failure degrades to a page without
     * measurement rather than to a broken page.
     */
    public static function configuration(): ?string
    {
        try {
            if (config('analytics.enabled') !== true || Analytics::isExcluded()) {
                return null;
            }

            $configuration = [
                'endpoint' => route('analytics.web.ingest', [], absolute: false),
                'route' => Route::currentRouteName(),
                'heartbeat' => (int) config('analytics.session.heartbeat_seconds') * 1000,
                'flush' => (int) config('analytics.session.flush_seconds') * 1000,
            ];

            $context = self::contextOfThisPage();

            if ($context !== null) {
                $configuration['context'] = $context;
            }

            return json_encode($configuration, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->warning('Collector.configuration_failed', ['exception' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Who this page is drawn for and on which browser, sealed · null for an
     * anonymous page, which sends exactly what it always did.
     *
     * Read here, while the host's session still holds both: a send that leaves
     * after a sign-out finds them gone. A failure costs the context only, never
     * the collector.
     */
    private static function contextOfThisPage(): ?string
    {
        try {
            $request = request();
            $subject = Analytics::subject();

            if ($subject === null || ! $request->hasSession()) {
                return null;
            }

            $browserKey = app(VisitorIdentityResolver::class)->resolve($request, Analytics::hasConsent());

            return app(PageContextSealer::class)->seal(new PageContext($subject['type'], $subject['id'], $browserKey));
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->warning('Collector.context_failed', ['exception' => $e->getMessage()]);

            return null;
        }
    }
}
