<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Support\UrlRedactor;
use Falcon\Analytics\Tests\TestCase;

final class UrlRedactorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('analytics.privacy.redact_query_params', ['token', 'email', 'password']);
    }

    public function test_it_redacts_denylisted_params_and_keeps_tracking_params(): void
    {
        $this->assertSame(
            'https://x.test/p?token=redacted&utm_source=meta&keep=1',
            (new UrlRedactor)->redact('https://x.test/p?token=secret&utm_source=meta&keep=1'),
        );
    }

    public function test_it_matches_the_denylist_case_insensitively_and_preserves_the_key_case(): void
    {
        $this->assertSame(
            'https://x.test/p?Token=redacted',
            (new UrlRedactor)->redact('https://x.test/p?Token=secret'),
        );
    }

    public function test_it_leaves_the_url_untouched_when_no_denylisted_param_is_present(): void
    {
        $this->assertSame(
            'https://x.test/p?utm_source=meta&gclid=abc',
            (new UrlRedactor)->redact('https://x.test/p?utm_source=meta&gclid=abc'),
        );
    }

    public function test_it_leaves_urls_without_a_query_empty_or_null_untouched(): void
    {
        $redactor = new UrlRedactor;

        $this->assertSame('https://x.test/p', $redactor->redact('https://x.test/p'));
        $this->assertSame('', $redactor->redact(''));
        $this->assertNull($redactor->redact(null));
    }

    public function test_it_does_nothing_when_the_denylist_is_empty(): void
    {
        config()->set('analytics.privacy.redact_query_params', []);

        $this->assertSame(
            'https://x.test/p?token=secret',
            (new UrlRedactor)->redact('https://x.test/p?token=secret'),
        );
    }
}
