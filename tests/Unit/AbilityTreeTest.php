<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Unit;

use Falcon\Analytics\Enums\Authorization\Ability;
use PHPUnit\Framework\TestCase;

final class AbilityTreeTest extends TestCase
{
    public function test_the_tree_has_a_single_root(): void
    {
        $roots = array_filter(Ability::cases(), fn (Ability $ability): bool => $ability->parent() === null);

        $this->assertSame([Ability::Analytics], array_values($roots));
    }

    public function test_every_ability_reaches_the_root_without_going_round(): void
    {
        foreach (Ability::cases() as $ability) {
            $seen = [];

            for ($step = $ability; $step !== null; $step = $step->parent()) {
                $this->assertNotContains($step, $seen, $ability->value.' goes round.');
                $seen[] = $step;
            }

            $this->assertSame(Ability::Analytics, end($seen));
        }
    }

    public function test_every_name_carries_the_package_prefix(): void
    {
        foreach (Ability::cases() as $ability) {
            $this->assertMatchesRegularExpression('/^analytics(\.[a-z-]+)*$/', $ability->value);
        }
    }

    public function test_an_ability_lies_within_its_ancestors_and_nowhere_else(): void
    {
        $this->assertTrue(Ability::CampaignsDelete->isWithin(Ability::CampaignsDelete));
        $this->assertTrue(Ability::CampaignsDelete->isWithin(Ability::CampaignsEdit));
        $this->assertTrue(Ability::CampaignsDelete->isWithin(Ability::Marketing));
        $this->assertTrue(Ability::CampaignsDelete->isWithin(Ability::Analytics));
        $this->assertFalse(Ability::CampaignsDelete->isWithin(Ability::Ads));
        $this->assertFalse(Ability::Campaigns->isWithin(Ability::CampaignsEdit));
    }
}
