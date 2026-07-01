<?php

declare(strict_types=1);

namespace Falcon\Analytics;

use Closure;
use Falcon\Analytics\Services\ServerEventRecorder;

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
     * Record a server-emitted event for the current visitor (thin delegate to
     * ServerEventRecorder). Like any event, it belongs to a funnel by its name.
     * A no-op when tracking is off or the context is excluded.
     *
     * @param  array<string, scalar|null>  $props
     */
    public function record(string $name, ?float $value = null, array $props = []): void
    {
        app(ServerEventRecorder::class)->record($name, $value, $props);
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
    public function consentGranted(): bool
    {
        if ($this->consentResolver !== null) {
            return (bool) ($this->consentResolver)();
        }

        $cookie = config('analytics.identity.consent_cookie');

        return is_string($cookie) && request()->cookie($cookie) === '1';
    }

    /** Whether the current request must be excluded from tracking (default: false). */
    public function excluded(): bool
    {
        if ($this->exclusionResolver !== null) {
            return (bool) ($this->exclusionResolver)();
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
            if (auth()->guard($guard)->check()) {
                return ['type' => $guard, 'id' => (int) auth()->guard($guard)->id()];
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

        return ['type' => (string) $subject['type'], 'id' => (int) $subject['id']];
    }
}
