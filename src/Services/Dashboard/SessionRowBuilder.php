<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\Session\SessionRow;
use Falcon\Analytics\DTOs\Dashboard\SessionSubjectAttribution;
use Falcon\Analytics\DTOs\Dashboard\SubjectName;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Services\SubjectResolver;
use Falcon\Analytics\Support\DeviceLabel;
use Falcon\Analytics\Support\DurationLabel;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Prepares the lines of the session list from a page of sessions already read,
 * their visitors loaded · the names come in one query per guard.
 *
 * @internal
 */
final readonly class SessionRowBuilder
{
    public function __construct(
        private SessionSubjectAttributor $attributor,
        private SubjectResolver $subjects,
    ) {}

    /**
     * @param  LengthAwarePaginator<int, Session>  $sessions
     * @return LengthAwarePaginator<int, SessionRow>
     */
    public function build(LengthAwarePaginator $sessions): LengthAwarePaginator
    {
        $attributions = $this->attributor->attribute($sessions->items());
        $names = $this->subjects->shownNames(array_values(array_map(
            static fn (SessionSubjectAttribution $attribution): array => [$attribution->guard, $attribution->id],
            $attributions,
        )));

        return $sessions->through(fn (Session $session): SessionRow => $this->row($session, $attributions[$session->id] ?? null, $names));
    }

    /**
     * @param  array<string, SubjectName>  $names
     */
    private function row(Session $session, ?SessionSubjectAttribution $attribution, array $names): SessionRow
    {
        $subject = $attribution !== null ? $names[$attribution->guard.':'.$attribution->id] : null;

        return new SessionRow(
            id: $session->id,
            name: $subject->name ?? __('Visiteur #:id', ['id' => $session->visitor_id]),
            label: $subject?->label,
            visitorUuid: $session->visitor->uuid,
            notConnected: $attribution !== null && $attribution->viaVisitor,
            startedAt: $session->started_at,
            duration: DurationLabel::for((int) $session->started_at->diffInSeconds($session->last_activity_at)),
            pageviewCount: $session->pageview_count,
            eventsCount: (int) $session->getAttribute('events_count'),
            conversionsCount: (int) $session->getAttribute('conversions_count'),
            source: $session->source,
            landingRoute: $session->landing_route,
            landingUrl: $session->landing_url,
            device: self::device($session),
            country: $session->country,
            city: $session->city,
        );
    }

    /** The device and browser on one line · null when neither is known. */
    private static function device(Session $session): ?string
    {
        $device = filled($session->device_type) ? DeviceLabel::for($session->device_type) : null;
        $browser = filled($session->browser) ? $session->browser : null;

        if ($device === null && $browser === null) {
            return null;
        }

        return ($device ?? __('Inconnu')).($browser !== null ? ' · '.$browser : '');
    }
}
