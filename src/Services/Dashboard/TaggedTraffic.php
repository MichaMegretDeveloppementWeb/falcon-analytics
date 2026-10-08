<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\TaggedGroup;

/**
 * What a set of tagged groups adds up to · sessions in all, by day, and their
 * distinct visitors, which a group read from the totals leaves unknown.
 *
 * @internal
 */
final class TaggedTraffic
{
    /**
     * @param  list<TaggedGroup>  $groups
     * @return array{sessions: int, visitors: int|null, daily: array<string, int>, dailyVisitors: array<string, int>|null}
     */
    public static function of(array $groups): array
    {
        $sessions = 0;
        $known = true;
        /** @var array<int, true> $visitors */
        $visitors = [];
        /** @var array<string, int> $daily */
        $daily = [];
        /** @var array<string, array<int, true>> $visitorsByDay */
        $visitorsByDay = [];

        foreach ($groups as $group) {
            $sessions += $group->sessions;
            $daily[$group->day] = ($daily[$group->day] ?? 0) + $group->sessions;

            if ($group->visitorId === null) {
                $known = false;

                continue;
            }

            $visitors[$group->visitorId] = true;
            $visitorsByDay[$group->day][$group->visitorId] = true;
        }

        return [
            'sessions' => $sessions,
            'visitors' => $known ? count($visitors) : null,
            'daily' => $daily,
            'dailyVisitors' => $known ? array_map(count(...), $visitorsByDay) : null,
        ];
    }

    /**
     * One row per id · a campaign, an ad.
     *
     * @param  array<int, list<TaggedGroup>>  $groupsById
     * @return array<int, array{sessions: int, visitors: int|null}>
     */
    public static function rows(array $groupsById): array
    {
        $rows = [];

        foreach ($groupsById as $id => $groups) {
            $traffic = self::of($groups);
            $rows[$id] = ['sessions' => $traffic['sessions'], 'visitors' => $traffic['visitors']];
        }

        return $rows;
    }
}
