<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Requests;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\IncomingBatch;
use Falcon\Analytics\DTOs\IncomingEvent;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Support\UrlRedactor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IngestBatchRequest extends FormRequest
{
    /** Oldest event age kept when reconstructing timestamps from client deltas. */
    private const MAX_EVENT_AGE_MS = 3_600_000;

    public function authorize(): bool
    {
        // Access is gated by the endpoint (enabled flag, exclusions) and route
        // middleware, not by per-user authorization. This is same-origin telemetry.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sent_at' => ['required', 'integer'],
            'referrer' => ['nullable', 'string', 'max:2048'],
            'events' => ['required', 'array', 'min:1', 'max:100'],
            'events.*.type' => ['required', Rule::enum(EventType::class)],
            'events.*.ts' => ['required', 'integer'],
            'events.*.name' => ['nullable', 'string', 'max:120'],
            'events.*.route' => ['nullable', 'string', 'max:191'],
            'events.*.url' => ['nullable', 'string', 'max:2048'],
            'events.*.selector' => ['nullable', 'string', 'max:255'],
            'events.*.text' => ['nullable', 'string', 'max:255'],
            'events.*.props' => ['nullable', 'array'],
            // Bounded to the decimal(12,2) column so an overflow cannot fail the insert.
            'events.*.value' => ['nullable', 'numeric', 'between:-9999999999.99,9999999999.99'],
        ];
    }

    /**
     * Map the validated payload into typed DTOs. Timestamps are reconstructed
     * from the client-relative deltas applied to the server time, so an offset
     * client clock cannot skew the recorded times. Sensitive query parameters are
     * redacted from every stored URL.
     */
    public function toBatch(): IncomingBatch
    {
        $now = CarbonImmutable::now();
        $sentAt = (int) $this->validated('sent_at');
        $redactor = new UrlRedactor;

        $events = array_map(
            function (array $raw) use ($now, $sentAt, $redactor): IncomingEvent {
                $deltaMs = max(0, min($sentAt - (int) $raw['ts'], self::MAX_EVENT_AGE_MS));

                return new IncomingEvent(
                    type: EventType::from($raw['type']),
                    occurredAt: $now->subMilliseconds($deltaMs),
                    name: $raw['name'] ?? null,
                    route: $raw['route'] ?? null,
                    url: $redactor->redact($raw['url'] ?? null),
                    targetSelector: $raw['selector'] ?? null,
                    targetText: $raw['text'] ?? null,
                    props: $raw['props'] ?? null,
                    value: isset($raw['value']) ? (float) $raw['value'] : null,
                );
            },
            $this->validated('events'),
        );

        return new IncomingBatch(
            events: array_values($events),
            referrer: $redactor->redact($this->validated('referrer')),
        );
    }
}
