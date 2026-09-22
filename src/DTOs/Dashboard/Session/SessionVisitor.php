<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard\Session;

/**
 * Who a session belongs to, as its detail screen names them.
 *
 * @internal
 */
final readonly class SessionVisitor
{
    /**
     * @param  string  $name  the subject's name, or their label and number, or « Visiteur anonyme »
     * @param  string|null  $label  the subject's label, shown beside a name that is known
     * @param  bool  $viaVisitor  identified by their other sessions, not signed in during this one
     */
    public function __construct(
        public int $id,
        public ?string $uuid,
        public string $name,
        public ?string $label,
        public bool $viaVisitor,
        public bool $isReturning,
    ) {}
}
