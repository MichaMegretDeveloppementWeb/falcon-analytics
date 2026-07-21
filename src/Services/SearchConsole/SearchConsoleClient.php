<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\SearchConsole;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Models\SearchConsoleConnection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Read-only client for the Search Console API, working on the stored
 * connection with transparent token refresh: the property list (attach step)
 * and the Search Analytics rows (daily sync).
 */
final class SearchConsoleClient
{
    private const API_BASE = 'https://www.googleapis.com/webmasters/v3';

    /** The API's own per-request maximum. */
    public const ROW_LIMIT = 25000;

    /**
     * The page size only deviates from the API maximum in tests, where paging
     * over thousands of fake rows would be pure waste.
     */
    public function __construct(
        private readonly SearchConsoleAuth $auth,
        private readonly int $rowLimit = self::ROW_LIMIT,
    ) {}

    /**
     * Every day+query row of the window, following the API's pagination until
     * a short page. One call per page, not per day, so the initial backfill
     * stays within quota.
     *
     * @return list<array{date: string, query: string, clicks: int, impressions: int, position: float}>
     */
    public function queryRows(SearchConsoleConnection $connection, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = [];
        $startRow = 0;

        do {
            $page = $this->queryPage($connection, $from, $to, $startRow);

            foreach ($page as $row) {
                /** @var array{keys?: list<string>, clicks?: int|float, impressions?: int|float, position?: int|float} $row */
                $keys = $row['keys'] ?? [];

                if (count($keys) < 2) {
                    continue;
                }

                $rows[] = [
                    'date' => (string) $keys[0],
                    'query' => mb_substr((string) $keys[1], 0, 255),
                    'clicks' => (int) ($row['clicks'] ?? 0),
                    'impressions' => (int) ($row['impressions'] ?? 0),
                    'position' => round((float) ($row['position'] ?? 0), 2),
                ];
            }

            $startRow += count($page);
        } while (count($page) === $this->rowLimit);

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function queryPage(SearchConsoleConnection $connection, CarbonImmutable $from, CarbonImmutable $to, int $startRow): array
    {
        $response = Http::withToken($this->auth->accessTokenFor($connection))
            ->timeout(60)
            ->post(self::API_BASE.'/sites/'.rawurlencode((string) $connection->property).'/searchAnalytics/query', [
                'startDate' => $from->toDateString(),
                'endDate' => $to->toDateString(),
                'dimensions' => ['date', 'query'],
                'rowLimit' => $this->rowLimit,
                'startRow' => $startRow,
                'type' => 'web',
            ]);

        if ($response->failed()) {
            throw new RuntimeException("Search Analytics query failed: HTTP {$response->status()}");
        }

        /** @var list<array<string, mixed>> */
        return (array) $response->json('rows', []);
    }

    /**
     * The verified properties the authorising account can read, for the
     * attach-a-property step. Unverified entries are filtered out: GSC only
     * serves data for properties the owner actually holds.
     *
     * @return list<array{site_url: string, permission: string}>
     */
    public function listProperties(SearchConsoleConnection $connection): array
    {
        $response = Http::withToken($this->auth->accessTokenFor($connection))
            ->timeout(30)
            ->get(self::API_BASE.'/sites');

        if ($response->failed()) {
            throw new RuntimeException("Search Console sites list failed: HTTP {$response->status()}");
        }

        /** @var list<array{siteUrl?: string, permissionLevel?: string}> $entries */
        $entries = (array) $response->json('siteEntry', []);

        $properties = [];

        foreach ($entries as $entry) {
            $siteUrl = (string) ($entry['siteUrl'] ?? '');
            $permission = (string) ($entry['permissionLevel'] ?? '');

            if ($siteUrl === '' || $permission === 'siteUnverifiedUser') {
                continue;
            }

            $properties[] = ['site_url' => $siteUrl, 'permission' => $permission];
        }

        usort($properties, fn (array $a, array $b): int => strcmp($a['site_url'], $b['site_url']));

        return $properties;
    }
}
