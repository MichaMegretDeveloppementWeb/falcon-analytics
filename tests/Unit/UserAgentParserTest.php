<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Unit;

use Falcon\Analytics\Support\UserAgentParser;
use PHPUnit\Framework\TestCase;

final class UserAgentParserTest extends TestCase
{
    private UserAgentParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new UserAgentParser;
    }

    public function test_it_returns_an_empty_result_for_a_missing_user_agent(): void
    {
        $info = $this->parser->parse(null);

        $this->assertFalse($info->isBot);
        $this->assertNull($info->browser);
        $this->assertNull($info->os);
        $this->assertNull($info->deviceType);

        $this->assertNull($this->parser->parse('')->browser);
    }

    public function test_it_parses_a_desktop_browser_user_agent(): void
    {
        $info = $this->parser->parse(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:128.0) Gecko/20100101 Firefox/128.0'
        );

        $this->assertFalse($info->isBot);
        $this->assertSame('Firefox', $info->browser);
        $this->assertSame('Windows', $info->os);
        $this->assertSame('desktop', $info->deviceType);
    }

    public function test_it_parses_a_mobile_browser_user_agent(): void
    {
        $info = $this->parser->parse(
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1'
        );

        $this->assertFalse($info->isBot);
        $this->assertSame('smartphone', $info->deviceType);
        $this->assertSame('Apple', $info->deviceBrand);
        $this->assertSame('iOS', $info->os);
    }

    public function test_it_flags_a_bot_and_leaves_device_fields_null(): void
    {
        $info = $this->parser->parse(
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'
        );

        $this->assertTrue($info->isBot);
        $this->assertNull($info->browser);
        $this->assertNull($info->deviceType);
    }

    public function test_it_truncates_oversized_parsed_fields_to_the_column_lengths(): void
    {
        $info = $this->parser->parse(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Gecko/20100101 Firefox/'.str_repeat('1', 40).'.0'
        );

        $this->assertSame('Firefox', $info->browser);
        $this->assertSame(str_repeat('1', 30), $info->browserVersion);
    }
}
