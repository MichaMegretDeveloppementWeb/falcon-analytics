<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard;

/**
 * How a tracked subject is shown · its own name when the host's columns give
 * one, otherwise its label and number.
 *
 * @internal
 */
final readonly class SubjectName
{
    /**
     * @param  string|null  $label  the subject's label, shown beside a name of its own · null when the name already carries it
     */
    public function __construct(
        public string $name,
        public ?string $label,
    ) {}
}
