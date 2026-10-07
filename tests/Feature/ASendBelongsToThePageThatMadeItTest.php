<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\DTOs\PageContext;
use Falcon\Analytics\Facades\Analytics;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Repositories\SessionWriteRepository;
use Falcon\Analytics\Services\PageContextSealer;
use Falcon\Analytics\Tests\Fixtures\Models\TestClient;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * What a page sends once its user has signed out · a click sent on the way
 * out, the heartbeat of a tab left open · joins the session of the one the page
 * was drawn for, within the grace, and creates nothing.
 *
 * The first tests of the package to send a batch while someone is signed in.
 */
final class ASendBelongsToThePageThatMadeItTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_click_sent_after_signing_out_joins_the_session_of_the_one_who_made_it(): void
    {
        $cabinet = TestClient::create(['first_name' => 'Cabinet']);
        $context = $this->signedInPageOf($cabinet);

        $this->signOut();
        $this->send($this->clicks(2), $context)->assertNoContent();

        $this->assertSame(1, Visitor::count(), 'A visitor of nobody was created.');
        $this->assertSame(1, Session::count(), 'A session of nobody was created.');
        $this->assertSame(0, Session::query()->whereNull('subject_id')->count());

        $session = Session::query()->sole();

        $this->assertSame($cabinet->id, $session->subject_id);
        $this->assertSame(3, Event::query()->where('session_id', $session->id)->count(), 'The page view and the two last clicks.');
        $this->assertSame(2, $session->fresh()?->click_count);
        $this->assertSame(2, Event::query()->where('type', 'click')->where('subject_id', $cabinet->id)->count());
    }

    /** A context nobody can read drops the batch, and no identifier is handed out for it. */
    public function test_a_context_read_nowhere_drops_the_batch_and_hands_out_nothing(): void
    {
        $cabinet = TestClient::create(['first_name' => 'Cabinet']);
        $sealed = $this->sealFor($cabinet, (string) Str::uuid());

        $forged = [
            'altered by one character' => substr($sealed, 0, 40).($sealed[40] === 'A' ? 'B' : 'A').substr($sealed, 41),
            'sealed with another key' => (new Encrypter(random_bytes(32), 'aes-256-cbc'))->encryptString((string) json_encode(['v' => 1, 'p' => 'fa-ctx', 't' => 'client', 'i' => $cabinet->id, 'k' => (string) Str::uuid()])),
            'not a string' => ['client', $cabinet->id],
        ];

        foreach ($forged as $case => $context) {
            $this->send($this->clicks(1), $context)->assertNoContent();

            $this->assertSame(0, Visitor::count(), $case);
            $this->assertSame(0, Event::count(), $case);
            $this->assertNull(session('fa_vid'), "{$case} · an identifier was handed out.");
        }
    }

    /** One address may send 120 batches a minute: a refused context is said once, not 120 times. */
    public function test_a_refused_context_is_said_once(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('warning')->once();

        foreach (range(1, 3) as $attempt) {
            $this->send($this->clicks(1), 'pas-un-contexte')->assertNoContent();
        }
    }

    public function test_a_leftover_is_attached_within_the_grace_and_dropped_past_it(): void
    {
        $cabinet = TestClient::create(['first_name' => 'Cabinet']);
        $context = $this->signedInPageOf($cabinet);
        $this->signOut();

        $this->travel(59)->seconds();
        $this->send($this->clicks(1), $context);
        $this->assertSame(1, Event::query()->where('type', 'click')->count(), 'Within the grace, the click joins the session.');

        $this->travel(2)->seconds();
        $this->send($this->clicks(1), $context);
        $this->assertSame(1, Event::query()->where('type', 'click')->count(), 'Past the grace, the click is dropped.');
        $this->assertSame(1, Visitor::count());
        $this->assertSame(1, Session::count());
    }

    /** A leftover is never a vouching: a tab left open stops once the grace has run from the last real one. */
    public function test_a_leftover_never_stretches_the_grace(): void
    {
        $cabinet = TestClient::create(['first_name' => 'Cabinet']);
        $context = $this->signedInPageOf($cabinet);
        $this->signOut();

        $this->travel(30)->seconds();
        $this->send($this->clicks(1), $context);

        $this->travel(31)->seconds();
        $this->send($this->clicks(1), $context);

        $this->assertSame(1, Event::query()->where('type', 'click')->count());
    }

    /** A host that slows the collector down keeps the sends of its sign-outs. */
    public function test_the_grace_is_never_shorter_than_a_heartbeat_and_a_flush(): void
    {
        config(['analytics.session.heartbeat_seconds' => 120, 'analytics.session.flush_seconds' => 5]);

        $cabinet = TestClient::create(['first_name' => 'Cabinet']);
        $context = $this->signedInPageOf($cabinet);
        $this->signOut();

        $this->travel(110)->seconds();
        $this->send($this->clicks(1), $context);

        $this->assertSame(1, Event::query()->where('type', 'click')->count());
    }

    /** Heartbeats alone would only stretch the session of someone who has left. */
    public function test_heartbeats_alone_after_signing_out_are_ignored(): void
    {
        $cabinet = TestClient::create(['first_name' => 'Cabinet']);
        $context = $this->signedInPageOf($cabinet);
        $this->signOut();
        $before = Session::query()->sole()->last_activity_at;

        $this->travel(20)->seconds();
        $this->send([['type' => 'heartbeat', 'ts' => 1000]], $context);

        $this->assertEquals($before, Session::query()->sole()->last_activity_at);
    }

    public function test_a_session_already_closed_takes_nothing(): void
    {
        $cabinet = TestClient::create(['first_name' => 'Cabinet']);
        $context = $this->signedInPageOf($cabinet);
        $this->signOut();
        Session::query()->update(['ended_at' => now()]);

        $this->send($this->clicks(1), $context);

        $this->assertSame(0, Event::query()->where('type', 'click')->count());
        $this->assertSame(1, Session::count());
    }

    /** A signs out, B signs in on the same browser: what A's page still sends never reaches B. */
    public function test_what_a_page_sends_for_one_never_reaches_the_next(): void
    {
        $first = TestClient::create(['first_name' => 'Premier']);
        $second = TestClient::create(['first_name' => 'Second']);
        $context = $this->signedInPageOf($first);
        $this->signOut();

        $this->actingAs($second, 'client');
        $this->send($this->clicks(1));
        $secondSession = Session::query()->where('subject_id', $second->id)->sole();

        $this->send($this->clicks(2), $context);

        $this->assertSame(1, Event::query()->where('session_id', $secondSession->id)->where('type', 'click')->count(), 'The second got the first one\'s clicks.');
        $this->assertSame(2, Event::query()->where('subject_id', $first->id)->where('type', 'click')->count(), 'Within the grace, they go to the first.');

        $this->travel(61)->seconds();
        $this->send($this->clicks(1), $context);

        $this->assertSame(1, Event::query()->where('session_id', $secondSession->id)->where('type', 'click')->count());
        $this->assertSame(2, Event::query()->where('subject_id', $first->id)->where('type', 'click')->count(), 'Past the grace, they are dropped.');
    }

    /**
     * A page drawn before signing in again carries the identifier of the
     * session before · while the host's session vouches for the same person,
     * the batch follows the live identifier, and no second session opens.
     */
    public function test_a_page_of_someone_still_signed_in_follows_the_live_identifier(): void
    {
        $cabinet = TestClient::create(['first_name' => 'Cabinet']);
        $staleContext = $this->signedInPageOf($cabinet);
        $this->signOut();

        $this->actingAs($cabinet, 'client');
        $this->send($this->clicks(1));
        $live = (string) session('fa_vid');

        $this->send($this->clicks(1), $staleContext);

        $this->assertSame(2, Event::query()->where('type', 'click')->whereIn('session_id', Session::query()->where('browser_key', $live)->select('id'))->count());
    }

    /** The host's session vouches only for its own subject's profile · a shared device never vouches for another's. */
    public function test_only_the_subjects_own_profile_is_vouched_for(): void
    {
        $owner = TestClient::create(['first_name' => 'Titulaire']);
        $guest = TestClient::create(['first_name' => 'Invité']);
        $context = $this->signedInPageOf($owner);
        $vouched = Session::query()->sole()->subject_confirmed_at;

        // The guest signs in on the owner's browser, keeping its identifier, and has no profile of their own.
        $this->app->make('auth')->forgetGuards();
        $this->actingAs($guest, 'client');
        $this->travel(30)->seconds();
        $this->send($this->clicks(1));

        $this->assertEquals($vouched, Session::query()->where('subject_id', $owner->id)->sole()->subject_confirmed_at);

        $this->signOut();
        $this->travel(31)->seconds();
        $this->send($this->clicks(1), $context);

        $this->assertSame(0, Event::query()->where('subject_id', $owner->id)->where('type', 'click')->count(), 'The guest\'s send stretched the owner\'s grace.');
    }

    public function test_a_server_event_vouches_for_its_subject(): void
    {
        Route::get('/_analytics_vouch_test', function () {
            Analytics::record('Lead');

            return response()->noContent();
        })->middleware('web');

        $this->actingAs(TestClient::create(['first_name' => 'Cabinet']), 'client');
        $this->withoutDefer()->get('/_analytics_vouch_test')->assertNoContent();

        $this->assertNotNull(Session::query()->sole()->subject_confirmed_at);
    }

    /** A batch that commits after a later one never moves the vouching back. */
    public function test_a_late_send_never_moves_the_vouching_back(): void
    {
        $cabinet = TestClient::create(['first_name' => 'Cabinet']);
        $this->signedInPageOf($cabinet);
        $earlier = now()->toImmutable();

        $this->travel(30)->seconds();
        $this->send($this->clicks(1));
        $latest = Session::query()->sole()->subject_confirmed_at;

        $this->app->make(SessionWriteRepository::class)->recordActivity(Session::query()->sole(), $earlier, 0, 0, 0, null, $earlier);

        $this->assertEquals($latest, Session::query()->sole()->subject_confirmed_at);
        $this->assertTrue($latest?->greaterThan($earlier->startOfSecond()));
    }

    public function test_an_anonymous_send_never_vouches_for_anyone(): void
    {
        $this->send($this->clicks(1))->assertNoContent();

        $this->assertNull(Session::query()->sole()->subject_confirmed_at);
    }

    /** A profile folded into another between the reading and the lock takes nothing. */
    public function test_a_profile_folded_meanwhile_takes_nothing(): void
    {
        $cabinet = TestClient::create(['first_name' => 'Cabinet']);
        $context = $this->signedInPageOf($cabinet);
        $this->signOut();
        $profile = Visitor::query()->sole();
        $elsewhere = Visitor::query()->create(['uuid' => (string) Str::uuid(), 'first_seen_at' => now(), 'last_seen_at' => now(), 'session_count' => 0]);

        DB::connection()->beforeExecuting(function (string $query) use ($profile, $elsewhere): void {
            if (str_contains($query, 'for update') && str_contains($query, 'falcon_analytics_visitors')) {
                DB::table('falcon_analytics_visitors')->where('id', $profile->id)->update(['merged_into_id' => $elsewhere->id]);
            }
        });

        $this->send($this->clicks(1), $context);

        $this->assertSame(0, Event::query()->where('type', 'click')->count());
    }

    /**
     * A signed-in page, sent once while its user is still there: what its
     * context says, and the one vouched-for send it leans on.
     */
    private function signedInPageOf(TestClient $client): string
    {
        $this->actingAs($client, 'client');
        $this->send([['type' => 'pageview', 'ts' => 1000, 'route' => 'patients.show', 'url' => 'https://cabinet.test/patients/170']])->assertNoContent();

        return $this->sealFor($client, (string) session('fa_vid'));
    }

    private function sealFor(TestClient $client, string $browserKey): string
    {
        return $this->app->make(PageContextSealer::class)->seal(new PageContext('client', $client->id, $browserKey));
    }

    /**
     * What the host does on sign-out: every guard out, the session emptied ·
     * the guards forgotten rather than logged out, the fixture table keeping no
     * remember token for Laravel to rewrite.
     */
    private function signOut(): void
    {
        $this->app->make('auth')->forgetGuards();
        $this->flushSession();
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return TestResponse<Response>
     */
    private function send(array $events, mixed $context = null): TestResponse
    {
        $batch = ['sent_at' => 1000, 'events' => $events];

        if ($context !== null) {
            $batch['context'] = $context;
        }

        return $this->withoutDefer()
            ->withHeader('Origin', config('app.url'))
            ->postJson('/__analytics', $batch);
    }

    /** @return list<array<string, mixed>> */
    private function clicks(int $count): array
    {
        return array_map(
            fn (int $i): array => ['type' => 'click', 'ts' => 1000, 'url' => 'https://cabinet.test/patients/170', 'selector' => "button#menu-{$i}", 'text' => 'Menu du compte'],
            range(1, $count),
        );
    }
}
