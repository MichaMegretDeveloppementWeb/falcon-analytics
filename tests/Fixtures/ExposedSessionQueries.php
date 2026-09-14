<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Fixtures;

use Falcon\Analytics\Repositories\Concerns\ScopesSessionQueries;

/**
 * Les trois expressions SQL du trait, rendues lisibles par un essai.
 *
 * Elles sont privées chez leurs dix appelants, et c'est bien · rien en dehors
 * des dépôts de lecture n'a de raison de les composer. Ce porteur existe donc
 * uniquement pour les éprouver, et il ne fait rien d'autre.
 *
 * Une classe nommée plutôt qu'anonyme · l'analyse statique ne type pas les
 * méthodes d'une classe anonyme rendue par une fonction, et l'essai perdait
 * alors la garantie qu'il croyait tenir.
 */
final class ExposedSessionQueries
{
    use ScopesSessionQueries;

    public function day(): string
    {
        return $this->dayExpression('started_at');
    }

    public function minute(): string
    {
        return $this->minuteExpression('occurred_at');
    }

    public function duration(): string
    {
        return $this->durationSecondsExpression('started_at', 'last_activity_at');
    }
}
