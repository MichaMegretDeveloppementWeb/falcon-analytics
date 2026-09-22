<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Unit;

use Falcon\Analytics\Support\UrlConditions;
use PHPUnit\Framework\TestCase;

final class UrlConditionsTest extends TestCase
{
    public function test_a_form_opens_on_one_empty_row_when_nothing_is_recorded(): void
    {
        $this->assertSame([UrlConditions::BLANK], UrlConditions::toEdit(null));
        $this->assertSame([UrlConditions::BLANK], UrlConditions::toEdit([]));
        $this->assertSame([['param' => 'src', 'value' => 'meta']], UrlConditions::toEdit([['param' => 'src', 'value' => 'meta']]));
    }

    public function test_what_is_saved_is_trimmed_in_order_and_without_holes(): void
    {
        $rows = [
            0 => ['param' => ' src ', 'value' => ' meta '],
            2 => ['param' => 'creative', 'value' => '   '],
            5 => ['param' => 'utm_campaign', 'value' => 'ete'],
        ];

        $this->assertSame(
            [['param' => 'src', 'value' => 'meta'], ['param' => 'utm_campaign', 'value' => 'ete']],
            UrlConditions::cleaned($rows),
        );
    }

    public function test_both_forms_validate_a_row_alike(): void
    {
        $this->assertSame(
            ['adConditions', 'adConditions.*.param', 'adConditions.*.value'],
            array_keys(UrlConditions::rules('adConditions')),
        );
        $this->assertSame(
            array_values(UrlConditions::rules('adConditions')),
            array_values(UrlConditions::rules('campaignConditions')),
        );
    }
}
