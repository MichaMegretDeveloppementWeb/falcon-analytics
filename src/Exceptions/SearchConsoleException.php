<?php

declare(strict_types=1);

namespace Falcon\Analytics\Exceptions;

use RuntimeException;

/**
 * Raised by the Search Console integration (OAuth exchange/refresh, API reads)
 * so callers can distinguish a Google-side failure from any other Throwable.
 * The message stays human-readable: it is logged and stored in the
 * connection's last_error.
 *
 * @internal it never leaves the package · two internal services raise it, and
 *           what drives them swallows it as a `Throwable`. Nothing catches it
 *           by its type, here or anywhere else, so its name promises nothing.
 */
final class SearchConsoleException extends RuntimeException {}
