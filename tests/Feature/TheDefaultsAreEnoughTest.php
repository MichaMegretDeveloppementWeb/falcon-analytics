<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

/**
 * The package works without touching a single one of its settings.
 *
 * **The documentation says so in three places**, and it is the kind of promise
 * that rots quietly: a default that stops being viable does not fail anywhere —
 * it fails at the one install nobody here ever runs, the one that configured
 * nothing.
 *
 * **What this undoes is the rest of the suite's own convenience.** Every other
 * test runs against a bench that names guards, subjects and an admin
 * middleware, because that is what a real host does and what those tests are
 * about. Here all of it goes back to the shipped file, verbatim.
 *
 * **Authentication is not part of what goes back.** A guard and a user model
 * belong to the host and exist in every Laravel application; `analytics.*` is
 * what this claims nothing needs to be written into. Restoring the shipped
 * `admin.middleware` — `['web', 'auth']` — and leaving the `web` guard pointing
 * at a model that does not exist would test testbench, not the defaults.
 *
 * @see AnOldPublishedConfigStillWorksTest
 *      for the other half: a copy published long ago, and the keys added since.
 */
final class TheDefaultsAreEnoughTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        tap($app['config'], function ($config): void {
            /*
             * The shipped file, whole, over whatever the bench had set. It is
             * loaded rather than listed: a list here would be a second copy of
             * the defaults, and the day the two disagreed this test would be
             * guarding the copy.
             *
             * `completeConfigFrom` runs later, in register(). It only fills in
             * what is missing, so nothing here is overwritten.
             */
            $config->set('analytics', require dirname(__DIR__, 2).'/config/analytics.php');

            // The host's own authentication, which the defaults expect to find
            // and which is no part of this package's configuration.
            $config->set('auth.providers.users', ['driver' => 'eloquent', 'model' => TestAdmin::class]);
        });
    }

    public function test_a_screen_answers_in_the_package_shell(): void
    {
        $this->actingAs(TestAdmin::create([]));

        $this->get(route('analytics.admin.overview'))
            ->assertSuccessful()
            // The shipped `admin.layout` is null, so the package draws its own
            // shell — the whole point of shipping one.
            ->assertSee('data-ui-scope="analytics"', false)
            ->assertSee('vendor/falcon/analytics/analytics.css', false);
    }

    /**
     * A visit is measured, end to end, with nothing configured.
     *
     * The endpoint address, the same-origin rule, the throttle and the whole
     * identity block are the shipped ones. **This is the assertion that would
     * have caught a default nobody could live with** — an endpoint colliding
     * with a common route, a throttle set too low to let a page through.
     */
    public function test_a_visit_is_measured(): void
    {
        $this->withoutDefer()
            ->withHeader('Origin', config('app.url'))
            ->postJson('/__analytics', [
                'sent_at' => 1000,
                'events' => [['type' => 'pageview', 'ts' => 1000, 'route' => 'home', 'url' => 'https://exemple.fr/']],
            ])
            ->assertNoContent();

        $this->assertSame(1, Visitor::count());
        $this->assertSame(1, Session::count());
        $this->assertSame(1, Event::count());
    }

    /**
     * And the visitor stays session-scoped, because nobody named a consent
     * cookie.
     *
     * **The default is the discreet one**, and it is the default precisely so
     * that an installation which configures nothing does not start handing out
     * persistent identifiers. A change that flipped this would be a privacy
     * regression that no screen would show.
     */
    public function test_no_persistent_identifier_is_handed_out(): void
    {
        $this->assertNull(config('analytics.identity.consent_cookie'));

        $response = $this->withoutDefer()
            ->withHeader('Origin', config('app.url'))
            ->postJson('/__analytics', [
                'sent_at' => 1000,
                'events' => [['type' => 'pageview', 'ts' => 1000, 'route' => 'home', 'url' => 'https://exemple.fr/']],
            ]);

        $response->assertNoContent();
        $this->assertNull($response->getCookie('fa_vid', false), 'A cookie would outlive the session.');
    }

    /**
     * The collector goes on a page, with nothing configured.
     *
     * The directive is the one thing a host writes by hand, so it is the one
     * whose silence costs the most: an unknown directive prints as plain text
     * and nothing measures anything.
     */
    public function test_the_collector_lands_on_a_public_page(): void
    {
        // A real route, because the kit lays the file on a RESPONSE. Rendered
        // as a bare string, the declaration goes into the reserved stack and
        // nothing renders it — which is correct, and says nothing about a page.
        Route::get('/une-page-publique', fn (): string => Blade::render(
            '<!DOCTYPE html><html><head><title>t</title></head><body>@analyticsCollector</body></html>'
        ));

        $html = (string) $this->get('/une-page-publique')->assertSuccessful()->getContent();

        $this->assertStringContainsString('window.__falconAnalytics=', $html);
        $this->assertStringContainsString('analytics/analytics.js', $html);
    }
}
