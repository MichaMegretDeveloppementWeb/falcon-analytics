<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

/**
 * The language the package writes in, whatever the host speaks: its labels are
 * fixed in French, so a date, a number or a country following the host's
 * locale would make a page that mixes the two.
 *
 * @internal
 */
final class Language
{
    public const string CODE = 'fr';
}
