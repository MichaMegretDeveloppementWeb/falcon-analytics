<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Events\TrackedEvent;
use Falcon\Analytics\Livewire\Admin\AdDetailPage;
use Falcon\Analytics\Livewire\Admin\AdForm;
use Falcon\Analytics\Livewire\Admin\CampaignDetailPage;
use Falcon\Analytics\Livewire\Admin\CampaignForm;
use Falcon\Analytics\Livewire\Admin\CampaignsPage;
use Falcon\Analytics\Livewire\Admin\Widgets\CampaignDetailContent;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\AdObjective;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;

final class MarketingPagesTest extends TestCase
{
    use RefreshDatabase;

    private TestAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = TestAdmin::create([]);
    }

    private function campaign(string $name = 'Été', ?string $platform = null, string $value = 'meta'): Campaign
    {
        return Campaign::create([
            'name' => $name,
            'platform' => $platform,
            'match_conditions' => [['param' => 'src', 'value' => $value]],
        ]);
    }

    public function test_it_mounts_the_marketing_screens_at_their_own_prefix_under_fixed_route_names(): void
    {
        $this->assertSame('/admin/marketing', route('analytics.admin.marketing.dashboard', absolute: false));
        $this->assertSame('/admin/marketing/campaigns', route('analytics.admin.marketing.campaigns', absolute: false));
        $this->assertSame('/admin/marketing/campaigns/1', route('analytics.admin.marketing.campaigns.show', ['campaign' => 1], absolute: false));
        $this->assertSame('/admin/marketing/ads', route('analytics.admin.marketing.ads', absolute: false));
        $this->assertSame('/admin/marketing/ads/1', route('analytics.admin.marketing.ads.show', ['ad' => 1], absolute: false));
    }

    public function test_it_protects_the_marketing_module_from_guests(): void
    {
        $campaign = $this->campaign();

        $this->get(route('analytics.admin.marketing.dashboard'))->assertRedirect(route('login'));
        $this->get(route('analytics.admin.marketing.campaigns'))->assertRedirect(route('login'));
        $this->get(route('analytics.admin.marketing.campaigns.show', $campaign))->assertRedirect(route('login'));
        $this->get(route('analytics.admin.marketing.ads'))->assertRedirect(route('login'));
    }

    public function test_it_defers_the_campaign_performance_and_dispatches_the_ads_table_metrics(): void
    {
        $campaign = $this->campaign();

        $this->actingAs($this->admin, 'admin');

        Livewire::test(CampaignDetailContent::class, ['refId' => $campaign->id, 'period' => 30])
            ->call('$refresh')
            ->assertDispatched('an-campaign-metrics-loaded')
            ->assertSeeText(__('Sessions'))
            ->assertSeeText(__('Taux de conversion'));
    }

    public function test_it_fills_its_inline_ads_table_metrics_from_the_dispatched_event(): void
    {
        $campaign = $this->campaign();

        $this->actingAs($this->admin, 'admin');

        Livewire::test(CampaignDetailPage::class, ['campaign' => $campaign])
            ->assertSet('adMetrics', [])
            ->call('fillAdMetrics', [7 => ['sessions' => 12, 'visitors' => 9]], [7 => 4])
            ->assertSet('adMetrics', [7 => ['sessions' => 12, 'visitors' => 9]])
            ->assertSet('adConversions', [7 => 4]);
    }

    public function test_it_renders_the_four_marketing_screens_for_an_admin(): void
    {
        $campaign = $this->campaign('Été 2026', 'Meta', 'meta_ete');
        Ad::create(['campaign_id' => $campaign->id, 'name' => 'Cabriolet', 'match_conditions' => [['param' => 'creative', 'value' => 'cabrio']]]);

        $this->actingAs($this->admin, 'admin');

        $this->get(route('analytics.admin.marketing.dashboard'))->assertSuccessful()->assertSeeText(__('Vue d\'ensemble'));
        $this->get(route('analytics.admin.marketing.campaigns'))->assertSuccessful()->assertSeeText('Été 2026')->assertSeeText('meta_ete');
        $this->get(route('analytics.admin.marketing.campaigns.show', $campaign))->assertSuccessful()->assertSeeText('Cabriolet')->assertSeeText('cabrio');
        $this->get(route('analytics.admin.marketing.ads'))->assertSuccessful()->assertSeeText('Cabriolet');

        $ad = Ad::where('name', 'Cabriolet')->firstOrFail();

        $this->get(route('analytics.admin.marketing.ads.show', $ad))->assertSuccessful()
            ->assertSeeText('Cabriolet')
            ->assertSeeText('Été 2026')
            ->assertSeeText(__('Performance'));
    }

    /** Each window is a dialog the keyboard enters, named by its title for a screen reader. */
    public function test_each_marketing_screen_carries_its_windows_as_the_kits_dialogs(): void
    {
        $campaign = $this->campaign('Été 2026');
        $ad = Ad::create(['campaign_id' => $campaign->id, 'name' => 'Cabriolet', 'match_conditions' => [['param' => 'creative', 'value' => 'cabrio']]]);

        $this->actingAs($this->admin, 'admin');

        $screens = [
            'the campaigns' => [route('analytics.admin.marketing.campaigns'), ['an-campaign-form', 'an-campaign-delete']],
            'a campaign' => [route('analytics.admin.marketing.campaigns.show', $campaign), ['an-campaign-form', 'an-campaign-delete', 'an-ad-form', 'an-ad-delete']],
            'an ad' => [route('analytics.admin.marketing.ads.show', $ad), ['an-ad-form']],
        ];

        foreach ($screens as $screen => [$url, $dialogs]) {
            $page = (string) $this->get($url)->assertSuccessful()->getContent();

            foreach ($dialogs as $dialog) {
                $this->assertMatchesRegularExpression(
                    '/role="dialog"[^>]*aria-labelledby="ui-modal-'.$dialog.'-title"/',
                    $page,
                    "{$screen} does not carry {$dialog} as the kit's dialog.",
                );
            }
        }
    }

    /** A window's confirming button is disabled for the length of its call · a double click saves once. */
    public function test_each_confirming_button_waits_for_its_own_call(): void
    {
        $campaign = $this->campaign('Été 2026');
        $ad = Ad::create(['campaign_id' => $campaign->id, 'name' => 'Cabriolet', 'match_conditions' => [['param' => 'creative', 'value' => 'cabrio']]]);

        $this->actingAs($this->admin, 'admin');

        $screens = [
            'the campaigns' => [route('analytics.admin.marketing.campaigns'), ['saveCampaign', 'deleteConfirmed']],
            'a campaign' => [route('analytics.admin.marketing.campaigns.show', $campaign), ['saveCampaign', 'deleteCampaignConfirmed', 'saveAd', 'deleteAdConfirmed']],
            'an ad' => [route('analytics.admin.marketing.ads.show', $ad), ['saveAd']],
        ];

        foreach ($screens as $screen => [$url, $calls]) {
            $page = (string) $this->get($url)->assertSuccessful()->getContent();

            foreach ($calls as $call) {
                $this->assertStringContainsString(
                    'wire:loading.attr="disabled" wire:target="'.$call.'"',
                    $page,
                    "On {$screen}, the button that calls {$call} stays clickable during the call.",
                );
            }
        }
    }

    /**
     * A form window is a real form its button submits · the kit brings the
     * cursor to the first refused field of a submitted form, and to none other.
     */
    public function test_each_form_window_is_a_form_its_button_submits(): void
    {
        $campaign = $this->campaign('Été 2026');
        $ad = Ad::create(['campaign_id' => $campaign->id, 'name' => 'Cabriolet', 'match_conditions' => [['param' => 'creative', 'value' => 'cabrio']]]);

        $this->actingAs($this->admin, 'admin');

        $screens = [
            'the campaigns' => [route('analytics.admin.marketing.campaigns'), ['an-campaign-form' => 'saveCampaign']],
            'a campaign' => [route('analytics.admin.marketing.campaigns.show', $campaign), ['an-campaign-form' => 'saveCampaign', 'an-ad-form' => 'saveAd']],
            'an ad' => [route('analytics.admin.marketing.ads.show', $ad), ['an-ad-form' => 'saveAd']],
        ];

        foreach ($screens as $screen => [$url, $forms]) {
            $page = (string) $this->get($url)->assertSuccessful()->getContent();

            foreach ($forms as $modal => $call) {
                $this->assertStringContainsString(
                    '<form id="'.$modal.'-fields" x-on:submit.prevent="$anCloseWhenDone($wire.'.$call.'(), \''.$modal.'\')"',
                    $page,
                    "On {$screen}, {$modal} is not a form that saves on submit.",
                );
                $this->assertMatchesRegularExpression(
                    '/<button(?=[^>]*\stype="submit")(?=[^>]*\sform="'.$modal.'-fields")[^>]*>/',
                    $page,
                    "On {$screen}, the button of {$modal} does not submit its form.",
                );
            }
        }
    }

    /** The last URL condition of a form cannot be removed · by the pointer or by the keyboard. */
    public function test_the_last_url_condition_cannot_be_removed(): void
    {
        $campaign = $this->campaign();

        $this->actingAs($this->admin, 'admin');

        $campaigns = Livewire::test(CampaignForm::class)->call('editCampaign');
        $this->assertSame([true], $this->removalsDisabled($campaigns->html(), 'removeCampaignCondition'));
        $this->assertSame([false, false], $this->removalsDisabled($campaigns->call('addCampaignCondition')->html(), 'removeCampaignCondition'));

        $ads = Livewire::test(AdForm::class, ['campaignId' => $campaign->id])->call('editAd');
        $this->assertSame([true], $this->removalsDisabled($ads->html(), 'removeAdCondition'));
        $this->assertSame([false, false], $this->removalsDisabled($ads->call('addAdCondition')->html(), 'removeAdCondition'));
    }

    /**
     * Whether each removal button of a form is disabled, in page order.
     *
     * @return list<bool>
     */
    private function removalsDisabled(string $html, string $method): array
    {
        preg_match_all('/<button[^>]*wire:click="'.$method.'\(\d+\)"[^>]*>/', $html, $buttons);

        return array_map(fn (string $button): bool => (bool) preg_match('/\sdisabled(\s|>|=)/', $button), $buttons[0]);
    }

    /** Both objective lists of the ad form close on the escape key, and say whether they are open. */
    public function test_the_objective_lists_close_on_escape_and_say_whether_they_are_open(): void
    {
        $campaign = $this->campaign();
        $ad = Ad::create(['campaign_id' => $campaign->id, 'name' => 'Cabriolet', 'match_conditions' => [['param' => 'creative', 'value' => 'cabrio']]]);

        $this->actingAs($this->admin, 'admin');

        $page = (string) $this->get(route('analytics.admin.marketing.ads.show', $ad))->assertSuccessful()->getContent();

        $this->assertSame(2, substr_count($page, 'x-data="anObjectivePicker"'), 'The ad form no longer draws its two lists.');
        $this->assertSame(2, substr_count($page, 'x-on:keydown.escape="closeOnEscape($event)"'));
        $this->assertSame(2, substr_count($page, 'x-bind:aria-expanded="open"'));
        $this->assertSame(2, substr_count($page, 'x-on:keydown.enter.prevent'), 'The Enter key in a list search would submit the whole ad form.');
    }

    public function test_it_creates_a_campaign_and_says_so(): void
    {
        $this->actingAs($this->admin, 'admin');

        Livewire::test(CampaignForm::class)
            ->call('editCampaign')
            ->assertReturned(true)
            ->set('campaignName', 'Hiver 2026')
            ->set('campaignPlatform', 'Google')
            ->set('campaignConditions.0.param', 'utm_campaign')
            ->set('campaignConditions.0.value', ' hiver ')
            ->call('saveCampaign')
            ->assertHasNoErrors()
            ->assertReturned(true)
            ->assertDispatched('an-campaigns-changed');

        $this->assertSame(
            [['param' => 'utm_campaign', 'value' => 'hiver']],
            Campaign::where('name', 'Hiver 2026')->first()?->match_conditions,
            'The value is saved trimmed.',
        );
    }

    public function test_it_edits_a_campaign_through_its_form(): void
    {
        $campaign = $this->campaign('Été', 'Meta', 'meta_ete');
        $this->actingAs($this->admin, 'admin');

        Livewire::test(CampaignForm::class)
            ->call('editCampaign', $campaign->id)
            ->assertReturned(true)
            ->assertSet('campaignId', $campaign->id)
            ->assertSet('campaignName', 'Été')
            ->assertSet('campaignConditions', [['param' => 'src', 'value' => 'meta_ete']])
            ->set('campaignName', 'Été 2027')
            ->call('addCampaignCondition')
            ->set('campaignConditions.1.param', 'creative')
            ->set('campaignConditions.1.value', 'cabrio')
            ->call('saveCampaign')
            ->assertHasNoErrors()
            ->assertReturned(true);

        $fresh = $campaign->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame('Été 2027', $fresh->name);
        $this->assertSame([
            ['param' => 'src', 'value' => 'meta_ete'],
            ['param' => 'creative', 'value' => 'cabrio'],
        ], $fresh->match_conditions);
    }

    public function test_it_creates_an_ad_with_its_objectives_in_one_save_and_says_so(): void
    {
        $campaign = $this->campaign('Été', null, 'meta_ete');
        $this->actingAs($this->admin, 'admin');

        Livewire::test(AdForm::class, ['campaignId' => $campaign->id])
            ->call('editAd')
            ->assertReturned(true)
            ->set('adName', 'Cabriolet')
            ->set('adConditions.0.param', 'creative')
            ->set('adConditions.0.value', 'cabrio')
            ->call('addObjective', 'funnel', 'concours', 'Concours')
            ->call('addObjective', 'event', 'Lead', 'Demande de code', 3.0)
            ->call('saveAd')
            ->assertHasNoErrors()
            ->assertReturned(true)
            ->assertDispatched('an-ads-changed');

        $ad = Ad::where('name', 'Cabriolet')->firstOrFail();

        $this->assertSame($campaign->id, $ad->campaign_id);
        $this->assertSame(2, AdObjective::where('ad_id', $ad->id)->count());
    }

    public function test_it_edits_an_ad_and_its_objectives_through_its_form(): void
    {
        $campaign = $this->campaign('Été', null, 'meta_ete');
        $ad = Ad::create(['campaign_id' => $campaign->id, 'name' => 'Cabrio', 'match_conditions' => [['param' => 'creative', 'value' => 'cabrio']]]);
        $this->actingAs($this->admin, 'admin');

        Livewire::test(AdForm::class, ['campaignId' => $campaign->id])
            ->call('editAd', $ad->id)
            ->assertReturned(true)
            ->assertSet('adId', $ad->id)
            ->set('adName', 'Cabriolet décapotable')
            ->call('addObjective', 'event', 'Lead', 'Lead', 2.0)
            ->call('saveAd')
            ->assertHasNoErrors()
            ->assertReturned(true);

        $fresh = $ad->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame('Cabriolet décapotable', $fresh->name);
        $this->assertTrue(AdObjective::where('ad_id', $ad->id)->where('reference', 'Lead')->exists());
    }

    public function test_it_excludes_already_selected_objectives_from_the_pickers(): void
    {
        // Two declared events, one of which will be picked: without the second,
        // an empty list would pass the test while proving nothing.
        $this->app->forgetInstance(EventRegistry::class);
        $registry = app(EventRegistry::class);
        $registry->register(new TrackedEvent('Lead', 'Demande de contact', 3));
        $registry->register(new TrackedEvent('Devis', 'Demande de devis', 5));

        $campaign = $this->campaign('Été', null, 'meta_ete');
        $this->actingAs($this->admin, 'admin');

        $component = Livewire::test(AdForm::class, ['campaignId' => $campaign->id])
            ->call('editAd')
            ->call('addObjective', 'event', 'Lead', 'Lead', 3.0);

        // `viewData()` and not `get()`: the options are view data, not a
        // property of the component. With `get()` this test read null, walked
        // an empty collection, and passed without checking anything.
        $options = $component->viewData('eventOptions');

        $this->assertIsArray($options);

        $offered = Collection::make($options)->pluck('reference')->all();

        $this->assertNotContains('Lead', $offered, 'the objective already picked must not be offered again');
        $this->assertContains('Devis', $offered, 'the others stay on offer');
    }

    public function test_it_deletes_a_campaign_and_cascades_to_its_ads_and_objectives(): void
    {
        $campaign = $this->campaign('Été', null, 'meta_ete');
        $ad = Ad::create(['campaign_id' => $campaign->id, 'name' => 'Cabrio', 'match_conditions' => [['param' => 'creative', 'value' => 'cabrio']]]);
        AdObjective::create(['ad_id' => $ad->id, 'type' => 'event', 'reference' => 'Lead']);

        $other = $this->campaign('Hiver', null, 'meta_hiver');

        $this->actingAs($this->admin, 'admin');

        Livewire::test(CampaignsPage::class)
            ->call('confirmDelete', $campaign->id)
            ->assertReturned(true)
            ->assertSet('deleteLabel', 'Été')
            ->call('deleteConfirmed')
            ->assertReturned(true);

        $this->assertFalse(Campaign::whereKey($campaign->id)->exists());
        $this->assertSame(0, Ad::count());
        $this->assertSame(0, AdObjective::count());
        $this->assertTrue(Campaign::whereKey($other->id)->exists());
    }

    public function test_it_deletes_an_ad_from_its_campaign_after_the_confirmation(): void
    {
        $campaign = $this->campaign('Été', null, 'meta_ete');
        $ad = Ad::create(['campaign_id' => $campaign->id, 'name' => 'Cabrio', 'match_conditions' => [['param' => 'creative', 'value' => 'cabrio']]]);
        $this->actingAs($this->admin, 'admin');

        Livewire::test(CampaignDetailPage::class, ['campaign' => $campaign])
            ->call('confirmDeleteAd', $ad->id)
            ->assertReturned(true)
            ->assertSet('deleteAdLabel', 'Cabrio')
            ->call('deleteAdConfirmed')
            ->assertReturned(true);

        $this->assertFalse(Ad::whereKey($ad->id)->exists());
    }

    public function test_it_rejects_forged_objective_types_before_they_reach_the_database(): void
    {
        $campaign = $this->campaign('Ete', null, 'meta_ete');
        $this->actingAs($this->admin, 'admin');

        Livewire::test(AdForm::class, ['campaignId' => $campaign->id])
            ->call('editAd')
            ->set('adName', 'Cabriolet')
            ->set('adConditions.0.param', 'creative')
            ->set('adConditions.0.value', 'cabrio')
            ->call('addObjective', 'forged-type', 'whatever', 'Whatever')
            ->call('saveAd')
            ->assertHasErrors(['objectives.0.type']);

        $this->assertSame(0, Ad::query()->count());
        $this->assertSame(0, AdObjective::query()->count());
    }

    public function test_it_displays_the_condition_validation_messages_as_text_not_only_a_red_border(): void
    {
        $campaign = $this->campaign('Ete', null, 'meta_ete');
        $this->actingAs($this->admin, 'admin');

        Livewire::test(AdForm::class, ['campaignId' => $campaign->id])
            ->call('editAd')
            ->set('adName', 'Cabriolet')
            ->set('adConditions.0.param', '')
            ->set('adConditions.0.value', '')
            ->call('saveAd')
            ->assertHasErrors(['adConditions.0.param', 'adConditions.0.value'])
            // Not a yes, so the modal stays in front of whoever fills it.
            ->assertReturned(fn (mixed $answer): bool => $answer !== true)
            ->assertSeeText(__('Le paramètre est obligatoire.'));

        Livewire::test(CampaignForm::class)
            ->call('editCampaign')
            ->set('campaignName', 'Hiver')
            ->set('campaignConditions.0.param', '')
            ->set('campaignConditions.0.value', 'x')
            ->call('saveCampaign')
            ->assertHasErrors(['campaignConditions.0.param'])
            ->assertReturned(fn (mixed $answer): bool => $answer !== true)
            ->assertSeeText(__('Le paramètre est obligatoire.'));
    }

    public function test_it_toasts_instead_of_crashing_when_editing_a_record_that_no_longer_exists(): void
    {
        $campaign = $this->campaign('Ete', null, 'meta_ete');
        $this->actingAs($this->admin, 'admin');

        // Strictly false: `assertReturned(false)` would also take a null, which
        // is what a method that answers nothing leaves behind.
        Livewire::test(CampaignForm::class)
            ->call('editCampaign', 999_999)
            ->assertDispatched('ui-toast', type: 'danger')
            ->assertReturned(fn (mixed $answer): bool => $answer === false);

        Livewire::test(AdForm::class, ['campaignId' => $campaign->id])
            ->call('editAd', 999_999)
            ->assertDispatched('ui-toast', type: 'danger')
            ->assertReturned(fn (mixed $answer): bool => $answer === false);

        Livewire::test(CampaignsPage::class)
            ->call('confirmDelete', 999_999)
            ->assertDispatched('ui-toast', type: 'danger')
            ->assertReturned(fn (mixed $answer): bool => $answer === false);

        Livewire::test(CampaignDetailPage::class, ['campaign' => $campaign])
            ->call('confirmDeleteAd', 999_999)
            ->assertDispatched('ui-toast', type: 'danger')
            ->assertReturned(fn (mixed $answer): bool => $answer === false);
    }

    /** The ad form edits the ads of the campaign it is laid for, and of no other. */
    public function test_the_ad_form_opens_no_ad_of_another_campaign(): void
    {
        $campaign = $this->campaign('Été', null, 'meta_ete');
        $other = $this->campaign('Hiver', null, 'meta_hiver');
        $theirs = Ad::create(['campaign_id' => $other->id, 'name' => 'Luge', 'match_conditions' => [['param' => 'creative', 'value' => 'luge']]]);
        $this->actingAs($this->admin, 'admin');

        Livewire::test(AdForm::class, ['campaignId' => $campaign->id])
            ->call('editAd', $theirs->id)
            ->assertReturned(fn (mixed $answer): bool => $answer === false)
            ->assertSet('adId', null)
            ->assertSet('adName', '');
    }

    /**
     * What a form saves under is decided by the server · the browser can
     * change neither the record being edited nor the campaign an ad goes to.
     *
     * @return array<string, array{class-string, string}>
     */
    public static function lockedNumbers(): array
    {
        return [
            'the campaign being edited' => [CampaignForm::class, 'campaignId'],
            'the ad being edited' => [AdForm::class, 'adId'],
            'the campaign an ad goes to' => [AdForm::class, 'campaignId'],
        ];
    }

    /** @param  class-string  $form */
    #[DataProvider('lockedNumbers')]
    public function test_a_form_keeps_what_it_saves_under_locked(string $form, string $property): void
    {
        $campaign = $this->campaign();
        $this->actingAs($this->admin, 'admin');

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test($form, $form === AdForm::class ? ['campaignId' => $campaign->id] : [])->set($property, $campaign->id + 1);
    }

    /**
     * Each screen listens for what its forms announce, and reads itself again
     * then · a screen that does not listen is never drawn again in the
     * browser, whatever the test harness redraws.
     */
    public function test_the_screens_read_themselves_again_when_a_form_writes(): void
    {
        $campaign = $this->campaign('Été', null, 'meta_ete');
        $ad = Ad::create(['campaign_id' => $campaign->id, 'name' => 'Cabrio', 'match_conditions' => [['param' => 'creative', 'value' => 'cabrio']]]);
        $this->actingAs($this->admin, 'admin');

        $list = Livewire::test(CampaignsPage::class)->assertDontSeeText('Automne');
        $detail = Livewire::test(CampaignDetailPage::class, ['campaign' => $campaign])->assertDontSeeText('Été 2027');
        $adPage = Livewire::test(AdDetailPage::class, ['ad' => $ad])->assertDontSeeText('Cabriolet');

        $this->assertSame(['an-campaigns-changed'], $this->listenersOf($list->html()));
        $this->assertEqualsCanonicalizing(['an-campaign-metrics-loaded', 'an-campaigns-changed', 'an-ads-changed'], $this->listenersOf($detail->html()));
        $this->assertSame(['an-ads-changed'], $this->listenersOf($adPage->html()));

        $this->campaign('Automne');
        $campaign->update(['name' => 'Été 2027']);
        Ad::create(['campaign_id' => $campaign->id, 'name' => 'Berline', 'match_conditions' => [['param' => 'creative', 'value' => 'berline']]]);
        $ad->update(['name' => 'Cabriolet']);

        $list->dispatch('an-campaigns-changed')->assertSeeText('Automne');
        $detail->dispatch('an-campaigns-changed')->assertSeeText('Été 2027');
        $detail->dispatch('an-ads-changed')->assertSeeText('Berline');
        $adPage->dispatch('an-ads-changed')->assertSeeText('Cabriolet');
    }

    /**
     * The events a first render tells the browser to listen for.
     *
     * @return list<string>
     */
    private function listenersOf(string $html): array
    {
        $this->assertSame(1, preg_match('/wire:effects="([^"]*)"/', $html, $effects), 'The render carries no effects.');

        $decoded = json_decode(html_entity_decode($effects[1], ENT_QUOTES | ENT_HTML5), true);

        return is_array($decoded) && is_array($decoded['listeners'] ?? null) ? array_values($decoded['listeners']) : [];
    }
}
