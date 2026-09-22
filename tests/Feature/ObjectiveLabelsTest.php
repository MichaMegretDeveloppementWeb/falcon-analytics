<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\DTOs\Dashboard\Marketing\ObjectiveTag;
use Falcon\Analytics\Enums\ObjectiveType;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\AdObjective;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Services\Dashboard\ObjectiveLabels;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * An objective reads as what it counts · the list of ads, a campaign's ads and
 * the ad form all name it from here.
 */
final class ObjectiveLabelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_objective_is_named_by_what_it_counts_and_by_its_reference_once_no_longer_declared(): void
    {
        config([
            'analytics.funnels_path' => __DIR__.'/../Fixtures/analytics-funnels.php',
            'analytics.events_path' => __DIR__.'/../Fixtures/analytics-events.php',
        ]);
        $this->app->forgetInstance(FunnelRegistry::class);
        $this->app->forgetInstance(EventRegistry::class);

        $campaign = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta']]]);
        $ad = Ad::create(['campaign_id' => $campaign->id, 'name' => 'Annonce', 'match_conditions' => [['param' => 'creative', 'value' => 'v']]]);

        foreach ([['funnel', 'sample'], ['event', 'sample.action'], ['event', 'gone'], ['funnel', 'sample.action']] as [$type, $reference]) {
            AdObjective::create(['ad_id' => $ad->id, 'type' => $type, 'reference' => $reference]);
        }

        $tags = app(ObjectiveLabels::class)->tagsOf($ad->objectives()->orderBy('id')->get());

        $this->assertSame(
            ['Sample funnel', 'Sample action', 'gone', 'sample.action'],
            array_map(static fn (ObjectiveTag $tag): string => $tag->label, $tags),
            'An event\'s name is no tunnel: a tunnel objective is only named by a declared tunnel.',
        );
        $this->assertSame(
            [ObjectiveType::Funnel, ObjectiveType::Event, ObjectiveType::Event, ObjectiveType::Funnel],
            array_map(static fn (ObjectiveTag $tag): ObjectiveType => $tag->type, $tags),
        );
    }
}
