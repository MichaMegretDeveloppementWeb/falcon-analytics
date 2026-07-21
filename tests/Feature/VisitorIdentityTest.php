<?php

use Falcon\Analytics\Services\VisitorIdentityResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->identity = new VisitorIdentityResolver;
});

it('issues a persistent cookie uuid when consent is granted', function () {
    $uuid = $this->identity->resolve(Request::create('/', 'POST'), true);

    expect(Str::isUuid($uuid))->toBeTrue()
        ->and(Cookie::queued('fa_vid')?->getValue())->toBe($uuid);
});

it('reuses an existing valid cookie without re-queuing', function () {
    $existing = (string) Str::uuid();
    $request = Request::create('/', 'POST', cookies: ['fa_vid' => $existing]);

    $uuid = $this->identity->resolve($request, true);

    expect($uuid)->toBe($existing)
        ->and(Cookie::hasQueued('fa_vid'))->toBeFalse();
});

it('regenerates when the cookie is not a valid uuid', function () {
    $request = Request::create('/', 'POST', cookies: ['fa_vid' => 'tampered']);

    $uuid = $this->identity->resolve($request, true);

    expect($uuid)->not->toBe('tampered')
        ->and(Str::isUuid($uuid))->toBeTrue();
});

it('stores a session-scoped uuid when consent is absent', function () {
    $session = app('session')->driver();
    $request = Request::create('/', 'POST');
    $request->setLaravelSession($session);

    $uuid = $this->identity->resolve($request, false);

    expect($uuid)->toBe($session->get('fa_vid'))
        ->and(Str::isUuid($uuid))->toBeTrue()
        ->and(Cookie::hasQueued('fa_vid'))->toBeFalse();
});

it('reuses the session uuid on subsequent calls', function () {
    $session = app('session')->driver();
    $request = Request::create('/', 'POST');
    $request->setLaravelSession($session);

    expect($this->identity->resolve($request, false))
        ->toBe($this->identity->resolve($request, false));
});
