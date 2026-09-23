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
 * A default that stops being viable fails only at an install that configures
 * nothing, so the whole `analytics` configuration goes back to the shipped file
 * here. Authentication stays the host's · a guard and a user model exist in
 * every Laravel application.
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
            // The shipped file itself: a list here would be a second copy of the defaults.
            $config->set('analytics', require dirname(__DIR__, 2).'/config/analytics.php');

            // The host's own authentication, which the defaults expect to find.
            $config->set('auth.providers.users', ['driver' => 'eloquent', 'model' => TestAdmin::class]);
        });
    }

    public function test_a_screen_answers_in_the_package_shell(): void
    {
        $this->actingAs(TestAdmin::create([]));

        $this->get(route('analytics.admin.overview'))
            ->assertSuccessful()
            // The shipped `layouts.admin` is null, so the package draws its own shell.
            ->assertSee('data-ui-scope="analytics"', false)
            ->assertSee('vendor/falcon/analytics/analytics.css', false);
    }

    /**
     * The endpoint address, the same-origin rule, the throttle and the identity
     * block are the shipped ones, so an endpoint colliding with a common route
     * or a throttle too low to let a page through fails here.
     */
    public function test_a_visit_is_measured(): void
    {
        $this->withoutDefer()
            ->withHeader('Origin', config('app.url'))
            ->postJson('/__analytics', [
                'sent_at' => 1000,
                'events' => [['type' => 'pageview', 'ts' => 1000, 'route' => 'home', 'url' => 'https://exemple.test/']],
            ])
            ->assertNoContent();

        $this->assertSame(1, Visitor::count());
        $this->assertSame(1, Session::count());
        $this->assertSame(1, Event::count());
    }

    /**
     * No consent cookie is named by default, so the visitor stays
     * session-scoped · flipping that would be a privacy regression no screen shows.
     */
    public function test_no_persistent_identifier_is_handed_out(): void
    {
        $this->assertNull(config('analytics.identity.consent_cookie'));

        $response = $this->withoutDefer()
            ->withHeader('Origin', config('app.url'))
            ->postJson('/__analytics', [
                'sent_at' => 1000,
                'events' => [['type' => 'pageview', 'ts' => 1000, 'route' => 'home', 'url' => 'https://exemple.test/']],
            ]);

        $response->assertNoContent();
        $this->assertNull($response->getCookie('fa_vid', false), 'A cookie would outlive the session.');
    }

    /**
     * The directive is the one thing a host writes by hand · an unknown one
     * prints as plain text and nothing is measured.
     */
    public function test_the_collector_lands_on_a_public_page(): void
    {
        // A real route: the kit lays the file on a response, not on a bare rendered string.
        Route::get('/une-page-publique', fn (): string => Blade::render(
            '<!DOCTYPE html><html><head><title>t</title></head><body>@analyticsCollector</body></html>'
        ));

        $html = (string) $this->get('/une-page-publique')->assertSuccessful()->getContent();

        $this->assertStringContainsString('window.__falconAnalytics=', $html);
        $this->assertStringContainsString('analytics/analytics.js', $html);
    }
}
