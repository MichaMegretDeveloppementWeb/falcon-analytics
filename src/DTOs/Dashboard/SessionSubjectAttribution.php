<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard;

/**
 * The subject a session is displayed under. Either the session's own subject
 * (the user was authenticated during the session) or, via fallback, the subject
 * stitched on the session's visitor (the user is known but was not connected
 * during this particular session).
 */
final readonly class SessionSubjectAttribution
{
    public function __construct(
        public string $guard,
        public int $id,
        public bool $viaVisitor,
    ) {}
}
