<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Marketing\AdDetail;
use Falcon\Analytics\DTOs\Dashboard\Marketing\CampaignDetail;
use Falcon\Analytics\DTOs\Dashboard\Session\SessionDetail;
use Falcon\Analytics\DTOs\Dashboard\Visitor\VisitorDetail;
use Falcon\Analytics\Livewire\Admin\AdDetailPage;
use Falcon\Analytics\Livewire\Admin\AdsPage;
use Falcon\Analytics\Livewire\Admin\CampaignDetailPage;
use Falcon\Analytics\Livewire\Admin\CampaignsPage;
use Falcon\Analytics\Livewire\Admin\RealtimePage;
use Falcon\Analytics\Livewire\Admin\SessionDetailPage;
use Falcon\Analytics\Livewire\Admin\SessionsPage;
use Falcon\Analytics\Livewire\Admin\VisitorDetailPage;
use Falcon\Analytics\Livewire\Admin\VisitorsPage;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Livewire\Component;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Symfony\Component\Finder\Finder;

/**
 * A screen hands its view values prepared for it, never a record · a detail
 * page its fiche, a list one row per line.
 *
 * A record reaching a view lets the view read whatever it likes, relations
 * included, and each of those reads is a query the screen never planned · the
 * record a screen keeps between two clicks is read back bare, so every relation
 * the view touches is read again on every click.
 */
final class TheScreensHandOverValuesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-07-10 12:00:00'));
        $this->actingAs(TestAdmin::create([]), 'admin');
    }

    public function test_no_reactive_component_keeps_a_record_in_a_public_property(): void
    {
        $holding = [];
        $read = 0;

        foreach ((new Finder)->files()->in(dirname(__DIR__, 2).'/src/Livewire')->name('*.php') as $file) {
            $class = 'Falcon\\Analytics\\Livewire\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

            if (! class_exists($class) && ! trait_exists($class)) {
                $this->fail("{$class} does not load: the sweep would skip it.");
            }

            $reflection = new ReflectionClass($class);
            $read++;

            foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                $type = $property->getType();

                if ($type instanceof ReflectionNamedType && ! $type->isBuiltin() && is_a($type->getName(), Model::class, true)) {
                    $holding[] = $class.'::$'.$property->getName();
                }
            }
        }

        $this->assertGreaterThan(20, $read, 'The sweep read almost nothing: it would pass on an empty folder.');
        $this->assertSame([], $holding);
    }

    /** @return array<string, array{string}> */
    public static function screens(): array
    {
        return ['a session' => ['session'], 'a visitor' => ['visitor'], 'a campaign' => ['campaign'], 'an ad' => ['ad']];
    }

    /** Nothing handed to the view is a record, on the first render and after a click, the rows it lists included. */
    #[DataProvider('screens')]
    public function test_a_detail_view_receives_no_record(string $screen): void
    {
        [$component, $parameters, $view, $fiche] = $this->screen($screen);

        $page = $this->renderedTwice($component, $parameters, $view);

        $this->assertInstanceOf($fiche, $page->viewData('detail'));
    }

    /** @return array<string, array{class-string, string}> */
    public static function lists(): array
    {
        return [
            'the sessions' => [SessionsPage::class, 'analytics::livewire.dashboard.sessions'],
            'the visitors' => [VisitorsPage::class, 'analytics::livewire.dashboard.visitors'],
            'the realtime board' => [RealtimePage::class, 'analytics::livewire.dashboard.realtime'],
            'the campaigns' => [CampaignsPage::class, 'analytics::livewire.dashboard.marketing-campaigns'],
            'the ads' => [AdsPage::class, 'analytics::livewire.dashboard.marketing-ads'],
        ];
    }

    /**
     * A list hands its view one prepared row per line · a record would let the
     * row read what it likes, once per line.
     *
     * @param  class-string  $component
     */
    #[DataProvider('lists')]
    public function test_a_list_view_receives_no_record(string $component, string $view): void
    {
        $this->aSession();
        $this->anAd();

        $this->renderedTwice($component, [], $view);
    }

    /**
     * Renders the screen, then clicks it once, and says whether either render
     * handed its view a record.
     *
     * @param  class-string  $component
     * @param  array<string, mixed>  $parameters
     * @return Testable<Component>
     */
    private function renderedTwice(string $component, array $parameters, string $view): Testable
    {
        $handed = [];
        View::composer($view, function ($rendered) use (&$handed): void {
            $handed[] = $rendered->getData();
        });

        $page = Livewire::test($component, $parameters);
        $page->call('$refresh');

        $this->assertCount(2, $handed, 'The view was not rendered twice: the sweep below would hold on nothing.');

        foreach ($handed as $render => $data) {
            $this->assertSame([], $this->recordsIn($data, ''), "Render {$render} handed a record to its view.");
        }

        return $page;
    }

    /** The number the screen keeps cannot be changed from the browser. */
    #[DataProvider('screens')]
    public function test_the_screen_keeps_its_number_locked(string $screen): void
    {
        [$component, $parameters] = $this->screen($screen);
        $other = $this->screen($screen)[1];

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test($component, $parameters)->set($screen.'Id', array_values($other)[0]->getKey());
    }

    /**
     * The component, its mount parameters, its view, and the fiche it hands over.
     *
     * @return array{class-string, array<string, Model>, string, class-string}
     */
    private function screen(string $screen): array
    {
        return match ($screen) {
            'session' => [SessionDetailPage::class, ['session' => $this->aSession()], 'analytics::livewire.dashboard.session-detail', SessionDetail::class],
            'visitor' => [VisitorDetailPage::class, ['visitor' => $this->aSession()->visitor()->firstOrFail()], 'analytics::livewire.dashboard.visitor-detail', VisitorDetail::class],
            'campaign' => [CampaignDetailPage::class, ['campaign' => $this->anAd()->campaign()->firstOrFail()], 'analytics::livewire.dashboard.marketing-campaign-detail', CampaignDetail::class],
            'ad' => [AdDetailPage::class, ['ad' => $this->anAd()], 'analytics::livewire.dashboard.marketing-ad-detail', AdDetail::class],
            default => $this->fail("No screen named {$screen}."),
        };
    }

    private function aSession(): Session
    {
        $session = Session::factory()
            ->for(Visitor::factory()->state(['session_count' => 1]))
            ->create(['started_at' => now()->subMinutes(5), 'pageview_count' => 1, 'source' => 'direct', 'device_type' => 'desktop']);
        Event::factory()->for($session)->create(['occurred_at' => now()->subMinutes(5), 'url' => 'https://exemple.test/']);

        return $session;
    }

    private function anAd(): Ad
    {
        return Ad::factory()
            ->for(Campaign::factory()->matching('src', 'meta')->state(['platform' => 'Meta']))
            ->matching('creative', 'v')
            ->create(['name' => 'Annonce']);
    }

    /**
     * Where records sit in what a view received · arrays, collections, pages
     * and the package's own fiches are walked through.
     *
     * @return list<string>
     */
    private function recordsIn(mixed $value, string $path): array
    {
        if ($value instanceof Model) {
            return [$path];
        }

        $children = match (true) {
            is_array($value) => $value,
            $value instanceof Collection => $value->all(),
            $value instanceof Paginator => $value->items(),
            is_object($value) && str_starts_with($value::class, 'Falcon\\Analytics\\DTOs\\') => get_object_vars($value),
            default => [],
        };

        $found = [];
        foreach ($children as $key => $child) {
            $found = [...$found, ...$this->recordsIn($child, $path.'.'.$key)];
        }

        return $found;
    }
}
