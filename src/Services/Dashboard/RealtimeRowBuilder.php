<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\Dashboard;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Realtime\FeedEntry;
use Falcon\Analytics\DTOs\Dashboard\Realtime\RecentVisitorRow;
use Falcon\Analytics\DTOs\Dashboard\SessionSubjectAttribution;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Services\SubjectResolver;
use Falcon\Analytics\Support\DeviceLabel;
use Illuminate\Database\Eloquent\Collection;

/**
 * Prepares the two lists of the realtime board, the recent visitors and the
 * live feed, from the rows already read · the names of both come in one query
 * per guard.
 *
 * @internal
 */
final readonly class RealtimeRowBuilder
{
    public function __construct(
        private SessionSubjectAttributor $attributor,
        private SubjectResolver $subjects,
    ) {}

    /**
     * @param  Collection<int, Session>  $recentSessions  one per visitor, their visitor loaded
     * @param  Collection<int, Event>  $feed  their session and its visitor loaded
     * @param  list<string>  $conversionNames
     * @param  array<string, string>  $eventLabels  the declared events' labels, by name
     * @return array{recentVisitors: list<RecentVisitorRow>, feed: list<FeedEntry>}
     */
    public function build(Collection $recentSessions, Collection $feed, CarbonImmutable $onlineSince, array $conversionNames, array $eventLabels): array
    {
        $names = $this->namesBySession($recentSessions->concat($feed->pluck('session')->filter())->unique('id'));
        $conversions = array_flip($conversionNames);

        return [
            'recentVisitors' => array_values($recentSessions->map(
                fn (Session $session): RecentVisitorRow => self::recentVisitor($session, $names[$session->id] ?? null, $onlineSince),
            )->all()),
            'feed' => array_values($feed->map(
                fn (Event $event): FeedEntry => self::entry($event, $names[$event->session_id] ?? null, isset($conversions[$event->name ?? '']), $eventLabels),
            )->all()),
        ];
    }

    private static function recentVisitor(Session $session, ?string $name, CarbonImmutable $onlineSince): RecentVisitorRow
    {
        return new RecentVisitorRow(
            sessionId: $session->id,
            name: $name ?? __('Visiteur #:id', ['id' => $session->visitor_id]),
            isOnline: $session->last_activity_at->greaterThanOrEqualTo($onlineSince),
            deviceIcon: DeviceLabel::icon($session->device_type),
            lastSeen: self::timeOf($session->last_activity_at),
            city: filled($session->city) ? $session->city : null,
        );
    }

    /**
     * @param  array<string, string>  $eventLabels
     */
    private static function entry(Event $event, ?string $name, bool $isConversion, array $eventLabels): FeedEntry
    {
        return new FeedEntry(
            id: $event->id,
            sessionId: $event->session_id,
            icon: self::iconOf($event, $isConversion),
            isConversion: $isConversion,
            action: self::actionOf($event, $eventLabels),
            url: $event->type === EventType::Pageview && filled($event->url) ? $event->url : null,
            name: $name ?? __('Visiteur #:id', ['id' => $event->visitor_id]),
            occurredAt: self::timeOf($event->occurred_at),
        );
    }

    /**
     * The name each attributed session is shown under, by session.
     *
     * @param  Collection<int, Session>  $sessions
     * @return array<int, string>
     */
    private function namesBySession(Collection $sessions): array
    {
        $attributions = $this->attributor->attribute($sessions);
        $shown = $this->subjects->shownNames(array_values(array_map(
            static fn (SessionSubjectAttribution $attribution): array => [$attribution->guard, $attribution->id],
            $attributions,
        )));

        return array_map(
            static fn (SessionSubjectAttribution $attribution): string => $shown[$attribution->guard.':'.$attribution->id]->name,
            $attributions,
        );
    }

    private static function iconOf(Event $event, bool $isConversion): string
    {
        return match (true) {
            $isConversion => 'check-circle',
            $event->type === EventType::Pageview => 'document-text',
            $event->type === EventType::Click => 'cursor-arrow-rays',
            default => 'bolt',
        };
    }

    /**
     * @param  array<string, string>  $eventLabels
     */
    private static function actionOf(Event $event, array $eventLabels): string
    {
        return match (true) {
            $event->type === EventType::Pageview => __('Page vue'),
            filled($event->name) => $eventLabels[$event->name] ?? $event->name,
            filled($event->target_text) => $event->target_text,
            default => __('Clic'),
        };
    }

    /** The time, and its day when it is not today. */
    private static function timeOf(CarbonImmutable $moment): string
    {
        return $moment->isToday() ? $moment->format('H:i') : $moment->translatedFormat('j M, H:i');
    }
}
