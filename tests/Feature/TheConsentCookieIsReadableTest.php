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
 * **every visitor stays session-scoped without a word**. Nothing fails, no
 * screen is empty, and the only symptom is a number that stays lower than it
 * should.
 *
 * It used to be a line the host added to `bootstrap/app.php`, and of the four
 * things asked of a host it was the one whose omission said the least. The
 * package now does it for itself, the way the kit already does for its own
 * three cookies.
 */
final class TheConsentCookieIsReadableTest extends TestCase
{
    /**
     * `except()` appends to a static list that outlives one test, so each
     * assertion reads the list rather than comparing it whole.
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

    /**
     * Nothing is exempted while no cookie is named, and nothing needs to be:
     * with no consent cookie the package never promotes a visitor.
     */
    public function test_it_exempts_nothing_when_no_cookie_is_named(): void
    {
        $before = $this->exempted();

        $this->bootWith(null);

        $this->assertSame($before, $this->exempted());
    }

    /**
     * The half that matters, and the reason the exemption exists: consent read
     * back from a cookie the browser wrote in clear.
     */
    public function test_consent_is_read_from_the_cookie_the_browser_wrote(): void
    {
        $this->bootWith('cookie_consent');

        request()->cookies->set('cookie_consent', '1');

        $this->assertTrue(Analytics::hasConsent());
    }

    /** And any other value is a refusal, not a grant. */
    public function test_any_other_value_is_not_consent(): void
    {
        $this->bootWith('cookie_consent');

        request()->cookies->set('cookie_consent', 'true');

        $this->assertFalse(Analytics::hasConsent());
    }
}
