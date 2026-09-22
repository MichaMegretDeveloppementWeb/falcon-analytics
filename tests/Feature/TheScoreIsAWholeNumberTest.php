<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Events\TrackedEvent;
use Falcon\Analytics\Funnels\Funnel;
use Falcon\Analytics\Tests\TestCase;
use TypeError;

/**
 * A score is a whole number, and the package refuses anything else.
 *
 * The value a conversion carries is a **score in points**, never an amount: the
 * screens add it up and show it in points, and a sum is never held in a float.
 *
 * **The refusal sits at the signature**, on the line where the mistake is, and
 * not in a total that drifts one day.
 */
final class TheScoreIsAWholeNumberTest extends TestCase
{
    public function test_a_declared_event_refuses_a_score_that_is_not_whole(): void
    {
        $this->expectException(TypeError::class);

        // @phpstan-ignore argument.type (the refusal is the subject)
        TrackedEvent::define('lead.created', 'Demande reçue', value: 5.0);
    }

    public function test_a_funnel_step_refuses_a_score_that_is_not_whole(): void
    {
        $this->expectException(TypeError::class);

        // @phpstan-ignore argument.type (the refusal is the subject)
        (new Funnel('devis', 'Devis'))->step('Offre vue', value: 1.5, route: 'offers');
    }

    public function test_a_whole_score_travels_from_the_declaration_to_the_step(): void
    {
        $event = TrackedEvent::define('lead.created', 'Demande reçue', value: 5);

        $this->assertSame(5, $event->value);
        $this->assertTrue($event->isConversion(), 'Un évènement qui porte un score est une conversion.');

        $funnel = (new Funnel('devis', 'Devis'))->step('Offre vue', value: 2, route: 'offers');

        $this->assertSame(2, $funnel->steps()[0]->value);
    }
}
