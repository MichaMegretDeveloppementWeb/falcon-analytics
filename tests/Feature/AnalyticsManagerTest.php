<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Analytics;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\Fixtures\Models\TestClient;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class AnalyticsManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_safe_defaults_with_no_resolver_and_empty_identity_config(): void
    {
        $analytics = new Analytics;

        $this->assertNull($analytics->subject());
        $this->assertFalse($analytics->hasConsent());
        $this->assertFalse($analytics->isExcluded());
    }

    public function test_it_resolves_and_coerces_the_registered_subject_closure(): void
    {
        $analytics = new Analytics;
        $analytics->resolveSubjectUsing(fn () => ['type' => 'lessor', 'id' => '7']);

        $this->assertSame(['type' => 'lessor', 'id' => 7], $analytics->subject());
    }

    public function test_it_normalises_a_malformed_subject_to_null(): void
    {
        $analytics = new Analytics;
        $analytics->resolveSubjectUsing(fn () => ['wrong' => 'shape']);

        $this->assertNull($analytics->subject());
    }

    public function test_it_resolves_the_subject_from_the_configured_guards(): void
    {
        config(['analytics.identity.subject_guards' => ['client', 'lessor']]);
        $client = TestClient::create([]);
        $this->actingAs($client, 'client');

        $this->assertSame(['type' => 'client', 'id' => $client->id], (new Analytics)->subject());
    }

    public function test_it_ignores_guards_that_do_not_exist(): void
    {
        config(['analytics.identity.subject_guards' => ['ghost']]);

        $this->assertNull((new Analytics)->subject());
    }

    public function test_it_excludes_traffic_from_the_configured_guards(): void
    {
        config(['analytics.identity.exclude_guards' => ['admin']]);
        $admin = TestAdmin::create([]);
        $this->actingAs($admin, 'admin');

        $this->assertTrue((new Analytics)->isExcluded());
    }

    public function test_it_reads_consent_from_the_configured_cookie(): void
    {
        config(['analytics.identity.consent_cookie' => 'vd_consent_marketing']);
        request()->cookies->set('vd_consent_marketing', '1');

        $this->assertTrue((new Analytics)->hasConsent());
    }

    public function test_it_lets_a_consent_closure_take_precedence_over_config(): void
    {
        config(['analytics.identity.consent_cookie' => 'vd_consent_marketing']);
        $analytics = new Analytics;
        $analytics->consentUsing(fn () => true);

        $this->assertTrue($analytics->hasConsent());
    }
}
