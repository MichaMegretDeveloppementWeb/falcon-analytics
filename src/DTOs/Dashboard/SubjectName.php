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
     * @param  string  $label  the kind of subject, as the host's configuration names it
     * @param  bool  $isOwnName  the name comes from the subject, and does not already carry the label
     */
    public function __construct(
        public string $name,
        public string $label,
        public bool $isOwnName,
    ) {}

    /** The label shown beside the name · null when the name already carries it. */
    public function labelBesideName(): ?string
    {
        return $this->isOwnName ? $this->label : null;
    }
}
