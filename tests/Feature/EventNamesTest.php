<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Services\Dashboard\EventNames;
use Falcon\Analytics\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The name an event is shown under · its declared label, alone · else the
 * text clicked, alone · else its technical name.
 */
final class EventNamesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['analytics.events_path' => __DIR__.'/../Fixtures/analytics-events.php']);
        $this->app->forgetInstance(EventRegistry::class);
    }

    /** @return array<string, array{EventType, string|null, string|null, string}> */
    public static function events(): array
    {
        return [
            'a declared event, with a text' => [EventType::Click, 'sample.action', 'Réserver maintenant', 'Sample action'],
            'a declared event, without a text' => [EventType::Custom, 'sample.action', null, 'Sample action'],
            'an undeclared event, with a text' => [EventType::Click, 'cta.missing', 'Voir plus', 'Voir plus'],
            'an undeclared event, without a text' => [EventType::Custom, 'cta.missing', null, 'cta.missing'],
            'a plain click, with a text' => [EventType::Click, null, 'Menu', 'Menu'],
            'a text of blanks is no text' => [EventType::Click, 'cta.missing', '   ', 'cta.missing'],
            'a click with nothing' => [EventType::Click, null, null, 'Clic'],
            'another event with nothing' => [EventType::Custom, null, null, 'Événement'],
        ];
    }

    #[DataProvider('events')]
    public function test_an_event_reads_as_its_label_else_its_text_else_its_name(EventType $type, ?string $name, ?string $text, string $shown): void
    {
        $event = new Event(['type' => $type, 'name' => $name, 'target_text' => $text]);

        $this->assertSame($shown, app(EventNames::class)->of($event));
    }

    /** @return array<string, array{string, string}> */
    public static function rankedClicks(): array
    {
        return [
            'a declared event' => ['sample.action', 'Sample action'],
            'a text' => ['Menu', 'Menu'],
            'an undeclared event' => ['cta.missing', 'cta.missing'],
        ];
    }

    /** The ranking keys a click by its event when it has one, by its text otherwise. */
    #[DataProvider('rankedClicks')]
    public function test_a_ranked_click_reads_as_its_label_when_its_event_is_declared(string $key, string $shown): void
    {
        $this->assertSame($shown, app(EventNames::class)->ofRankedClick($key));
    }
}
