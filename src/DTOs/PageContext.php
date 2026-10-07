<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs;

/**
 * Who a page was drawn for, and on which browser: what its later sends are
 * attached to once the host's session no longer says it.
 *
 * @internal
 */
final readonly class PageContext
{
    public function __construct(
        public string $subjectType,
        public int $subjectId,
        public string $browserKey,
    ) {}

    /** @return array{type: string, id: int} */
    public function subject(): array
    {
        return ['type' => $this->subjectType, 'id' => $this->subjectId];
    }

    /**
     * Whether a subject is the one this page was drawn for.
     *
     * @param  array{type: string, id: int}|null  $subject
     */
    public function names(?array $subject): bool
    {
        return $subject !== null
            && $subject['type'] === $this->subjectType
            && $subject['id'] === $this->subjectId;
    }
}
