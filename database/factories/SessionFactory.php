<?php

declare(strict_types=1);

namespace Falcon\Analytics\Database\Factories;

use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A visit that has just started, as the ingestion opens it · nothing counted
 * yet, and nothing it could not know.
 *
 * @extends Factory<Session>
 */
final class SessionFactory extends Factory
{
    protected $model = Session::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'visitor_id' => Visitor::factory(),
            'browser_key' => fn (array $attributes): mixed => Visitor::query()->whereKey($attributes['visitor_id'])->value('uuid'),
            'started_at' => now(),
            'last_activity_at' => now(),
            'ended_at' => null,
            'is_bot' => false,
            'pageview_count' => 0,
            'click_count' => 0,
            'event_count' => 0,
        ];
    }

    /** A visit opened and last active at the given moment. */
    public function at(mixed $moment): self
    {
        return $this->state(fn (): array => ['started_at' => $moment, 'last_activity_at' => $moment]);
    }

    public function bot(): self
    {
        return $this->state(fn (): array => ['is_bot' => true]);
    }

    /** A visit made by one of the host's accounts. */
    public function forSubject(string $type, int $id): self
    {
        return $this->state(fn (): array => ['subject_type' => $type, 'subject_id' => $id]);
    }
}
