<?php

declare(strict_types=1);

namespace Falcon\Analytics\Database\Factories;

use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A page view within a visit · its visitor is the visit's, and its page is left
 * to the model, which derives it from the address.
 *
 * @extends Factory<Event>
 */
final class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => Session::factory(),
            'visitor_id' => fn (array $attributes): mixed => Session::query()->whereKey($attributes['session_id'])->value('visitor_id'),
            'occurred_at' => now(),
            'type' => EventType::Pageview,
        ];
    }

    /** A click, named when the element was declared, described by its text otherwise. */
    public function click(?string $name = null, ?string $text = null): self
    {
        return $this->state(fn (): array => ['type' => EventType::Click, 'name' => $name, 'target_text' => $text]);
    }

    /** An event the host sends by name. */
    public function custom(string $name): self
    {
        return $this->state(fn (): array => ['type' => EventType::Custom, 'name' => $name]);
    }
}
