<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Livewire\Admin\VisitorDetailPage;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/**
 * « Supprimer les données de ce visiteur » announces the sessions that go ·
 * those another person signed in on this browser left there move to that
 * person, and are not announced.
 */
final class TheErasureWindowSaysWhatGoesTest extends TestCase
{
    use RefreshDatabase;

    private const OWNER = ['type' => 'client', 'id' => 7];

    private const GUEST = ['type' => 'client', 'id' => 8];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-07-10 12:00:00'));
        $this->actingAs(TestAdmin::create(['email' => 'admin@example.test']), 'admin');
    }

    /** The proof · three sessions on the profile, one of them another person's · the window says two, and two go. */
    public function test_the_window_counts_only_the_sessions_that_go(): void
    {
        $profile = $this->profile(self::OWNER);
        $this->sessionOn($profile, self::OWNER);
        $this->sessionOn($profile, null);
        $guests = $this->sessionOn($profile, self::GUEST);

        Livewire::test(VisitorDetailPage::class, ['visitorId' => $profile->id])
            ->assertSee('le visiteur, ses 2 sessions et tous leurs événements seront définitivement supprimés.')
            ->call('forget');

        $this->assertSame([$guests->id], Session::query()->pluck('id')->all(), 'The two announced went, the guest\'s stayed.');
    }

    /** An anonymous profile · every session another person signed in on moves to them. */
    public function test_on_an_anonymous_profile_only_the_anonymous_sessions_are_announced(): void
    {
        $profile = $this->profile(null);
        $this->sessionOn($profile, null);
        $this->sessionOn($profile, self::GUEST);

        Livewire::test(VisitorDetailPage::class, ['visitorId' => $profile->id])
            ->assertSee('le visiteur, sa session et tous ses événements seront définitivement supprimés.');
    }

    /** Nothing of theirs left · the window says only the visitor goes. */
    public function test_a_profile_holding_only_another_persons_sessions_announces_the_visitor_alone(): void
    {
        $profile = $this->profile(self::OWNER);
        $this->sessionOn($profile, self::GUEST);

        Livewire::test(VisitorDetailPage::class, ['visitorId' => $profile->id])
            ->assertSee("Cette action est irréversible\u{00A0}: le visiteur sera définitivement supprimé.")
            ->assertDontSee('sa session');
    }

    /**
     * @param  array{type: string, id: int}|null  $subject
     */
    private function profile(?array $subject): Visitor
    {
        return Visitor::factory()->create([
            'first_seen_at' => CarbonImmutable::now()->subDays(9),
            'last_seen_at' => CarbonImmutable::now()->subDay(),
            'subject_type' => $subject['type'] ?? null,
            'subject_id' => $subject['id'] ?? null,
        ]);
    }

    /**
     * @param  array{type: string, id: int}|null  $subject
     */
    private function sessionOn(Visitor $profile, ?array $subject): Session
    {
        $profile->increment('session_count');

        return Session::factory()->for($profile)->at(CarbonImmutable::now()->subDays(2))->create([
            'subject_type' => $subject['type'] ?? null,
            'subject_id' => $subject['id'] ?? null,
        ]);
    }
}
