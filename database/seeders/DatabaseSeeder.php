<?php

declare(strict_types=1);

namespace Falcon\Analytics\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Everything the package lays to fill the screens, in order · the campaigns
 * first, so the visits that come through their links are credited to them.
 *
 * The door is `analytics:seed`, which refuses outside development. Run by its
 * own name, this chain has no such guard:
 *
 *     php artisan db:seed --class="Falcon\Analytics\Database\Seeders\DatabaseSeeder"
 */
final class DatabaseSeeder extends Seeder
{
    public function __construct(
        private readonly DemoMarketingSeeder $marketing,
        private readonly DemoTrafficSeeder $traffic,
    ) {}

    /**
     * @param  (callable(string, array<string, int>): void)|null  $report  told of each section as it lands
     * @return array<string, array<string, int>> what was written, section by section
     */
    public function run(int $days = 30, int $visits = 600, ?callable $report = null): array
    {
        $written = [];

        foreach ([
            'Marketing' => fn (): array => $this->marketing->run(),
            'Trafic' => fn (): array => $this->traffic->run($days, $visits),
        ] as $section => $lay) {
            $written[$section] = $lay();

            if ($report !== null) {
                $report($section, $written[$section]);
            }
        }

        return $written;
    }
}
