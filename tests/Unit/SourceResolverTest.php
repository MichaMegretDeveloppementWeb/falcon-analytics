<?php

use Falcon\Analytics\Services\SourceResolver;

beforeEach(function () {
    $this->resolver = new SourceResolver;
});

it('classifies direct traffic', function () {
    expect($this->resolver->resolve('https://vantadrive.ch/', null, 'vantadrive.ch')->source)->toBe('direct');
});

it('treats an internal referrer as direct', function () {
    expect($this->resolver->resolve('https://vantadrive.ch/x', 'https://www.vantadrive.ch/', 'vantadrive.ch')->source)->toBe('direct');
});

it('classifies organic search', function () {
    expect($this->resolver->resolve('https://vantadrive.ch/', 'https://www.google.com/search?q=x', 'vantadrive.ch')->source)->toBe('organic');
});

it('classifies social', function () {
    expect($this->resolver->resolve('https://vantadrive.ch/', 'https://facebook.com/', 'vantadrive.ch')->source)->toBe('social');
});

it('classifies referral', function () {
    expect($this->resolver->resolve('https://vantadrive.ch/', 'https://someblog.example/', 'vantadrive.ch')->source)->toBe('referral');
});

it('derives paid source and utm parameters, utm winning over the referrer', function () {
    $acquisition = $this->resolver->resolve(
        'https://vantadrive.ch/?utm_source=meta&utm_medium=cpc&utm_campaign=spring',
        'https://facebook.com/',
        'vantadrive.ch',
    );

    expect($acquisition->source)->toBe('paid')
        ->and($acquisition->utmSource)->toBe('meta')
        ->and($acquisition->utmMedium)->toBe('cpc')
        ->and($acquisition->utmCampaign)->toBe('spring');
});

it('classifies Google Ads auto-tagging (gclid) as paid even without utm and a google referrer', function () {
    expect($this->resolver->resolve('https://vantadrive.ch/?gclid=abc123', 'https://www.google.com/', 'vantadrive.ch')->source)->toBe('paid');
});

it('classifies Microsoft Ads (msclkid) as paid', function () {
    expect($this->resolver->resolve('https://vantadrive.ch/?msclkid=abc', null, 'vantadrive.ch')->source)->toBe('paid');
});

it('classifies paid_social utm_medium as paid', function () {
    expect($this->resolver->resolve('https://vantadrive.ch/?utm_source=meta&utm_medium=paid_social', 'https://facebook.com/', 'vantadrive.ch')->source)->toBe('paid');
});

it('does not treat fbclid as paid (organic facebook clicks carry it too)', function () {
    expect($this->resolver->resolve('https://vantadrive.ch/?fbclid=xyz', 'https://facebook.com/', 'vantadrive.ch')->source)->toBe('social');
});

it('returns null utm when absent', function () {
    $acquisition = $this->resolver->resolve('https://vantadrive.ch/', null, 'vantadrive.ch');

    expect($acquisition->utmSource)->toBeNull()
        ->and($acquisition->utmCampaign)->toBeNull();
});

it('truncates oversized utm values to the column length', function () {
    $long = str_repeat('a', 400);
    $acquisition = $this->resolver->resolve('https://vantadrive.ch/?utm_source='.$long.'&utm_campaign='.$long, null, 'vantadrive.ch');

    expect($acquisition->utmSource)->toBe(str_repeat('a', 150))
        ->and($acquisition->utmCampaign)->toBe(str_repeat('a', 150));
});
