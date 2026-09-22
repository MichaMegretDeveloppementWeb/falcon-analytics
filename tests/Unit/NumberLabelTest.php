<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Unit;

use Falcon\Analytics\Support\NumberLabel;
use Falcon\Analytics\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class NumberLabelTest extends TestCase
{
    /** @return array<string, array{string, int|float, int, string}> */
    public static function numbers(): array
    {
        return [
            'nothing' => ['fr', 0, 0, '0'],
            'under a thousand' => ['fr', 999, 0, '999'],
            'the thousands kept together' => ['fr', 12345, 0, "12\u{202F}345"],
            'a decimal' => ['fr', 12345.6, 1, "12\u{202F}345,6"],
            'a half rounded up, as before' => ['fr', 2.25, 1, '2,3'],
            'another half rounded up' => ['fr', 12.45, 1, '12,5'],
            'a negative' => ['fr', -3, 0, '-3'],
            'in English' => ['en', 12345.6, 1, '12,345.6'],
        ];
    }

    #[DataProvider('numbers')]
    public function test_a_number_reads_in_the_site_language(string $locale, int|float $number, int $decimals, string $expected): void
    {
        app()->setLocale($locale);

        $this->assertSame($expected, NumberLabel::for($number, $decimals));
    }

    /** @return array<string, array{string, int|float, int, string}> */
    public static function percents(): array
    {
        return [
            'none' => ['fr', 0, 0, "0\u{00A0}%"],
            'a whole share' => ['fr', 45, 0, "45\u{00A0}%"],
            'a rate to the tenth' => ['fr', 45.3, 1, "45,3\u{00A0}%"],
            'a half rounded up' => ['fr', 2.25, 1, "2,3\u{00A0}%"],
            'in English' => ['en', 45.3, 1, '45.3%'],
        ];
    }

    #[DataProvider('percents')]
    public function test_a_share_reads_as_a_percentage(string $locale, int|float $percent, int $decimals, string $expected): void
    {
        app()->setLocale($locale);

        $this->assertSame($expected, NumberLabel::percent($percent, $decimals));
    }

    public function test_changing_language_mid_request_changes_the_writing(): void
    {
        app()->setLocale('fr');
        $this->assertSame("1\u{202F}234", NumberLabel::for(1234));

        app()->setLocale('en');
        $this->assertSame('1,234', NumberLabel::for(1234));
    }
}
