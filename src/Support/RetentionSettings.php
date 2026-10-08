<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

/**
 * The three retention settings, read and judged in one place · the anonymous
 * page views and clicks, the sessions with the profiles they leave empty, and
 * the named events.
 *
 * @internal
 */
final class RetentionSettings
{
    /** Days the anonymous page views and clicks are kept, or null to keep them. */
    public static function pages(): ?int
    {
        return self::days('retention_days');
    }

    /** Days the sessions are kept, or null to keep them. */
    public static function sessions(): ?int
    {
        return self::days('session_retention_days');
    }

    /** The named events' own duration, or null when they leave with their sessions. */
    public static function namedEvents(): ?int
    {
        return self::days('event_retention_days');
    }

    /** Days the named events are kept, or null to keep them · never past their session's. */
    public static function events(): ?int
    {
        $events = self::days('event_retention_days');
        $sessions = self::sessions();

        if ($events === null || $sessions === null) {
            return $events ?? $sessions;
        }

        return min($events, $sessions);
    }

    /**
     * Why the settings cannot be acted on, or null when they can · a value that
     * is no number of days, sessions kept less long than the page views they
     * hold, or named events kept longer than the sessions they live in.
     */
    public static function refusal(): ?string
    {
        foreach (['retention_days', 'session_retention_days', 'event_retention_days'] as $key) {
            $value = config("analytics.{$key}");

            if ($value !== null && (! is_int($value) || $value < 1)) {
                return "analytics.{$key} doit être un nombre de jours d’au moins 1, ou null pour ne jamais effacer. "
                    .'Valeur lue : '.(is_scalar($value) ? var_export($value, true) : get_debug_type($value)).'.';
            }
        }

        $pages = self::pages();
        $sessions = self::sessions();
        $events = self::days('event_retention_days');

        if ($sessions !== null && ($pages === null || $sessions < $pages)) {
            return 'analytics.session_retention_days ('.$sessions.' jours) est plus court que analytics.retention_days ('
                .($pages === null ? 'jamais effacé' : $pages.' jours').') : effacer une session efface ses pages vues. '
                .'Réglez une durée de sessions au moins égale.';
        }

        if ($events !== null && $sessions !== null && $events > $sessions) {
            return 'analytics.event_retention_days ('.$events.' jours) est plus long que analytics.session_retention_days ('
                .$sessions.' jours) : un événement nommé vit dans sa session et part avec elle. Réglez une durée au plus égale.';
        }

        return null;
    }

    private static function days(string $key): ?int
    {
        $value = config("analytics.{$key}");

        return is_int($value) && $value >= 1 ? $value : null;
    }
}
