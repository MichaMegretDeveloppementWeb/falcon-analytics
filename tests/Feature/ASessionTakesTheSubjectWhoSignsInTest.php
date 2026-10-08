<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Actions\ArchiveClosedDaysAction;
use Falcon\Analytics\Facades\Analytics;
use Falcon\Analytics\Models\DailyCount;
use Falcon\Analytics\Models\DailySessionTotal;
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
            ->assertDontSeeText('· '.__('Non connecté'));
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
     * A shared browser · the profile is someone else's, and the one who signs
     * in has none of their own yet · the open session takes their name all the
     * same, and vouches for nobody · the vouching stays the profile owner's.
     */
    public function test_a_shared_browser_names_the_open_session_after_who_signs_in(): void
    {
        $guest = TestClient::create(['first_name' => 'Cabinet', 'last_name' => 'Lac']);
        $open = $this->anOpenSessionOnSomeoneElsesBrowser($guest, CarbonImmutable::now()->subMinute());

        $this->send([$this->pageview('/agenda')]);

        $open->refresh();

        $this->assertSame(['client', $guest->id], [$open->subject_type, $open->subject_id], 'The session stayed « Non connecté » after the guest signed in.');
        $this->assertNull($open->subject_confirmed_at, 'The guest vouched for the owner\'s browser.');
        $this->assertSame(1, Session::count(), 'The guest\'s send opened a session of its own.');
    }

    /** The same on a shared browser · the day the session began, already summarised, is summarised again under the one who signed in. */
    public function test_a_day_already_summarised_is_summarised_again_on_a_shared_browser(): void
    {
        config(['analytics.session.timeout_minutes' => 180]);
        $this->travelTo(CarbonImmutable::parse('2026-06-15 01:05:00'));
        $guest = TestClient::create(['first_name' => 'Cabinet', 'last_name' => 'Lac']);
        $this->anOpenSessionOnSomeoneElsesBrowser($guest, CarbonImmutable::parse('2026-06-14 23:30:00'));
        $this->app->make(ArchiveClosedDaysAction::class)->execute();
        $this->assertSame([null], $this->subjectsOfTheSessionTotalsOf('2026-06-14'));

        $this->send([$this->pageview('/patients')]);

        $this->assertSame(['client'], $this->subjectsOfTheSessionTotalsOf('2026-06-14'));
    }

    /** The session named after the guest goes with the guest, and the owner's profile counts what it still holds. */
    public function test_forgetting_the_guest_takes_the_session_they_signed_in_on(): void
    {
        $guest = TestClient::create(['first_name' => 'Cabinet', 'last_name' => 'Lac']);
        $open = $this->anOpenSessionOnSomeoneElsesBrowser($guest, CarbonImmutable::now()->subMinute());
        $this->send([$this->pageview('/agenda')]);

        Analytics::forgetSubject('client', $guest->id);

        $this->assertFalse(Session::query()->whereKey($open->id)->exists());
        $this->assertSame(0, Event::query()->where('session_id', $open->id)->count(), 'The page seen before signing in goes too.');
        $this->assertSame(0, Visitor::query()->where('uuid', $open->browser_key)->sole()->session_count);
    }

    /**
     * A session that takes its subject after its first day was summarised moves
     * that day's pages and session totals under the subject · the day is
     * summarised again.
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
        $this->assertSame([null], $this->subjectsOfTheSessionTotalsOf('2026-06-14'));

        $this->actingAs($cabinet, 'client');
        $this->send([$this->pageview('/patients')]);

        $this->assertSame(['client'], $this->subjectsOfThePagesOf('2026-06-14'));
        $this->assertSame(['client'], $this->subjectsOfTheSessionTotalsOf('2026-06-14'));
    }

    /**
     * A browser whose profile is the owner's, with a session open on it since
     * a given moment and a page seen there signed out · then the guest signs
     * in on it.
     */
    private function anOpenSessionOnSomeoneElsesBrowser(TestClient $guest, CarbonImmutable $since): Session
    {
        $owner = TestClient::create(['first_name' => 'Cabinet', 'last_name' => 'Rive']);
        $browser = (string) Str::uuid();

        $profile = Visitor::factory()->create(['uuid' => $browser, 'subject_type' => 'client', 'subject_id' => $owner->id, 'session_count' => 1]);
        $open = Session::factory()->for($profile)->create([
            'browser_key' => $browser,
            'started_at' => $since,
            'last_activity_at' => $since,
            'pageview_count' => 1,
        ]);
        Event::factory()->for($open)->create(['occurred_at' => $since, 'url' => 'https://cabinet.test/connexion', 'page' => '/connexion']);

        $this->actingAs($guest, 'client')->withSession(['fa_vid' => $browser]);

        return $open;
    }

    /** @return list<string|null> */
    private function subjectsOfTheSessionTotalsOf(string $day): array
    {
        return array_values(DailySessionTotal::query()
            ->where('day', $day)
            ->where('dimension', DailySessionTotal::DIMENSION_ALL)
            ->pluck('subject_type')
            ->all());
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
