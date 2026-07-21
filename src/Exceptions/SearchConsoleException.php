<?php

declare(strict_types=1);

namespace Falcon\Analytics\Exceptions;

use RuntimeException;

/**
 * Raised by the Search Console integration (OAuth exchange/refresh, API reads)
 * so callers can distinguish a Google-side failure from any other Throwable.
 * The message stays human-readable: it is logged and surfaced on the
 * integrations screen through the connection's last_error.
 */
final class SearchConsoleException extends RuntimeException {}
