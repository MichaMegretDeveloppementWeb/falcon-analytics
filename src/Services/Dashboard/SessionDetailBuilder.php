<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\Session\JourneyEvent;
use Falcon\Analytics\DTOs\Dashboard\Session\JourneyStep;
use Falcon\Analytics\DTOs\Dashboard\Session\SessionAcquisition;
use Falcon\Analytics\DTOs\Dashboard\Session\SessionDetail;
use Falcon\Analytics\DTOs\Dashboard\Session\SessionVisitor;
use Falcon\Analytics\DTOs\Dashboard\Session\TimeShare;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Services\SubjectResolver;
use Falcon\Analytics\Support\ChartPalette;
use Falcon\Analytics\Support\DeviceLabel;
use Falcon\Analytics\Support\DurationLabel;
use Falcon\Analytics\Support\SourceLabel;
use Illuminate\Database\Eloquent\Collection;

/**
 * Prepares the detail screen of a session from the session and its events,
 * already read · the view receives values, and reads nothing.
 *
 * @internal
 */
final readonly class SessionDetailBuilder
{
    /** How many pages the time split names before grouping the others. */
    private const NAMED_PAGES = 5;

    private const SOURCE_ICONS = [
        'direct' => 'cursor-arrow-rays',
        'organic' => 'magnifying-glass',
        'social' => 'user-group',
        'paid' => 'megaphone',
        'referral' => 'arrow-top-right-on-square',
        'email' => 'envelope',
        'campaign' => 'flag',
    ];

    public function __construct(
        private SessionJourneyBuilder $journeys,
        private SessionSubjectAttributor $attributor,
        private SubjectResolver $subjects,
    ) {}

    /**
     * @param  Collection<int, Event>  $events  the session's events, oldest first
     * @param  list<string>  $conversionNames
     */
    public function build(Session $session, Collection $events, array $conversionNames): SessionDetail
    {
        $journey = $this->journeys->build($events, $session->started_at, $session->last_activity_at);
        $seconds = (int) $session->started_at->diffInSeconds($session->last_activity_at);
        $timePerPage = $this->journeys->timePerPage($journey);
        $timeTotal = array_sum($timePerPage);

        return new SessionDetail(
            id: $session->id,
            startedAt: $session->started_at,
            visitor: $this->visitor($session),
            duration: DurationLabel::for($seconds),
            pageviewCount: $session->pageview_count,
            // Read off the session: past the retention its anonymous clicks are
            // erased, and the counter outlives the rows it counted.
            clicksCount: $session->click_count,
            eventsCount: $events->whereNotNull('name')->count(),
            conversionsCount: $events->whereIn('name', $conversionNames)->count(),
            averagePageDuration: DurationLabel::for($session->pageview_count > 0 ? (int) round($seconds / $session->pageview_count) : 0),
            journey: $this->steps($journey),
            detailErased: $this->detailWasErased($session, $events),
            acquisition: $this->acquisition($session),
            timeShares: $this->timeShares($timePerPage),
            timeTotal: $timeTotal > 0 ? DurationLabel::for($timeTotal) : null,
            country: $session->country,
            city: self::filled($session->city),
            ip: self::filled($session->ip),
            deviceIcon: self::deviceIcon($session->device_type),
            deviceLabel: $session->device_type !== null && $session->device_type !== '' ? DeviceLabel::for($session->device_type) : null,
            browser: self::joined($session->browser, $session->browser_version),
            system: self::joined($session->os, $session->os_version),
        );
    }

    /** Who the session belongs to, from its subject or the one stitched on its visitor. */
    private function visitor(Session $session): SessionVisitor
    {
        $attribution = $this->attributor->attribute([$session])[$session->id] ?? null;
        $uuid = $session->visitor->uuid;
        $isReturning = $session->visitor->session_count > 1;

        if ($attribution === null) {
            return new SessionVisitor($session->visitor_id, self::filled($uuid), __('Visiteur anonyme'), null, false, $isReturning);
        }

        $label = $this->subjects->label($attribution->guard);
        $name = $this->subjects->name($attribution->guard, $attribution->id);

        return new SessionVisitor(
            id: $session->visitor_id,
            uuid: self::filled($uuid),
            name: $name ?? $label.' #'.$attribution->id,
            label: $name !== null && $name !== '' && $label !== '' ? $label : null,
            viaVisitor: $attribution->viaVisitor,
            isReturning: $isReturning,
        );
    }

    /**
     * @param  list<array{event: Event, children: list<Event>, seconds: int}>  $journey
     * @return list<JourneyStep>
     */
    private function steps(array $journey): array
    {
        $longest = 1;
        foreach ($journey as $step) {
            if ($step['event']->type === EventType::Pageview) {
                $longest = max($longest, $step['seconds']);
            }
        }

        return array_map(fn (array $step): JourneyStep => $this->step($step, $longest), $journey);
    }

    /**
     * @param  array{event: Event, children: list<Event>, seconds: int}  $step
     * @param  int  $longest  the seconds of the longest page, 1 at least
     */
    private function step(array $step, int $longest): JourneyStep
    {
        $event = $step['event'];
        $isPageview = $event->type === EventType::Pageview;

        return new JourneyStep(
            isPageview: $isPageview,
            isConversion: $event->type === EventType::Custom,
            route: $event->route,
            url: $event->url,
            label: self::labelOf($event),
            occurredAt: $event->occurred_at,
            duration: DurationLabel::for($step['seconds']),
            barPercent: $isPageview ? max(3, (int) round($step['seconds'] / $longest * 100)) : 0,
            children: array_map(
                fn (Event $child): JourneyEvent => new JourneyEvent($child->type === EventType::Custom, self::labelOf($child), $child->occurred_at),
                $step['children'],
            ),
        );
    }

    private function acquisition(Session $session): SessionAcquisition
    {
        return new SessionAcquisition(
            source: $session->source,
            icon: self::SOURCE_ICONS[strtolower((string) $session->source)] ?? 'globe-alt',
            description: SourceLabel::description($session->source),
            searchQuery: self::searchQuery($session->referrer),
            campaignTerm: self::filled($session->utm_term),
            landingRoute: $session->landing_route,
            landingUrl: $session->landing_url,
            referrer: self::filled($session->referrer),
            campaignParameters: self::campaignParameters($session),
        );
    }

    /**
     * The campaign parameters the landing address carried, by their label.
     *
     * @return array<string, string>
     */
    private static function campaignParameters(Session $session): array
    {
        $parameters = [];

        foreach ([
            __('Campagne') => $session->utm_campaign,
            __('Source UTM') => $session->utm_source,
            __('Support') => $session->utm_medium,
            __('Contenu') => $session->utm_content,
        ] as $label => $value) {
            if (self::filled($value) !== null) {
                $parameters[$label] = (string) $value;
            }
        }

        return $parameters;
    }

    /**
     * The query a search engine left in the referrer · almost never there, the
     * engines strip it. `utm_term` is an advertiser's parameter and is kept
     * apart rather than taken for what the visitor typed.
     */
    private static function searchQuery(?string $referrer): ?string
    {
        if (self::filled($referrer) === null) {
            return null;
        }

        parse_str((string) parse_url((string) $referrer, PHP_URL_QUERY), $parameters);
        $query = $parameters['q'] ?? $parameters['query'] ?? null;

        return is_string($query) ? self::filled($query) : null;
    }

    /**
     * The five longest pages, then the others together, each in its series colour.
     *
     * @param  array<array-key, int>  $timePerPage  page => seconds, longest first · a numeric page label comes back as an integer key
     * @return list<TimeShare>
     */
    private function timeShares(array $timePerPage): array
    {
        $segments = array_slice($timePerPage, 0, self::NAMED_PAGES, true);
        $others = array_sum(array_slice($timePerPage, self::NAMED_PAGES, null, true));

        if ($others > 0) {
            $segments[__('Autres')] = $others;
        }

        $shares = [];
        foreach ($segments as $label => $seconds) {
            $shares[] = new TimeShare((string) $label, $seconds, DurationLabel::for($seconds), ChartPalette::SERIES[count($shares)] ?? '--an-series-6');
        }

        return $shares;
    }

    /**
     * Whether this session had a step-by-step and lost part of it · more
     * counted as it happened than the rows still hold. Nothing is queried, the
     * rows are already read to draw the journey.
     *
     * @param  Collection<int, Event>  $events
     */
    private function detailWasErased(Session $session, Collection $events): bool
    {
        $kept = $events
            ->filter(fn (Event $event): bool => $event->type === EventType::Pageview || $event->type === EventType::Click)
            ->count();

        return $session->pageview_count + $session->click_count > $kept;
    }

    private static function labelOf(Event $event): string
    {
        return self::filled($event->target_text)
            ?? self::filled($event->name)
            ?? ($event->type === EventType::Click ? __('Clic') : __('Évènement'));
    }

    private static function deviceIcon(?string $type): string
    {
        return match (strtolower((string) $type)) {
            'mobile' => 'device-phone-mobile',
            'tablet' => 'device-tablet',
            'desktop' => 'computer-desktop',
            default => 'question-mark-circle',
        };
    }

    /** A name and its version on one line · null when both are missing. */
    private static function joined(?string $name, ?string $version): ?string
    {
        $joined = trim(($name ?? '').' '.($version ?? ''));

        return $joined !== '' ? $joined : null;
    }

    /** The value, or null when it holds nothing but blanks. */
    private static function filled(?string $value): ?string
    {
        return filled($value) ? $value : null;
    }
}
