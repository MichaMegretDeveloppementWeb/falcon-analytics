<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Services\VisitorIdentityResolver;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

final class VisitorIdentityTest extends TestCase
{
    private VisitorIdentityResolver $identity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->identity = new VisitorIdentityResolver;
    }

    public function test_it_issues_a_persistent_cookie_uuid_when_consent_is_granted(): void
    {
        $uuid = $this->identity->resolve(Request::create('/', 'POST'), true);

        $this->assertTrue(Str::isUuid($uuid));
        $this->assertSame($uuid, Cookie::queued('fa_vid')?->getValue());
    }

    public function test_it_reuses_an_existing_valid_cookie_without_re_queuing(): void
    {
        $existing = (string) Str::uuid();
        $request = Request::create('/', 'POST', cookies: ['fa_vid' => $existing]);

        $this->assertSame($existing, $this->identity->resolve($request, true));
        $this->assertFalse(Cookie::hasQueued('fa_vid'));
    }

    public function test_it_regenerates_when_the_cookie_is_not_a_valid_uuid(): void
    {
        $request = Request::create('/', 'POST', cookies: ['fa_vid' => 'tampered']);

        $uuid = $this->identity->resolve($request, true);

        $this->assertNotSame('tampered', $uuid);
        $this->assertTrue(Str::isUuid($uuid));
    }

    public function test_it_stores_a_session_scoped_uuid_when_consent_is_absent(): void
    {
        $session = app('session')->driver();
        $request = Request::create('/', 'POST');
        $request->setLaravelSession($session);

        $uuid = $this->identity->resolve($request, false);

        $this->assertSame($session->get('fa_vid'), $uuid);
        $this->assertTrue(Str::isUuid($uuid));
        $this->assertFalse(Cookie::hasQueued('fa_vid'));
    }

    public function test_it_reuses_the_session_uuid_on_subsequent_calls(): void
    {
        $session = app('session')->driver();
        $request = Request::create('/', 'POST');
        $request->setLaravelSession($session);

        $this->assertSame(
            $this->identity->resolve($request, false),
            $this->identity->resolve($request, false),
        );
    }
}
