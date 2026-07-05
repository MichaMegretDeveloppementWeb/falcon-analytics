<?php

use Falcon\Analytics\Analytics;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\Fixtures\Models\TestClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns safe defaults with no resolver and empty identity config', function () {
    $analytics = new Analytics;

    expect($analytics->subject())->toBeNull()
        ->and($analytics->hasConsent())->toBeFalse()
        ->and($analytics->isExcluded())->toBeFalse();
});

it('resolves and coerces the registered subject closure', function () {
    $analytics = new Analytics;
    $analytics->resolveSubjectUsing(fn () => ['type' => 'lessor', 'id' => '7']);

    expect($analytics->subject())->toBe(['type' => 'lessor', 'id' => 7]);
});

it('normalises a malformed subject to null', function () {
    $analytics = new Analytics;
    $analytics->resolveSubjectUsing(fn () => ['wrong' => 'shape']);

    expect($analytics->subject())->toBeNull();
});

it('resolves the subject from the configured guards', function () {
    config(['analytics.identity.subject_guards' => ['client', 'lessor']]);
    $client = TestClient::create([]);
    $this->actingAs($client, 'client');

    expect((new Analytics)->subject())->toBe(['type' => 'client', 'id' => $client->id]);
});

it('ignores guards that do not exist', function () {
    config(['analytics.identity.subject_guards' => ['ghost']]);

    expect((new Analytics)->subject())->toBeNull();
});

it('excludes traffic from the configured guards', function () {
    config(['analytics.identity.exclude_guards' => ['admin']]);
    $admin = TestAdmin::create([]);
    $this->actingAs($admin, 'admin');

    expect((new Analytics)->isExcluded())->toBeTrue();
});

it('reads consent from the configured cookie', function () {
    config(['analytics.identity.consent_cookie' => 'vd_consent_marketing']);
    request()->cookies->set('vd_consent_marketing', '1');

    expect((new Analytics)->hasConsent())->toBeTrue();
});

it('lets a consent closure take precedence over config', function () {
    config(['analytics.identity.consent_cookie' => 'vd_consent_marketing']);
    $analytics = new Analytics;
    $analytics->consentUsing(fn () => true);

    expect($analytics->hasConsent())->toBeTrue();
});
