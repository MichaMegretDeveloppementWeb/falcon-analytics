<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Http\Middleware\ReadsTheSessionWithoutProlongingIt;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Orchestra\Testbench\Attributes\DefineEnvironment;

/**
 * The collector reads the host's session and writes it back only when a send
 * changed something in it · a heartbeat no longer keeps a signed-in user's
 * session alive, nor ages the host's flash data.
 *
 * On the file driver, forgotten between requests: the suite's array driver
 * keeps every session in memory, and would not see a write that was skipped.
 */
final class TheCollectorDoesNotProlongTheSessionTest extends TestCase
{
    use RefreshDatabase;

    private string $sessions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessions = sys_get_temp_dir().DIRECTORY_SEPARATOR.'falcon-analytics-sessions-'.getmypid();
        File::ensureDirectoryExists($this->sessions);
        File::cleanDirectory($this->sessions);

        config(['session.driver' => 'file', 'session.files' => $this->sessions]);

        Route::get('/_host_flash', function () {
            session()->flash('status', 'Vous êtes déconnecté.');

            return response()->noContent();
        })->middleware('web');

        Route::get('/_host_status', fn () => response((string) session('status')))->middleware('web');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sessions);

        parent::tearDown();
    }

    public function test_a_send_that_changes_nothing_writes_nothing_and_hands_no_cookie_back(): void
    {
        $id = $this->sessionOf($this->send());
        $file = $this->sessions.DIRECTORY_SEPARATOR.$id;
        touch($file, time() - 600);

        $response = $this->send($id);

        $this->assertNull($response->getCookie((string) config('session.cookie'), false), 'The session cookie was handed back.');
        clearstatcache();
        $this->assertLessThan(time() - 500, filemtime($file), 'The session was written back.');
    }

    /** The first send stores the visitor's identifier: that one is written, or every send would be a new visitor. */
    public function test_a_send_that_stores_an_identifier_writes_it(): void
    {
        $id = $this->sessionOf($this->send());

        $this->send($id);
        $this->send($id);

        $this->assertSame(1, Visitor::count());
        $this->assertSame(1, Session::count());
    }

    /** A send between a redirection and its page leaves the host's message where it was. */
    public function test_the_hosts_flash_message_survives_a_send(): void
    {
        $id = $this->sessionOf($this->forgetting()->get('/_host_flash'));

        $this->send($id);

        $this->forgetting()
            ->withCookie((string) config('session.cookie'), $id)
            ->get('/_host_status')
            ->assertSee('Vous êtes déconnecté.');
    }

    /** A session set to block is built with its cache factory, as Laravel builds StartSession. */
    public function test_a_session_set_to_block_still_answers(): void
    {
        config(['session.block' => true, 'session.block_store' => 'array']);

        $this->send()->assertNoContent();

        $this->assertSame(1, Event::count());
    }

    public function test_startsession_in_the_hosts_list_is_swapped(): void
    {
        $this->assertSwapped();
    }

    #[DefineEnvironment('withALeadingBackslash')]
    public function test_startsession_written_with_a_leading_backslash_is_swapped_too(): void
    {
        $this->assertSwapped();
    }

    protected function withALeadingBackslash(Application $app): void
    {
        $app['config']->set('analytics.web.middleware', [EncryptCookies::class, AddQueuedCookiesToResponse::class, '\\'.StartSession::class]);
    }

    private function assertSwapped(): void
    {
        $stack = Route::getRoutes()->getByName('analytics.web.ingest')?->gatherMiddleware() ?? [];

        $this->assertContains(ReadsTheSessionWithoutProlongingIt::class, $stack);
        $this->assertNotContains(StartSession::class, $stack);
        $this->assertNotContains('\\'.StartSession::class, $stack);
    }

    /**
     * A batch, the session cookie sent when there is one.
     *
     * @return TestResponse<Response>
     */
    private function send(?string $session = null): TestResponse
    {
        $request = $this->forgetting()->withoutDefer()->withCredentials()->withHeader('Origin', config('app.url'));

        if ($session !== null) {
            $request = $request->withCookie((string) config('session.cookie'), $session);
        }

        return $request->postJson('/__analytics', [
            'sent_at' => 1000,
            'events' => [['type' => 'pageview', 'ts' => 1000, 'route' => 'home', 'url' => 'https://boutique.test/']],
        ]);
    }

    /** Each request reads the session from its file, as two separate requests of a browser do. */
    private function forgetting(): static
    {
        $this->app->make('session')->forgetDrivers();

        return $this;
    }

    /** @param  TestResponse<Response>  $response */
    private function sessionOf(TestResponse $response): string
    {
        $cookie = $response->getCookie((string) config('session.cookie'));

        $this->assertNotNull($cookie, 'The session was not handed out.');

        return (string) $cookie->getValue();
    }
}
