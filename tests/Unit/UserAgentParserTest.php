<?php

use Falcon\Analytics\Support\UserAgentParser;

beforeEach(function () {
    $this->parser = new UserAgentParser;
});

it('returns an empty result for a missing user agent', function () {
    $info = $this->parser->parse(null);

    expect($info->isBot)->toBeFalse()
        ->and($info->browser)->toBeNull()
        ->and($info->os)->toBeNull()
        ->and($info->deviceType)->toBeNull();

    expect($this->parser->parse('')->browser)->toBeNull();
});

it('parses a desktop browser user agent', function () {
    $info = $this->parser->parse(
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:128.0) Gecko/20100101 Firefox/128.0'
    );

    expect($info->isBot)->toBeFalse()
        ->and($info->browser)->toBe('Firefox')
        ->and($info->os)->toBe('Windows')
        ->and($info->deviceType)->toBe('desktop');
});

it('parses a mobile browser user agent', function () {
    $info = $this->parser->parse(
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1'
    );

    expect($info->isBot)->toBeFalse()
        ->and($info->deviceType)->toBe('smartphone')
        ->and($info->deviceBrand)->toBe('Apple')
        ->and($info->os)->toBe('iOS');
});

it('flags a bot and leaves device fields null', function () {
    $info = $this->parser->parse(
        'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'
    );

    expect($info->isBot)->toBeTrue()
        ->and($info->browser)->toBeNull()
        ->and($info->deviceType)->toBeNull();
});

it('truncates oversized parsed fields to the column lengths', function () {
    $info = $this->parser->parse(
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Gecko/20100101 Firefox/'.str_repeat('1', 40).'.0'
    );

    expect($info->browser)->toBe('Firefox')
        ->and($info->browserVersion)->toBe(str_repeat('1', 30));
});
