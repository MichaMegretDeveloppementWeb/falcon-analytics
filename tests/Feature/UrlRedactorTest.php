<?php

use Falcon\Analytics\Support\UrlRedactor;

beforeEach(function () {
    config()->set('analytics.privacy.redact_query_params', ['token', 'email', 'password']);
});

it('redacts denylisted params and keeps tracking params', function () {
    expect((new UrlRedactor)->redact('https://x.test/p?token=secret&utm_source=meta&keep=1'))
        ->toBe('https://x.test/p?token=redacted&utm_source=meta&keep=1');
});

it('matches the denylist case-insensitively and preserves the key case', function () {
    expect((new UrlRedactor)->redact('https://x.test/p?Token=secret'))
        ->toBe('https://x.test/p?Token=redacted');
});

it('leaves the url untouched when no denylisted param is present', function () {
    expect((new UrlRedactor)->redact('https://x.test/p?utm_source=meta&gclid=abc'))
        ->toBe('https://x.test/p?utm_source=meta&gclid=abc');
});

it('leaves urls without a query, empty or null untouched', function () {
    $redactor = new UrlRedactor;

    expect($redactor->redact('https://x.test/p'))->toBe('https://x.test/p')
        ->and($redactor->redact(''))->toBe('')
        ->and($redactor->redact(null))->toBeNull();
});

it('does nothing when the denylist is empty', function () {
    config()->set('analytics.privacy.redact_query_params', []);

    expect((new UrlRedactor)->redact('https://x.test/p?token=secret'))
        ->toBe('https://x.test/p?token=secret');
});
