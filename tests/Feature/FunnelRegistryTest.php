<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Funnels\Funnel;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Tests\TestCase;
use InvalidArgumentException;

final class FunnelRegistryTest extends TestCase
{
    public function test_it_loads_funnels_declared_in_the_configured_file(): void
    {
        config(['analytics.funnels_path' => __DIR__.'/../Fixtures/analytics-funnels.php']);
        $this->app->forgetInstance(FunnelRegistry::class);

        $registry = app(FunnelRegistry::class);

        $this->assertCount(1, $registry->all());

        $funnel = $registry->get('sample');

        $this->assertNotNull($funnel);
        $this->assertSame('Sample funnel', $funnel->label);
        $this->assertCount(2, $funnel->steps());
        $this->assertSame('home', $funnel->steps()[0]->route);
        $this->assertNull($funnel->steps()[0]->event);
        $this->assertSame(1.0, $funnel->steps()[0]->value);
        $this->assertSame('sample.action', $funnel->steps()[1]->event);
        $this->assertSame(5.0, $funnel->steps()[1]->value);
    }

    public function test_it_rejects_a_step_matching_neither_or_both_of_event_and_route(): void
    {
        $funnel = new Funnel('x', 'X');

        $this->assertThrows(
            fn () => $funnel->step('bad', value: 1),
            InvalidArgumentException::class,
        );

        $this->assertThrows(
            fn () => $funnel->step('bad', value: 1, event: 'e', route: 'r'),
            InvalidArgumentException::class,
        );
    }

    public function test_it_degrades_without_crashing_when_the_funnels_file_throws(): void
    {
        config(['analytics.funnels_path' => __DIR__.'/../Fixtures/analytics-funnels-broken.php']);
        $this->app->forgetInstance(FunnelRegistry::class);

        $registry = app(FunnelRegistry::class);

        // Le fichier déclare un tunnel puis lève : la résolution ne doit pas casser.
        $this->assertCount(1, $registry->all());
        $this->assertNotNull($registry->get('before'));
    }
}
