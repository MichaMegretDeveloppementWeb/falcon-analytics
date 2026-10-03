<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Enums\Authorization\Ability;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\CompiledRouteCollection;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * An address under the package's prefixes that leads nowhere answers with the
 * package's own page · in the layout the host names for the administration,
 * with the status of a page not found. Everything else keeps the host's answer.
 */
final class APageThatIsNotThereTest extends TestCase
{
    use RefreshDatabase;

    private const string SENTENCE = 'Cette page n’existe pas ou plus.';

    private function signIn(): void
    {
        $this->actingAs(TestAdmin::create([]), 'admin');
    }

    /** @return array<string, array{string, string}> */
    public static function spaces(): array
    {
        return [
            'the audience' => ['/admin/analytics/nulle-part', 'Page introuvable · Audience'],
            'the marketing' => ['/admin/marketing/nulle-part', 'Page introuvable · Marketing'],
        ];
    }

    #[DataProvider('spaces')]
    public function test_an_unknown_address_answers_with_the_packages_page(string $address, string $title): void
    {
        $this->signIn();

        $this->get($address)
            ->assertNotFound()
            ->assertSee(self::SENTENCE)
            ->assertSee('<title>'.$title, false)
            ->assertSee('data-an-area="admin"', false)
            ->assertSee('href="'.route('analytics.admin.overview').'"', false);
    }

    public function test_the_page_is_drawn_in_the_layout_the_host_names(): void
    {
        View::addLocation(__DIR__.'/../Fixtures/views');
        config()->set('analytics.layouts.admin', 'host-shell');

        $this->signIn();

        $this->get('/admin/analytics/nulle-part')
            ->assertNotFound()
            ->assertSee('chrome fourni par le gabarit')
            ->assertSee(self::SENTENCE);
    }

    public function test_the_way_back_is_not_offered_to_an_account_that_may_not_open_the_overview(): void
    {
        $this->signIn();

        Gate::define(Ability::Overview, fn (TestAdmin $admin): bool => false);

        $this->get('/admin/analytics/nulle-part')
            ->assertNotFound()
            ->assertSee(self::SENTENCE)
            ->assertDontSee('href="'.route('analytics.admin.overview').'"', false);
    }

    public function test_an_account_barred_from_the_package_learns_nothing(): void
    {
        $this->signIn();

        Gate::define(Ability::Analytics, fn (TestAdmin $admin): bool => false);

        $this->get('/admin/analytics/nulle-part')
            ->assertForbidden()
            ->assertDontSee(self::SENTENCE);
    }

    public function test_a_guest_is_sent_to_sign_in_and_learns_nothing(): void
    {
        $this->get('/admin/analytics/nulle-part')
            ->assertRedirect(route('login'))
            ->assertDontSee(self::SENTENCE);
    }

    /** @return array<string, array{string}> */
    public static function methods(): array
    {
        return ['post' => ['POST'], 'put' => ['PUT'], 'delete' => ['DELETE']];
    }

    #[DataProvider('methods')]
    public function test_every_method_reaches_the_page(string $method): void
    {
        $this->signIn();

        $this->call($method, '/admin/marketing/nulle-part')
            ->assertNotFound()
            ->assertSee(self::SENTENCE);
    }

    public function test_an_address_outside_the_package_keeps_the_hosts_answer(): void
    {
        $this->signIn();

        $this->get('/nulle-part')
            ->assertNotFound()
            ->assertDontSee(self::SENTENCE);
    }

    public function test_a_request_that_wants_json_is_not_given_the_page(): void
    {
        $this->signIn();

        $this->getJson('/admin/analytics/nulle-part')
            ->assertNotFound()
            ->assertDontSee(self::SENTENCE);
    }

    public function test_a_request_the_reactive_layer_sends_is_never_given_the_page(): void
    {
        $request = Request::create('/admin/analytics/nulle-part', 'POST', server: ['HTTP_X_LIVEWIRE' => '1']);
        $request->setRouteResolver(fn () => Route::getRoutes()->getByName('analytics.admin.overview'));

        $response = $this->app?->make(ExceptionHandler::class)->render($request, new NotFoundHttpException);

        $this->assertStringNotContainsString(self::SENTENCE, (string) $response?->getContent());
    }

    public function test_the_collector_never_answers_with_the_page(): void
    {
        $request = Request::create('/analytics/collect', 'POST');
        $request->setRouteResolver(fn () => Route::getRoutes()->getByName('analytics.web.ingest'));

        $response = $this->app?->make(ExceptionHandler::class)->render($request, new NotFoundHttpException);

        $this->assertStringNotContainsString(self::SENTENCE, (string) $response?->getContent());
    }

    public function test_a_route_the_host_adds_under_the_prefix_still_answers(): void
    {
        Route::get('admin/analytics/export-maison', fn (): string => 'export de la maison');

        $this->signIn();

        $this->get('/admin/analytics/export-maison')
            ->assertOk()
            ->assertSee('export de la maison');
    }

    /** What a host declares in `withExceptions()` is registered before any provider. */
    protected function aHostThatAnswersItself(Application $app): void
    {
        $app->afterResolving(Handler::class, function (Handler $handler): void {
            $handler->renderable(fn (NotFoundHttpException $missing): Response => response('la page de l’hôte', 404));
        });
    }

    #[DefineEnvironment('aHostThatAnswersItself')]
    public function test_the_host_can_answer_a_404_itself(): void
    {
        $this->signIn();

        $this->get('/admin/analytics/nulle-part')
            ->assertNotFound()
            ->assertSee('la page de l’hôte')
            ->assertDontSee(self::SENTENCE);
    }

    public function test_the_unknown_addresses_survive_route_caching(): void
    {
        $collection = Route::getRoutes();
        $this->assertInstanceOf(RouteCollection::class, $collection);

        $compiled = $collection->compile();
        $routes = (new CompiledRouteCollection($compiled['compiled'], $compiled['attributes']))
            ->setRouter(app(Router::class))
            ->setContainer(app());

        $this->assertSame('analytics.admin.missing', $routes->match(Request::create('/admin/analytics/nulle-part'))->getName());
        $this->assertSame('analytics.admin.missing', $routes->match(Request::create('/admin/analytics/nulle-part', 'POST'))->getName());
        $this->assertSame('analytics.admin.visitors', $routes->match(Request::create('/admin/analytics/visitors'))->getName());
        $this->assertSame('analytics.admin.marketing.missing', $routes->match(Request::create('/admin/marketing/nulle-part'))->getName());
    }

    protected function aMarketingMountedAtTheRoot(Application $app): void
    {
        $app['config']->set('analytics.admin.marketing.route_prefix', '');
    }

    #[DefineEnvironment('aMarketingMountedAtTheRoot')]
    public function test_a_space_mounted_at_the_root_takes_no_address(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('analytics.admin.marketing.missing'));
        $this->assertNotNull(Route::getRoutes()->getByName('analytics.admin.missing'));
    }
}
