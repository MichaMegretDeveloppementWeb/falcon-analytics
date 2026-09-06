<?php

declare(strict_types=1);

namespace Falcon\Analytics\View;

use Falcon\Analytics\Facades\Analytics;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Throwable;

final class Collector
{
    /**
     * The markup emitted by @analyticsConfig: an inline config object, and
     * nothing else.
     *
     * **Le code du collecteur n'est plus ici.** L'hote l'importe dans son
     * entree JavaScript publique, et c'est son build qui le nomme, le versionne
     * et le sert · une balise de moins, et un fichier de moins servi par PHP.
     *
     * **Ce qui reste ne peut pas etre empaquete**, et c'est pour cela que la
     * directive existe encore · le nom de la route change a chaque page, et le
     * suivi se coupe quand l'administratrice est connectee. Un fichier compile
     * ne peut porter ni l'un ni l'autre. C'est la separation que fait Livewire
     * entre `@livewireScripts`, du code, et `@livewireScriptConfig`, des
     * donnees.
     *
     * **Ne rien emettre vaut suivi coupe** · le collecteur lit
     * `window.__falconAnalytics` et sort de lui-meme quand il est absent, donc
     * l'hote n'a aucune condition a ecrire de son cote.
     *
     * Runs inline on every host page, so any failure degrades to an empty
     * string rather than breaking the host.
     */
    public static function render(): string
    {
        try {
            // Suppressed when tracking is off or the current context is excluded
            // (e.g. an authenticated admin), so no collector runs on those pages.
            if (! config('analytics.enabled') || Analytics::isExcluded()) {
                return '';
            }

            $config = json_encode([
                'endpoint' => '/'.ltrim((string) config('analytics.endpoint'), '/'),
                'route' => Route::currentRouteName(),
                'heartbeat' => (int) config('analytics.session.heartbeat_seconds') * 1000,
                'flush' => (int) config('analytics.session.flush_seconds') * 1000,
            ], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

            return "<script>window.__falconAnalytics={$config};</script>";
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->warning('Collector.render_failed', ['exception' => $e->getMessage()]);

            return '';
        }
    }
}
