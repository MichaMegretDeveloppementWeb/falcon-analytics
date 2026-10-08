<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Actions\ArchiveClosedDaysAction;
use Falcon\Analytics\Models\DailyCount;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\Fixtures\Models\TestClient;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * A session opened before its visitor signed in takes the subject of the first
 * send the host vouches for · and a session never changes the subject it has.
 */
final class ASessionTakesTheSubjectWhoSignsInTest extends TestCase
{
    use RefreshDatabase;

    /** The proof the host asked for: the session carries the subject, and the Sessions screen names it. */
    public function test_a_session_opened_signed_out_takes_the_subject_who_signs_in(): void
    {
        $cabinet = TestClient::create(['first_name' => 'Cabinet', 'last_name' => 'Rive']);

        $this->send([$this->pageview('/connexion')]);
        $this->assertNull(Session::query()->sole()->subject_type, 'The first page is sent by nobody.');

        $this->actingAs($cabinet, 'client');
        $this->send([$this->pageview('/patients')]);

        $session = Session::query()->sole();

        $this->assertSame('client', $session->subject_type);
        $this->assertSame($cabinet->id, $session->subject_id);
        $this->assertSame(2, Event::query()->where('session_id', $session->id)->count());

        $this->actingAs(TestAdmin::create([]), 'admin')
            ->get(route('analytics.admin.sessions'))
            ->assertSuccessful()
            ->assertSeeText('Cabinet Rive')
            ->assertDontSeeText(__('Non connecté'));
    }

    /** A session that has a subject keeps it: whoever signs in next opens a session of their own. */
    public function test_another_subject_opens_a_session_of_its_own(): void
    {
        $first = TestClient::create(['first_name' => 'Cabinet', 'last_name' => 'Rive']);
        $second = TestClient::create(['first_name' => 'Cabinet', 'last_name' => 'Lac']);

        $this->actingAs($first, 'client');
        $this->send([$this->pageview('/patients')]);

        $this->actingAs($second, 'client');
        $this->send([$this->pageview('/agenda')]);

        $this->assertSame(2, Session::count());
        $this->assertSame(
            [$first->id, $second->id],
            Session::query()->orderBy('id')->pluck('subject_id')->all(),
        );
    }

    /** An anonymous send vouches for nobody, so the session stays without a subject. */
    public function test_an_anonymous_send_names_nobody(): void
    {
        $this->send([$this->pageview('/')]);
        $this->send([$this->pageview('/tarifs')]);

        $session = Session::query()->sole();

        $this->assertNull($session->subject_type);
        $this->assertNull($session->subject_id);
    }

    /**
     * A shared browser · the profile is someone else's, so the send vouches for
     * nobody and the session is not named after the one who signed in on it.
     */
    public function test_a_shared_browser_never_names_the_session_after_someone_else(): void
    {
        $owner = TestClient::create(['first_name' => 'Cabinet', 'last_name' => 'Rive']);
        $guest = TestClient::create(['first_name' => 'Cabinet', 'last_name' => 'Lac']);
        $browser = (string) Str::uuid();

        $profile = Visitor::factory()->create(['uuid' => $browser, 'subject_type' => 'client', 'subject_id' => $owner->id]);
        $open = Session::factory()->for($profile)->create([
            'browser_key' => $browser,
            'started_at' => now()->subMinute(),
            'last_activity_at' => now()->subMinute(),
        ]);

        $this->actingAs($guest, 'client')->withSession(['fa_vid' => $browser]);
        $this->send([$this->pageview('/agenda')]);

        $this->assertNull($open->fresh()?->subject_type, 'The owner\'s session took the guest.');
    }

    /**
     * A session that takes its subject after its first day was summarised moves
     * that day's pages under the subject · the day is summarised again.
     */
    public function test_a_day_already_summarised_is_summarised_again(): void
    {
        config(['analytics.session.timeout_minutes' => 180]);
        $cabinet = TestClient::create(['first_name' => 'Cabinet', 'last_name' => 'Rive']);

        $this->travelTo(CarbonImmutable::parse('2026-06-14 23:30:00'));
        $this->send([$this->pageview('/connexion')]);

        $this->travelTo(CarbonImmutable::parse('2026-06-15 01:05:00'));
        $this->app->make(ArchiveClosedDaysAction::class)->execute();
        $this->assertSame([null], $this->subjectsOfThePagesOf('2026-06-14'));

        $this->actingAs($cabinet, 'client');
        $this->send([$this->pageview('/patients')]);

        $this->assertSame(['client'], $this->subjectsOfThePagesOf('2026-06-14'));
    }

    /** @return list<string|null> */
    private function subjectsOfThePagesOf(string $day): array
    {
        return array_values(DailyCount::query()
            ->where('day', $day)
            ->where('kind', 'page')
            ->pluck('subject_type')
            ->all());
    }

    /** @return array<string, mixed> */
    private function pageview(string $path): array
    {
        return ['type' => 'pageview', 'ts' => 1000, 'route' => 'page', 'url' => 'https://cabinet.test'.$path];
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return TestResponse<Response>
     */
    private function send(array $events): TestResponse
    {
        return $this->withoutDefer()
            ->withHeader('Origin', config('app.url'))
            ->postJson('/__analytics', ['sent_at' => 1000, 'events' => $events])
            ->assertNoContent();
    }
}
