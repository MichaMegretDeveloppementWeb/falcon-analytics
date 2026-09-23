<?php

declare(strict_types=1);

namespace Falcon\Analytics\Database\Factories;

use Falcon\Analytics\Models\Visitor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A browser seen for the first time, as the ingestion records it.
 *
 * @extends Factory<Visitor>
 */
final class VisitorFactory extends Factory
{
    protected $model = Visitor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'session_count' => 0,
            'subject_type' => null,
            'subject_id' => null,
            'merged_into_id' => null,
        ];
    }

    /** A visitor the host recognised as one of its own accounts. */
    public function forSubject(string $type, int $id): self
    {
        return $this->state(fn (): array => ['subject_type' => $type, 'subject_id' => $id]);
    }
}
