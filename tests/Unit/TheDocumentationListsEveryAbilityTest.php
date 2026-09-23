<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Unit;

use Falcon\Analytics\Enums\Authorization\Ability;
use PHPUnit\Framework\TestCase;

/**
 * The table a host reads in docs/autorisation.md says exactly what the
 * enumeration holds: every case, its name, and the ability it follows.
 */
final class TheDocumentationListsEveryAbilityTest extends TestCase
{
    public function test_the_table_of_abilities_matches_the_enumeration(): void
    {
        $expected = [];

        foreach (Ability::cases() as $ability) {
            $expected[] = [$ability->name, $ability->value, $ability->parent()->name ?? '·'];
        }

        $this->assertSame($expected, $this->documentedRows());
    }

    /** @return list<array{0: string, 1: string, 2: string}> */
    private function documentedRows(): array
    {
        $documentation = (string) file_get_contents(__DIR__.'/../../docs/autorisation.md');

        preg_match_all('/^\| `(\w+)` \| `([a-z.-]+)` \| [^|]+ \| (?:`(\w+)`|·[^|]*) \|$/mu', $documentation, $rows, PREG_SET_ORDER);

        return array_map(fn (array $row): array => [$row[1], $row[2], ($row[3] ?? '') !== '' ? $row[3] : '·'], $rows);
    }
}
