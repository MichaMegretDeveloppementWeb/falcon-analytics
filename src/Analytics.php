<?php

declare(strict_types=1);

namespace Falcon\Analytics;

use Carbon\CarbonImmutable;
use Closure;
use Falcon\Analytics\Actions\IngestEventsAction;
use Falcon\Analytics\DTOs\IncomingBatch;
use Falcon\Analytics\DTOs\IncomingEvent;
use Falcon\Analytics\DTOs\RequestSnapshot;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Services\VisitorIdentityResolver;
use Falcon\Analytics\Support\UrlRedactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Integration surface between the host application and the package.
 *
 * By default the subject, exclusions and consent are resolved from the
 * declarative `analytics.identity` config (guards and cookie name), so a host
 * needs no glue code. A host may instead register closures for advanced logic;
 * a registered closure always takes precedence over the config.
 */
final class Analytics
{
    /** @var (Closure(): mixed)|null */
    private ?Closure $subjectResolver = null;

    /** @var (Closure(): bool)|null */
    private ?Closure $consentResolver = null;

    /** @var (Closure(): bool)|null */
    private ?Closure $exclusionResolver = null;

    /**
     * @param  Closure(): mixed  $resolver  must return ['type' => string, 'id' => int] or null
     */
    public function resolveSubjectUsing(Closure $resolver): void
    {
        $this->subjectResolver = $resolver;
    }

    /**
     * @param  Closure(): bool  $resolver
     */
    public function consentUsing(Closure $resolver): void
    {
        $this->consentResolver = $resolver;
    }

    /**
     * @param  Closure(): bool  $resolver
     */
    public function excludeUsing(Closure $resolver): void
    {
        $this->exclusionResolver = $resolver;
    }

    /**
     * Record a server-emitted event for the current visitor · same visitor and
     * session, same storage, same funnels as the collector's: a code-sent event
     * is just an event whose source is the server. Like any event, it belongs
     * to a funnel by its name.
     *
     * A no-op when tracking is off or the context is excluded (e.g. an admin),
     * and deferred so it never blocks the response. Outside a web request that
     * carries a session — a queued job, a command — there is no visitor to
     * record it for: nothing is recorded, and the log says so. Nothing here
     * ever throws to the caller: analytics must never break the code that
     * emits an event.
     *
     * The score is a whole number of points, not an amount: see TrackedEvent.
     *
     * @param  array<string, scalar|null>  $props
     */
    public function record(string $name, ?int $value = null, array $props = []): void
    {
        if (config('analytics.enabled') !== true) {
            return;
        }

        try {
            $request = request();

            if (! $request->hasSession()) {
                Log::channel(config('analytics.log_channel'))->warning(
                    'Analytics server event not recorded: it was sent outside a visitor\'s web request.',
                    ['event' => $name],
                );

                return;
            }

            if ($this->isExcluded()) {
                return;
            }

            $this->ingestAfterTheResponse(
                app(VisitorIdentityResolver::class)->resolve($request, $this->hasConsent()),
                $this->subject(),
                new RequestSnapshot(ip: $request->ip(), userAgent: $request->userAgent(), host: $request->getHost()),
                $this->serverBatch($request, $name, $value, $props),
            );
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Analytics server event could not be recorded.', [
                'exception' => $e,
            ]);
        }
    }

    /**
     * The current identified subject, or null when anonymous.
     *
     * @return array{type: string, id: int}|null
     */
    public function subject(): ?array
    {
        $subject = $this->subjectResolver !== null
            ? ($this->subjectResolver)()
            : $this->subjectFromGuards();

        return $this->normaliseSubject($subject);
    }

    /** Whether the visitor consented to a persistent identifier (default: false). */
    public function hasConsent(): bool
    {
        if ($this->consentResolver !== null) {
            return ($this->consentResolver)();
        }

        $cookie = config('analytics.identity.consent_cookie');

        return is_string($cookie) && request()->cookie($cookie) === '1';
    }

    /** Whether the current request must be excluded from tracking (default: false). */
    public function isExcluded(): bool
    {
        if ($this->exclusionResolver !== null) {
            return ($this->exclusionResolver)();
        }

        foreach ($this->guards('exclude_guards') as $guard) {
            if (auth()->guard($guard)->check()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{type: string, id: int}|null
     */
    private function subjectFromGuards(): ?array
    {
        foreach ($this->guards('subject_guards') as $guard) {
            if (! auth()->guard($guard)->check()) {
                continue;
            }

            $id = auth()->guard($guard)->id();

            // subject_id is an integer column: a non-numeric key (e.g. a UUID)
            // cannot be stored, so the subject is left unstitched rather than
            // silently collapsed to 0.
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                return ['type' => $guard, 'id' => (int) $id];
            }
        }

        return null;
    }

    /**
     * Configured guard names for a key, keeping only guards that actually exist
     * so a stray name never triggers a runtime error during ingestion.
     *
     * @return list<string>
     */
    private function guards(string $key): array
    {
        $defined = config('auth.guards', []);

        return array_values(array_filter(
            (array) config("analytics.identity.{$key}", []),
            fn ($guard): bool => is_string($guard) && array_key_exists($guard, $defined),
        ));
    }

    /**
     * Malformed resolver output is normalised to null so a host mistake never
     * corrupts the data.
     *
     * @return array{type: string, id: int}|null
     */
    private function normaliseSubject(mixed $subject): ?array
    {
        if (! is_array($subject) || ! isset($subject['type'], $subject['id'])) {
            return null;
        }

        $type = $subject['type'];
        $id = $subject['id'];

        // Same rule as `subjectFromGuards()`, applied to what a host's resolver
        // hands over: subject_id is an integer column, so a non-numeric key
        // (e.g. a UUID) cannot be stored. Casting it would attach every one of
        // those visits to subject 0, a subject that does not exist and that
        // would gather everybody's journeys.
        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            return null;
        }

        if (! is_string($type) || $type === '') {
            return null;
        }

        return ['type' => $type, 'id' => (int) $id];
    }

    /**
     * One custom event, as the server saw the request it was emitted in.
     *
     * @param  array<string, scalar|null>  $props
     */
    private function serverBatch(Request $request, string $name, ?int $value, array $props): IncomingBatch
    {
        return new IncomingBatch(events: [new IncomingEvent(
            type: EventType::Custom,
            occurredAt: CarbonImmutable::now(),
            name: $name,
            route: $request->route()?->getName(),
            url: app(UrlRedactor::class)->redact($request->fullUrl()),
            props: $props === [] ? null : $props,
            value: $value,
        )]);
    }

    /**
     * Hand the batch to the ingestion once the response has gone · a failure
     * there is logged, never thrown to the code that emitted the event.
     *
     * @param  array{type: string, id: int}|null  $subject
     */
    private function ingestAfterTheResponse(string $uuid, ?array $subject, RequestSnapshot $snapshot, IncomingBatch $batch): void
    {
        $action = app(IngestEventsAction::class);

        defer(function () use ($action, $uuid, $subject, $snapshot, $batch): void {
            try {
                $action->execute($uuid, $subject, $snapshot, $batch);
            } catch (Throwable $e) {
                Log::channel(config('analytics.log_channel'))->error('Analytics server event failed.', [
                    'exception' => $e,
                ]);
            }
        });
    }
}
