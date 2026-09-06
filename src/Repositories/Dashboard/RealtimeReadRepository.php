<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories\Dashboard;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Repositories\Concerns\ScopesSessionQueries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read model for the realtime screen. Every read here runs on EVERY poll tick,
 * so each one is bounded and rides an existing index (sessions last_activity_at,
 * events (type, occurred_at) / (name, occurred_at)): a read too heavy to poll
 * must be bounded further, never deferred. Bots excluded, like every dashboard
 * read.
 */
final class RealtimeReadRepository
{
    use ScopesSessionQueries;

    /**
     * Distinct visitors whose last activity is within the online window right
     * now (visitors, not sessions: two devices of one person count once).
     */
    public function onlineCount(CarbonImmutable $onlineSince, ?string $subjectType): int
    {
        return $this->activeSessions($onlineSince, $subjectType)->distinct()->count('visitor_id');
    }

    /**
     * Headline figures of the recent window: sessions active in the window
     * (started earlier but still active count too), distinct visitors, and page
     * views that occurred within the window.
     *
     * @return array{sessions: int, visitors: int, pageviews: int}
     */
    public function windowCounts(CarbonImmutable $since, ?string $subjectType): array
    {
        $sessions = $this->activeSessions($since, $subjectType)
            ->toBase()
            ->selectRaw('COUNT(*) as sessions, COUNT(DISTINCT visitor_id) as visitors')
            ->first();

        $pageviews = $this->windowEvents($since, $subjectType)
            ->where('type', EventType::Pageview)
            ->count();

        return [
            'sessions' => (int) ($sessions->sessions ?? 0),
            'visitors' => (int) ($sessions->visitors ?? 0),
            'pageviews' => $pageviews,
        ];
    }

    /**
     * Declared conversions fired within the window.
     *
     * @param  list<string>  $conversionNames
     */
    public function conversionsCount(CarbonImmutable $since, ?string $subjectType, array $conversionNames): int
    {
        if ($conversionNames === []) {
            return 0;
        }

        return $this->windowEvents($since, $subjectType)
            ->whereIn('name', $conversionNames)
            ->count();
    }

    /**
     * Page views per minute over the window, keyed by 'Y-m-d H:i'. Minutes
     * without traffic are absent; the caller zero-fills.
     *
     * @return array<string, int>
     */
    public function pageviewsPerMinute(CarbonImmutable $since, ?string $subjectType): array
    {
        $minute = $this->minuteExpression('occurred_at');

        return $this->windowEvents($since, $subjectType)
            ->where('type', EventType::Pageview)
            ->toBase()
            ->selectRaw("{$minute} as minute, COUNT(*) as total")
            ->groupBy(DB::raw($minute))
            ->get()
            ->mapWithKeys(fn (object $row): array => [(string) $row->minute => (int) $row->total])
            ->all();
    }

