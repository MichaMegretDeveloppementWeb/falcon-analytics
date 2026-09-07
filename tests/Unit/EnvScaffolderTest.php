<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Unit;

use Falcon\Analytics\Support\EnvScaffolder;
use PHPUnit\Framework\TestCase;

final class EnvScaffolderTest extends TestCase
{
    /**
     * Les groupes que l'installateur passerait au greffon.
     *
     * `envGroups` et non `groups` · PHPUnit declare `groups()` final sur son
     * `TestCase`, et la redefinir est une erreur fatale au chargement, pas un
     * essai qui tombe.
     *
     * @return list<array{comment: list<string>, entries: array<string, string>}>
     */
    private function envGroups(): array
    {
        return [
            [
                'comment' => ['Master switch.'],
                'entries' => ['ANALYTICS_ENABLED' => 'true'],
            ],
            [
                'comment' => ['GeoIP licence key.', 'Second comment line.'],
                'entries' => ['ANALYTICS_GEOIP_LICENSE_KEY' => '', 'ANALYTICS_GEOIP_DATABASE' => ''],
            ],
        ];
    }

    public function test_it_appends_every_missing_key_under_the_package_header_with_its_group_comments(): void
    {
        $block = EnvScaffolder::appendableBlock("APP_NAME=Test\n", $this->envGroups());

        $this->assertStringContainsString('# --- Falcon Analytics', $block);
        $this->assertStringContainsString('# Master switch.', $block);
        $this->assertStringContainsString('# Second comment line.', $block);
        $this->assertStringContainsString('ANALYTICS_ENABLED=true', $block);
        $this->assertStringContainsString('ANALYTICS_GEOIP_LICENSE_KEY=', $block);
        $this->assertStringContainsString('ANALYTICS_GEOIP_DATABASE=', $block);
        $this->assertStringStartsWith("\n", $block);
    }

    public function test_it_returns_an_empty_block_when_every_key_is_already_present(): void
    {
        $contents = "ANALYTICS_ENABLED=false\nANALYTICS_GEOIP_LICENSE_KEY=abc\nANALYTICS_GEOIP_DATABASE=/db.mmdb\n";

        $this->assertSame('', EnvScaffolder::appendableBlock($contents, $this->envGroups()));
    }

    public function test_it_appends_only_the_missing_keys_and_skips_fully_present_groups_with_their_comments(): void
    {
        $block = EnvScaffolder::appendableBlock(
            "ANALYTICS_ENABLED=true\nANALYTICS_GEOIP_LICENSE_KEY=abc\n",
            $this->envGroups(),
        );

        $this->assertStringNotContainsString('ANALYTICS_ENABLED', $block);
        $this->assertStringNotContainsString('# Master switch.', $block);
        $this->assertStringContainsString('# GeoIP licence key.', $block);
        $this->assertStringContainsString('ANALYTICS_GEOIP_DATABASE=', $block);
        $this->assertStringNotContainsString('ANALYTICS_GEOIP_LICENSE_KEY=', $block);
    }

    public function test_it_does_not_treat_a_longer_key_name_as_already_present(): void
    {
        $block = EnvScaffolder::appendableBlock(
            "ANALYTICS_ENABLED_LEGACY=true\n",
            [['comment' => [], 'entries' => ['ANALYTICS_ENABLED' => 'true']]],
        );

        $this->assertStringContainsString('ANALYTICS_ENABLED=true', $block);
    }
}
