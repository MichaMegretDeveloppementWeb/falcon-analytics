<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Analytics;
use Falcon\Analytics\Facades\Analytics as AnalyticsFacade;
use Falcon\Analytics\Tests\TestCase;

final class AnalyticsFacadeTest extends TestCase
{
    public function test_it_resolves_the_manager_as_a_shared_singleton(): void
    {
        $this->assertSame(app(Analytics::class), app(Analytics::class));
    }

    public function test_it_registers_resolvers_through_the_facade_onto_the_container_instance(): void
    {
        AnalyticsFacade::consentUsing(fn () => true);
        AnalyticsFacade::resolveSubjectUsing(fn () => ['type' => 'client', 'id' => 5]);

        $this->assertTrue(AnalyticsFacade::hasConsent());
        $this->assertTrue(app(Analytics::class)->hasConsent());
        $this->assertSame(['type' => 'client', 'id' => 5], app(Analytics::class)->subject());
    }
}