    /**
     * The latest stored events since the given instant, newest first,
     * hard-bounded, with their session and visitor loaded so the caller can
     * attribute and link each line without extra queries.
     *
     * @return Collection<int, Event>
     */
    public function activityFeed(CarbonImmutable $since, ?string $subjectType, int $limit): Collection
    {
        return $this->windowEvents($since, $subjectType)
            ->with('session.visitor:id,uuid,subject_type,subject_id')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * The sessions since the given instant, most recently active first,
     * hard-bounded, with their visitor loaded so the caller can attribute and
     * link each row without extra queries.
     *
     * @return Collection<int, Session>
     */
    public function recentSessions(CarbonImmutable $since, ?string $subjectType, int $limit): Collection
    {
        return $this->activeSessions($since, $subjectType)
            ->select(['id', 'visitor_id', 'browser_key', 'subject_type', 'subject_id', 'started_at', 'last_activity_at', 'device_type', 'country', 'city', 'pageview_count'])
            ->with('visitor:id,uuid,subject_type,subject_id')
            ->orderByDesc('last_activity_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Most viewed pages of the window by their real URL, top N.
     *
     * @return list<array{url: string, total: int}>
     */
    public function topPages(CarbonImmutable $since, ?string $subjectType, int $limit = 5): array
    {
        // `array_values` · le resultat est deja indexe depuis zero, mais son
        // type ne le dit pas et ce fichier declare des listes. Meme raison
        // partout ici.
        return array_values($this->windowEvents($since, $subjectType)
            ->where('type', EventType::Pageview)
            ->whereNotNull('url')
            ->toBase()
            ->selectRaw('url, COUNT(*) as total')
            ->groupBy('url')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => ['url' => (string) $row->url, 'total' => (int) $row->total])
            ->all());
    }

    /**
     * Sessions of the window grouped by acquisition source, top N.
     *
     * @return list<array{label: string, total: int}>
     */
    public function topSources(CarbonImmutable $since, ?string $subjectType, int $limit = 5): array
    {
        return $this->activeBreakdown($since, $subjectType, 'source', 'direct', $limit);
    }

    /**
     * Sessions of the window grouped by device type, top N.
     *
     * @return list<array{label: string, total: int}>
     */
    public function topDevices(CarbonImmutable $since, ?string $subjectType, int $limit = 5): array
    {
        return $this->activeBreakdown($since, $subjectType, 'device_type', '', $limit);
    }

    /**
     * Window sessions grouped by locality for the map, with how many of them
     * are online right now. Bounded to the busiest locations so the payload
     * never grows with traffic.
     *
     * @return list<array{city: string|null, country: string|null, latitude: float, longitude: float, total: int, online: int}>
     */
    public function mapPoints(CarbonImmutable $since, CarbonImmutable $onlineSince, int $limit = 200): array
    {
        return array_values($this->activeSessions($since, null)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->toBase()
            ->selectRaw(
                'city, country, latitude, longitude, COUNT(*) as total, SUM(CASE WHEN last_activity_at >= ? THEN 1 ELSE 0 END) as online',
                [$onlineSince->toDateTimeString()],
            )
            ->groupBy('city', 'country', 'latitude', 'longitude')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'city' => $row->city !== null ? (string) $row->city : null,
                'country' => $row->country !== null ? (string) $row->country : null,
                'latitude' => (float) $row->latitude,
                'longitude' => (float) $row->longitude,
                'total' => (int) $row->total,
                'online' => (int) $row->online,
            ])
            ->all());
    }

    /**
     * Window sessions the local GeoIP could not place (no coordinates), shown
     * as a discreet note under the map.
     */
    public function unlocatedCount(CarbonImmutable $since): int
    {
        return $this->activeSessions($since, null)->whereNull('latitude')->count();
    }

    /**
     * Non-bot sessions still active since the given instant, narrowed to a
     * subject type. Activity-based (not started_at): a session opened before
     * the window that is still alive belongs to the window.
     *
     * @return Builder<Session>
     */
    private function activeSessions(CarbonImmutable $since, ?string $subjectType): Builder
    {
        return Session::query()
            ->where('is_bot', false)
            ->where('last_activity_at', '>=', $since)
            ->when($subjectType !== null, fn (Builder $query): Builder => $query->where('subject_type', $subjectType));
    }

    /**
     * Non-bot events that occurred within the window, subject-narrowed through
     * the visitor (consistent with the events screen).
     *
     * @return Builder<Event>
     */
    private function windowEvents(CarbonImmutable $since, ?string $subjectType): Builder
    {
        return Event::query()
            ->where('occurred_at', '>=', $since)
            ->whereHas('session', fn (Builder $session): Builder => $session->where('is_bot', false))
            ->when($subjectType !== null, fn (Builder $query): Builder => $query->whereHas(
                'visitor',
                fn (Builder $visitor): Builder => $visitor->where('subject_type', $subjectType),
            ));
    }

    /**
     * Window sessions counted by a column, empties folded into a default label.
     *
     * `literal-string` sur la colonne · elle entre dans du SQL brut, et
     * `selectRaw()` n'accepte que des litteraux pour barrer l'injection. Les
     * deux appelants passent une constante.
     *
     * @param  literal-string  $column
     * @return list<array{label: string, total: int}>
     */
    private function activeBreakdown(CarbonImmutable $since, ?string $subjectType, string $column, string $default, int $limit): array
    {
        return array_values($this->activeSessions($since, $subjectType)
            ->toBase()
            ->selectRaw("{$column} as label, COUNT(*) as total")
            ->groupBy($column)
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'label' => trim((string) ($row->label ?? '')) !== '' ? (string) $row->label : $default,
                'total' => (int) $row->total,
            ])
            ->all());
    }
}
