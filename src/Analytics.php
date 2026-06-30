<?php

declare(strict_types=1);

namespace Falcon\Analytics;

use Closure;

/**
 * Integration surface between the host application and the package.
 *
 * The host registers three closures (subject, consent, exclusion) from a service
 * provider; the package resolves them per request. Registered as a container
 * singleton so the closures survive across the request lifecycle.
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
     * Register how to resolve the currently identified subject.
     * The closure must return ['type' => string, 'id' => int] or null.
     *
     * @param  Closure(): mixed  $resolver
     */
    public function resolveSubjectUsing(Closure $resolver): void
    {
        $this->subjectResolver = $resolver;
    }

    /**
     * Register how to tell whether the visitor consented to a persistent identifier.
     *
     * @param  Closure(): bool  $resolver
     */
    public function consentUsing(Closure $resolver): void
    {
        $this->consentResolver = $resolver;
    }

    /**
     * Register how to tell whether the current request must be excluded entirely
     * (internal staff, etc.).
     *
     * @param  Closure(): bool  $resolver
     */
    public function excludeUsing(Closure $resolver): void
    {
        $this->exclusionResolver = $resolver;
    }

    /**
     * The current identified subject, or null when anonymous. Malformed resolver
     * output is normalised to null so a host mistake never corrupts the data.
     *
     * @return array{type: string, id: int}|null
     */
    public function subject(): ?array
    {
        if ($this->subjectResolver === null) {
            return null;
        }

        $subject = ($this->subjectResolver)();

        if (! is_array($subject) || ! isset($subject['type'], $subject['id'])) {
            return null;
        }

        return ['type' => (string) $subject['type'], 'id' => (int) $subject['id']];
    }

    /** Whether the visitor granted consent for a persistent identifier (default: false). */
    public function consentGranted(): bool
    {
        return $this->consentResolver !== null && (bool) ($this->consentResolver)();
    }

    /** Whether the current request must be excluded from tracking (default: false). */
    public function excluded(): bool
    {
        return $this->exclusionResolver !== null && (bool) ($this->exclusionResolver)();
    }
}
