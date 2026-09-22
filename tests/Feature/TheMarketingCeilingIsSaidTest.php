<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Livewire\Admin\Widgets\AdDetailContent;
use Falcon\Analytics\Livewire\Admin\Widgets\CampaignDetailContent;
use Falcon\Analytics\Livewire\Admin\Widgets\MarketingDashboardContent;
use Falcon\Analytics\Livewire\Admin\Widgets\OverviewAcquisition;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * Past the ceiling on sessions read for attribution, every screen whose
 * figures come from them says they under-count.
 *
 * The ceiling keeps the memory bounded, and it is right. What it cost was a
 * figure that looked like any other while it was a part of the real one, and
 * the log was the only place that said so.
 */
final class TheMarketingCeilingIsSaidTest extends TestCase
{
    use RefreshDatabase;

    private const SAID = 'seules les 2 premières sont lues';

    private Campaign $campaign;

    private Ad $ad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->campaign = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta']]]);
        $this->ad = Ad::create(['campaign_id' => $this->campaign->id, 'name' => 'Cabriolet', 'match_conditions' => [['param' => 'src', 'value' => 'meta']]]);

        $visitor = Visitor::create(['uuid' => (string) Str::uuid(), 'first_seen_at' => now(), 'last_seen_at' => now()]);

        Session::query()->insert(array_fill(0, 3, [
            'visitor_id' => $visitor->id,
            'started_at' => now()->subHour(),
            'last_activity_at' => now()->subHour(),
            'is_bot' => false,
            'source' => 'social',
            'mkt_params' => '{"src":"meta"}',
        ]));
    }

    public function test_every_screen_built_on_those_sessions_says_it_past_the_ceiling(): void
    {
        config(['analytics.marketing.max_sessions' => 2]);

        Livewire::test(MarketingDashboardContent::class, ['period' => 30])->call('$refresh')->assertSeeText(self::SAID);
        Livewire::test(CampaignDetailContent::class, ['refId' => $this->campaign->id, 'period' => 30])->call('$refresh')->assertSeeText(self::SAID);
        Livewire::test(AdDetailContent::class, ['refId' => $this->ad->id, 'period' => 30])->call('$refresh')->assertSeeText(self::SAID);
        Livewire::test(OverviewAcquisition::class, ['period' => 30])->call('$refresh')->assertSeeText(self::SAID);
    }

    public function test_no_screen_says_anything_below_the_ceiling(): void
    {
        Livewire::test(MarketingDashboardContent::class, ['period' => 30])->call('$refresh')->assertDontSeeText('premières sont lues');
        Livewire::test(CampaignDetailContent::class, ['refId' => $this->campaign->id, 'period' => 30])->call('$refresh')->assertDontSeeText('premières sont lues');
        Livewire::test(AdDetailContent::class, ['refId' => $this->ad->id, 'period' => 30])->call('$refresh')->assertDontSeeText('premières sont lues');
        Livewire::test(OverviewAcquisition::class, ['period' => 30])->call('$refresh')->assertDontSeeText('premières sont lues');
    }
}
