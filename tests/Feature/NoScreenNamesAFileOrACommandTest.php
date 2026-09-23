<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Enums\GeoStatus;
use Falcon\Analytics\Livewire\Admin\Widgets\FunnelsContent;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/**
 * A screen is read by whoever runs the site, who can act on no file, command
 * or variable: it says what is missing and who to turn to.
 */
final class NoScreenNamesAFileOrACommandTest extends TestCase
{
    use RefreshDatabase;

    private const TECHNICAL = ['.php', '.env', 'analytics:', 'ANALYTICS_'];

    public function test_the_funnels_screen_without_a_funnel_says_who_to_ask(): void
    {
        $screen = Livewire::test(FunnelsContent::class, ['period' => 30])
            ->call('$refresh')
            ->assertSeeText(__('Aucun tunnel'))
            ->assertSeeText(__('Pour en suivre un, adressez-vous à la personne qui maintient le site.'));

        foreach (self::TECHNICAL as $name) {
            $screen->assertDontSeeText($name);
        }
    }

    public function test_the_locality_notice_names_nothing_technical(): void
    {
        $this->actingAs(TestAdmin::create([]), 'admin');

        $page = $this->get(route('analytics.admin.sessions'))->assertSuccessful();

        foreach (self::TECHNICAL as $name) {
            $page->assertDontSeeText($name);
        }
    }

    public function test_every_locality_notice_has_its_own_screen_sentence(): void
    {
        foreach ([GeoStatus::NoDatabase, GeoStatus::UnreadableDatabase, GeoStatus::PrivateAddress] as $status) {
            $notice = (string) $status->notice();

            $this->assertNotSame('', $notice, "{$status->name} has no screen sentence.");

            foreach (self::TECHNICAL as $name) {
                $this->assertStringNotContainsString($name, $notice, "{$status->name} names {$name} on screen.");
            }
        }

        $this->assertNull(GeoStatus::Ready->notice());
        $this->assertNull(GeoStatus::NotInDatabase->notice());
    }
}
