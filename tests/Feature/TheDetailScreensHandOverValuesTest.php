<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Marketing\AdDetail;
use Falcon\Analytics\DTOs\Dashboard\Marketing\CampaignDetail;
use Falcon\Analytics\DTOs\Dashboard\Session\SessionDetail;
use Falcon\Analytics\DTOs\Dashboard\Visitor\VisitorDetail;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Livewire\Admin\AdDetailPage;
use Falcon\Analytics\Livewire\Admin\CampaignDetailPage;
use Falcon\Analytics\Livewire\Admin\SessionDetailPage;
use Falcon\Analytics\Livewire\Admin\VisitorDetailPage;
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
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Symfony\Component\Finder\Finder;

/**
 * A detail screen hands its view values prepared for it, never a record.
 *
 * A record reaching a view lets the view read whatever it likes, relations
 * included, and each of those reads is a query the screen never planned · the
 * record a screen keeps between two clicks is read back bare, so every relation
 * the view touches is read again on every click.
 */
final class TheDetailScreensHandOverValuesTest extends TestCase
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

    /**
     * Nothing handed to the view is a record, on the first render and after a
     * click · except the rows a detail screen lists, which are a list's.
     */
    #[DataProvider('screens')]
    public function test_a_detail_view_receives_no_record(string $screen): void
    {
        [$component, $parameters, $view, $listed, $fiche] = $this->screen($screen);

        $handed = [];
        View::composer($view, function ($rendered) use (&$handed): void {
            $handed[] = $rendered->getData();
        });

        $page = Livewire::test($component, $parameters);
        $page->call('$refresh');

        $this->assertCount(2, $handed, 'The view was not rendered twice: the sweep below would hold on nothing.');
        $this->assertInstanceOf($fiche, $page->viewData('detail'));

        foreach ($handed as $render => $data) {
            $this->assertSame([], $this->recordsIn(array_diff_key($data, array_flip($listed)), ''), "Render {$render} handed a record to its view.");
        }
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
     * The component, its mount parameters, its view, the keys it lists, and the
     * fiche it hands over.
     *
     * @return array{class-string, array<string, Model>, string, list<string>, class-string}
     */
    private function screen(string $screen): array
    {
        return match ($screen) {
            'session' => [SessionDetailPage::class, ['session' => $this->aSession()], 'analytics::livewire.dashboard.session-detail', [], SessionDetail::class],
            'visitor' => [VisitorDetailPage::class, ['visitor' => $this->aSession()->visitor()->firstOrFail()], 'analytics::livewire.dashboard.visitor-detail', ['sessions'], VisitorDetail::class],
            'campaign' => [CampaignDetailPage::class, ['campaign' => $this->anAd()->campaign()->firstOrFail()], 'analytics::livewire.dashboard.marketing-campaign-detail', ['ads'], CampaignDetail::class],
            'ad' => [AdDetailPage::class, ['ad' => $this->anAd()], 'analytics::livewire.dashboard.marketing-ad-detail', [], AdDetail::class],
            default => $this->fail("No screen named {$screen}."),
        };
    }

    private function aSession(): Session
    {
        $visitor = Visitor::create(['uuid' => (string) Str::uuid(), 'first_seen_at' => now(), 'last_seen_at' => now(), 'session_count' => 1]);
        $session = Session::create([
            'visitor_id' => $visitor->id, 'browser_key' => $visitor->uuid, 'started_at' => now()->subMinutes(5), 'last_activity_at' => now(),
            'is_bot' => false, 'pageview_count' => 1, 'click_count' => 0, 'source' => 'direct', 'device_type' => 'desktop',
        ]);
        Event::create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'occurred_at' => now()->subMinutes(5), 'type' => EventType::Pageview, 'url' => 'https://exemple.test/']);

        return $session;
    }

    private function anAd(): Ad
    {
        $campaign = Campaign::create(['name' => 'Campagne '.Str::random(4), 'platform' => 'Meta', 'match_conditions' => [['param' => 'src', 'value' => 'meta']]]);

        return Ad::create(['campaign_id' => $campaign->id, 'name' => 'Annonce', 'match_conditions' => [['param' => 'creative', 'value' => 'v']]]);
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
