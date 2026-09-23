<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\Dashboard;

use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Models\Event;

/**
 * The name an event is shown under, on every screen · its declared label,
 * alone · else the text clicked, alone · else its technical name.
 *
 * @internal
 */
final readonly class EventNames
{
    public function __construct(private EventRegistry $events) {}

    public function of(Event $event): string
    {
        return $this->declared($event->name)
            ?? self::filled($event->target_text)
            ?? self::filled($event->name)
            ?? ($event->type === EventType::Click ? __('Clic') : __('Événement'));
    }

    /** A click as the ranking keys it · by its event when it has one, by its text otherwise. */
    public function ofRankedClick(string $key): string
    {
        return $this->declared($key) ?? $key;
    }

    private function declared(?string $name): ?string
    {
        return $name !== null && $name !== '' ? $this->events->get($name)?->label : null;
    }

    private static function filled(?string $value): ?string
    {
        return $value !== null && trim($value) !== '' ? $value : null;
    }
}
