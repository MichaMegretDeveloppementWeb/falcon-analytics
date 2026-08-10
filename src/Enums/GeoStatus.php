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
 * Two audiences, two sets of wording: the dashboard speaks the interface language, the console
 * stays in English like every other command in this package.
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

    /** What to do about it, when there is something to do. */
    public function hint(): ?string
    {
        return match ($this) {
            self::NoDatabase => __('Lancez analytics:geoip:download.'),
            self::UnreadableDatabase => __('Relancez analytics:geoip:download pour remplacer le fichier.'),
            self::PrivateAddress => __('Renseignez ANALYTICS_GEOIP_DEV_IP avec une adresse publique pour voir les localités en développement local.'),
            self::Ready, self::NotInDatabase => null,
        };
    }

    /** The same states, for the console. */
    public function consoleLabel(): string
    {
        return match ($this) {
            self::Ready => 'located',
            self::NoDatabase => 'no GeoIP database',
            self::UnreadableDatabase => 'GeoIP database unreadable',
            self::PrivateAddress => 'private address',
            self::NotInDatabase => 'address not in the database',
        };
    }

    public function consoleHint(): ?string
    {
        return match ($this) {
            self::NoDatabase => 'Run analytics:geoip:download.',
            self::UnreadableDatabase => 'Run analytics:geoip:download again to replace the file.',
            self::PrivateAddress => 'Set ANALYTICS_GEOIP_DEV_IP to a public address to see localities in local development.',
            self::Ready, self::NotInDatabase => null,
        };
    }
}
