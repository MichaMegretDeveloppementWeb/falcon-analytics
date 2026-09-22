<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Unit;

use Falcon\Analytics\Support\DurationLabel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DurationLabelTest extends TestCase
{
    /** @return array<string, array{int|float, string}> */
    public static function durations(): array
    {
        return [
            'nothing' => [0, "0\u{00A0}s"],
            'under a minute' => [59, "59\u{00A0}s"],
            'a round minute' => [60, "1\u{00A0}min"],
            'a minute and a second' => [61, "1\u{00A0}min\u{00A0}1\u{00A0}s"],
            'an hour, in minutes' => [3600, "60\u{00A0}min"],
            'past an hour' => [3725, "62\u{00A0}min\u{00A0}5\u{00A0}s"],
            'an average rounded up to the minute' => [59.6, "1\u{00A0}min"],
            'an average rounded down' => [1.4, "1\u{00A0}s"],
        ];
    }

    #[DataProvider('durations')]
    public function test_a_duration_reads_in_minutes_and_seconds(int|float $seconds, string $expected): void
    {
        $this->assertSame($expected, DurationLabel::for($seconds));
    }
}
