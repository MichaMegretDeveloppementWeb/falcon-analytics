<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs;

/**
 * What an erasing run decided, and why.
 *
 * The command turns this into a sentence and an exit code; the catch-up on a
 * screen load ignores it. **Neither has to know how the other reports**, which
 * is what lets the decision itself live in one place.
 *
 * @internal
 */
final readonly class MaintenanceOutcome
{
    private function __construct(
        public bool $refused,
        public int $erased,
        public string $said,
    ) {}

    /** Nothing was asked of it, and that is a normal answer. */
    public static function nothingToDo(string $said): self
    {
        return new self(refused: false, erased: 0, said: $said);
    }

    /** It is waiting on the summarising, which is how a dead scheduler is harmless. */
    public static function waiting(string $said): self
    {
        return new self(refused: false, erased: 0, said: $said);
    }

    /** The setting cannot be read as a number of days, so nothing was touched. */
    public static function refused(string $said): self
    {
        return new self(refused: true, erased: 0, said: $said);
    }

    public static function erased(int $count, string $said): self
    {
        return new self(refused: false, erased: $count, said: $said);
    }
}
