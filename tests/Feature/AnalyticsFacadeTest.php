<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Analytics;
use Falcon\Analytics\Facades\Analytics as AnalyticsFacade;
use Falcon\Analytics\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

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

    public function test_it_accepts_a_numeric_string_key_from_a_host_resolver(): void
    {
        AnalyticsFacade::resolveSubjectUsing(fn () => ['type' => 'client', 'id' => '42']);

        $this->assertSame(['type' => 'client', 'id' => 42], app(Analytics::class)->subject());
    }

    /**
     * @param  array<string, mixed>  $resolved
     */
    #[DataProvider('subjectsAHostCannotStore')]
    public function test_it_leaves_the_visitor_anonymous_rather_than_collapsing_a_key_it_cannot_store(array $resolved): void
    {
        AnalyticsFacade::resolveSubjectUsing(fn () => $resolved);

        // A cast would attach the visit to subject 0, which gathers everybody's journeys.
        $this->assertNull(app(Analytics::class)->subject());
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function subjectsAHostCannotStore(): array
    {
        return [
            'a uuid key' => [['type' => 'client', 'id' => '9f1c-4b2a-8e77']],
            'a key with a numeric prefix' => [['type' => 'client', 'id' => '12abc']],
            'a float key' => [['type' => 'client', 'id' => 4.5]],
            'an empty key' => [['type' => 'client', 'id' => '']],
            'a type that is not a string' => [['type' => ['client'], 'id' => 5]],
            'an empty type' => [['type' => '', 'id' => 5]],
        ];
    }
}
