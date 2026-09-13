<?php

declare(strict_types=1);

namespace Falcon\Analytics\Enums;

/**
 * Why an address did, or did not, resolve to a locality.
 *
 * Geolocation degrades to an empty location whatever goes wrong, which is the right behaviour
 * for a request and the wrong one for whoever reads the screen: a missing database and a private
 * address both showed the same blank column, and nothing said which to fix.
 *
 * One set of wording, for the screen and for the console alike. There used to be two, the console
 * keeping English while the dashboard spoke the interface language; both now speak the same
 * language, so a second set would only be something to keep in sync.
 *
 * @internal nothing public returns it · the two enumerations cast on a model
 *           are, and this one is not one of them.
 */
enum GeoStatus: string
{
    /** The database is open and the address resolved. */
    case Ready = 'ready';

    /** No database on disk: analytics:geoip:download has never run, or wrote elsewhere. */
    case NoDatabase = 'no_database';

    /** A file is there but the reader refuses it: truncated download, or wrong edition. */
    case UnreadableDatabase = 'unreadable_database';

    /** A private or reserved address, which no database can place. Set a development address. */
    case PrivateAddress = 'private_address';

    /** A public address the database does not cover. Nothing to fix. */
    case NotInDatabase = 'not_in_database';

    /** One short line, for a screen that has no room for an explanation. */
    public function label(): string
    {
        return match ($this) {
            self::Ready => __('Localisé'),
            self::NoDatabase => __('Aucune base de géolocalisation'),
            self::UnreadableDatabase => __('Base de géolocalisation illisible'),
            self::PrivateAddress => __('Adresse privée'),
            self::NotInDatabase => __('Adresse absente de la base'),
        };
    }

    /**
     * What to do about it, when there is something to do.
     *
     * `__()` returns `array|string|null`, the translator answering an array
     * when a key designates one. Rather than assert otherwise with a cast that
     * would render « Array », an array counts as no advice.
     */
    public function hint(): ?string
    {
        $hint = match ($this) {
            self::NoDatabase => __('Lancez analytics:geoip:download.'),
            self::UnreadableDatabase => __('Relancez analytics:geoip:download pour remplacer le fichier.'),
            self::PrivateAddress => __('Renseignez ANALYTICS_GEOIP_DEV_IP avec une adresse publique pour voir les localités en développement local.'),
            self::Ready, self::NotInDatabase => null,
        };

        return is_string($hint) ? $hint : null;
    }
}
