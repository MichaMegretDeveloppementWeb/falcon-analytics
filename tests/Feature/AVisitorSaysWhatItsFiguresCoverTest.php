<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Visitor\VisitorDetail;
use Falcon\Analytics\Livewire\Admin\VisitorDetailPage;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/**
 * A visitor's page past the retention of sessions · its figures cover the
 * sessions kept, it says so, and « Récurrent » follows the sessions left.
 */
final class AVisitorSaysWhatItsFiguresCoverTest extends TestCase
{
    use RefreshDatabase;

    private const SAID = 'Sur ses sessions des 400 derniers jours';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
        config(['analytics.retention_days' => 30]);
    }

    public function test_the_page_says_how_far_back_its_figures_go(): void
    {
        config(['analytics.session_retention_days' => 400]);

        $this->open($this->aVisitor())->assertSee(self::SAID);
    }

    public function test_nothing_is_said_while_the_sessions_are_kept(): void
    {
        $this->open($this->aVisitor())->assertDontSee('derniers jours');
    }

    /** Settings the purge refuses erase nothing, so the page has nothing to warn about. */
    public function test_nothing_is_said_when_the_purge_refuses_the_settings(): void
    {
        config(['analytics.session_retention_days' => 20]);

        $this->open($this->aVisitor())->assertDontSee('derniers jours');
    }

    /** Its old session erased, the visitor has one left and is no longer returning. */
    public function test_returning_follows_the_sessions_left(): void
    {
        config(['analytics.session_retention_days' => 60]);

        $visitor = $this->aVisitor();
        $this->sessionOn($visitor, '2026-03-01 10:00:00');
        $this->sessionOn($visitor, '2026-06-10 10:00:00');

        $this->assertTrue($this->detailOf($visitor)->isReturning, 'Two sessions, before the purge.');

        $this->artisan('analytics:archive')->assertSuccessful();
        $this->artisan('analytics:prune')->assertSuccessful();

        $detail = $this->detailOf($visitor);
        $this->assertFalse($detail->isReturning);
        $this->assertSame(1, $detail->sessionCount);
    }

    private function aVisitor(): Visitor
    {
        return Visitor::factory()->create([
            'first_seen_at' => '2026-03-01 10:00:00',
            'last_seen_at' => '2026-06-10 10:00:00',
            'session_count' => 2,
        ]);
    }

    private function sessionOn(Visitor $visitor, string $at): Session
    {
        $start = CarbonImmutable::parse($at);

        return Session::factory()->for($visitor)->create(['started_at' => $start, 'last_activity_at' => $start->addMinutes(10)]);
    }

    /** @return TestResponse<Response> */
    private function open(Visitor $visitor): TestResponse
    {
        return $this->actingAs(TestAdmin::create(['email' => 'admin@example.test']), 'admin')
            ->get(route('analytics.admin.visitors.show', $visitor->id))
            ->assertSuccessful();
    }

    private function detailOf(Visitor $visitor): VisitorDetail
    {
        $this->actingAs(TestAdmin::query()->firstOrCreate(['email' => 'admin@example.test']), 'admin');

        $detail = Livewire::test(VisitorDetailPage::class, ['visitorId' => $visitor->id])->viewData('detail');
        $this->assertInstanceOf(VisitorDetail::class, $detail);

        return $detail;
    }
}
