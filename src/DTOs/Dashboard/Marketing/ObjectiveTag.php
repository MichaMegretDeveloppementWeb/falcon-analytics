<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard\Marketing;

use Falcon\Analytics\Enums\ObjectiveType;

/**
 * An objective of an ad, with the label it is shown under.
 *
 * @internal
 */
final readonly class ObjectiveTag
{
    public function __construct(
        public ObjectiveType $type,
        public string $reference,
        public string $label,
    ) {}
}
