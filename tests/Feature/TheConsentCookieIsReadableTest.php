<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\AnalyticsServiceProvider;
use Falcon\Analytics\Facades\Analytics;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Cookie\Middleware\EncryptCookies;
use ReflectionClass;

/**
 * A consent cookie the host does not have to exempt itself.
 *
 * A consent banner writes that cookie in JavaScript, so in clear. Read back
 * through `EncryptCookies` it decrypts to `null`, consent is never seen, and
 * every visitor stays session-scoped without a word. The package exempts the
 * cookie itself, as the kit does for its own.
 */
final class TheConsentCookieIsReadableTest extends TestCase
{
    /**
     * `except()` appends to a static list that outlives one test, so the
     * assertions look for a name or compare with the list read beforehand.
     *
     * @return list<string>
     */
    private function exempted(): array
    {
        /** @var list<string> $names */
        $names = (new ReflectionClass(EncryptCookies::class))->getStaticPropertyValue('neverEncrypt');

        return $names;
    }

    /** The package boots with the host's configuration already in hand. */
    private function bootWith(?string $cookie): void
    {
        config(['analytics.identity.consent_cookie' => $cookie]);

        $this->app->register(AnalyticsServiceProvider::class, force: true);
    }

    public function test_it_exempts_the_cookie_the_host_named(): void
    {
        $this->bootWith('cookie_consent');

        $this->assertContains('cookie_consent', $this->exempted());
    }

    /** With no consent cookie the package never promotes a visitor, so nothing needs exempting. */
    public function test_it_exempts_nothing_when_no_cookie_is_named(): void
    {
        $before = $this->exempted();

        $this->bootWith(null);

        $this->assertSame($before, $this->exempted());
    }

    public function test_consent_is_read_from_the_cookie_the_browser_wrote(): void
    {
        $this->bootWith('cookie_consent');

        request()->cookies->set('cookie_consent', '1');

        $this->assertTrue(Analytics::hasConsent());
    }

    public function test_any_other_value_is_not_consent(): void
    {
        $this->bootWith('cookie_consent');

        request()->cookies->set('cookie_consent', 'true');

        $this->assertFalse(Analytics::hasConsent());
    }
}
