<?php

declare(strict_types=1);

namespace Falcon\Analytics\Enums;

/**
 * Why an address did, or did not, resolve to a locality.
 *
 * Geolocation degrades to an empty location whatever goes wrong, which suits a request but not
 * whoever reads the screen: a missing database and a private address give the same blank column.
 * This says which one to fix.
 *
 * The label serves the screen and the console alike · the screen then says
 * what it means for its reader, the console what to run.
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
     * What it means for whoever reads a screen, and who to turn to · no file,
     * command or variable, which that reader cannot act on.
     */
    public function notice(): ?string
    {
        return self::sentence(match ($this) {
            self::NoDatabase => __('La base de géolocalisation est absente, donc les localités restent vides. Signalez-le à la personne qui maintient le site.'),
            self::UnreadableDatabase => __('La base de géolocalisation ne se lit pas, donc les localités restent vides. Signalez-le à la personne qui maintient le site.'),
            self::PrivateAddress => __('Les visiteurs arrivés depuis une adresse privée, comme en développement local, ne se localisent pas.'),
            self::Ready, self::NotInDatabase => null,
        });
    }

    /** What to run about it, for the console, when there is something to do. */
    public function hint(): ?string
    {
        return self::sentence(match ($this) {
            self::NoDatabase => __('Lancez analytics:geoip:download.'),
            self::UnreadableDatabase => __('Relancez analytics:geoip:download pour remplacer le fichier.'),
            self::PrivateAddress => __('Renseignez ANALYTICS_GEOIP_DEV_IP avec une adresse publique pour voir les localités en développement local.'),
            self::Ready, self::NotInDatabase => null,
        });
    }

    /**
     * `__()` returns `array|string|null`, the translator answering an array
     * when a key designates one. An array counts as nothing to say: cast to a
     * string, it would render « Array ».
     *
     * @param  array<array-key, mixed>|string|null  $translated
     */
    private static function sentence(array|string|null $translated): ?string
    {
        return is_string($translated) ? $translated : null;
    }
}
