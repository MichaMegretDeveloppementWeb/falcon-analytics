<?php

use Carbon\CarbonImmutable;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Services\Dashboard\SessionSubjectAttributor;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\Fixtures\Models\TestClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function seedAttributionVisitor(array $attrs = []): Visitor
{
    return Visitor::create(array_merge([
        'uuid' => (string) Str::uuid(),
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ], $attrs));
}

function seedAttributionSession(Visitor $visitor, array $attrs = []): Session
{
    return Session::create(array_merge([
        'visitor_id' => $visitor->id,
        'started_at' => now(),
        'last_activity_at' => now(),
        'is_bot' => false,
        'pageview_count' => 1,
    ], $attrs));
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
    $this->admin = TestAdmin::create([]);
    $this->attributor = new SessionSubjectAttributor;
});

// ── Service ─────────────────────────────────────────

it('keeps the session own subject when it was authenticated', function () {
    $visitor = seedAttributionVisitor(['subject_type' => 'client', 'subject_id' => 7]);
    $session = seedAttributionSession($visitor, ['subject_type' => 'client', 'subject_id' => 7]);

    $attribution = $this->attributor->attribute([$session->load('visitor')])[$session->id];

    expect($attribution->guard)->toBe('client')
        ->and($attribution->id)->toBe(7)
        ->and($attribution->viaVisitor)->toBeFalse();
});

it('names an anonymous session after its visitor stitched subject', function () {
    $visitor = seedAttributionVisitor(['subject_type' => 'client', 'subject_id' => 7]);
    seedAttributionSession($visitor, ['subject_type' => 'client', 'subject_id' => 7]);
    $anonymous = seedAttributionSession($visitor);

    $attribution = $this->attributor->attribute([$anonymous->load('visitor')])[$anonymous->id];

    expect($attribution->guard)->toBe('client')
        ->and($attribution->id)->toBe(7)
        ->and($attribution->viaVisitor)->toBeTrue();
});

it('withholds the fallback when the visitor identified sessions disagree', function () {
    $visitor = seedAttributionVisitor(['subject_type' => 'client', 'subject_id' => 7]);
    seedAttributionSession($visitor, ['subject_type' => 'client', 'subject_id' => 7]);
    seedAttributionSession($visitor, ['subject_type' => 'lessor', 'subject_id' => 2]);
    $anonymous = seedAttributionSession($visitor);

    $attributions = $this->attributor->attribute([$anonymous->load('visitor')]);

    expect($attributions)->not->toHaveKey($anonymous->id);
});

it('keeps an own subject even when the visitor is ambiguous', function () {
    $visitor = seedAttributionVisitor(['subject_type' => 'client', 'subject_id' => 7]);
    seedAttributionSession($visitor, ['subject_type' => 'client', 'subject_id' => 7]);
    $lessorSession = seedAttributionSession($visitor, ['subject_type' => 'lessor', 'subject_id' => 2]);

    $attribution = $this->attributor->attribute([$lessorSession->load('visitor')])[$lessorSession->id];

    expect($attribution->guard)->toBe('lessor')
        ->and($attribution->id)->toBe(2)
        ->and($attribution->viaVisitor)->toBeFalse();
});

it('leaves sessions of a fully anonymous visitor unattributed', function () {
    $anonymous = seedAttributionSession(seedAttributionVisitor());

    expect($this->attributor->attribute([$anonymous->load('visitor')]))->toBe([]);
});

// ── Pages ───────────────────────────────────────────

it('shows the stitched name with the not-connected hint in the session list', function () {
    $marie = TestClient::create(['first_name' => 'Marie', 'last_name' => 'Dupont']);
    $visitor = seedAttributionVisitor(['subject_type' => 'client', 'subject_id' => $marie->id]);
    seedAttributionSession($visitor);

    $this->actingAs($this->admin, 'admin')
        ->get(route('analytics.sessions'))
        ->assertSuccessful()
        ->assertSeeText('Marie Dupont')
        ->assertSeeText(__('Non connecté'));
});

it('names the visitor on the detail of an anonymous session and flags it not connected', function () {
    $marie = TestClient::create(['first_name' => 'Marie', 'last_name' => 'Dupont']);
    $visitor = seedAttributionVisitor(['subject_type' => 'client', 'subject_id' => $marie->id]);
    $session = seedAttributionSession($visitor);

    $this->actingAs($this->admin, 'admin')
        ->get(route('analytics.sessions.show', $session))
        ->assertSuccessful()
        ->assertSeeText('Marie Dupont')
        ->assertSeeText(__('Non connecté'))
        ->assertDontSeeText(__('Visiteur anonyme'));
});

it('keeps the session detail anonymous when the visitor is unidentified', function () {
    $session = seedAttributionSession(seedAttributionVisitor());

    $this->actingAs($this->admin, 'admin')
        ->get(route('analytics.sessions.show', $session))
        ->assertSuccessful()
        ->assertSeeText(__('Visiteur anonyme'));
});

it('marks the connected sessions on the visitor detail', function () {
    $visitor = seedAttributionVisitor(['subject_type' => 'client', 'subject_id' => 7]);
    seedAttributionSession($visitor, ['subject_type' => 'client', 'subject_id' => 7]);
    seedAttributionSession($visitor);

    $this->actingAs($this->admin, 'admin')
        ->get(route('analytics.visitors.show', $visitor))
        ->assertSuccessful()
        ->assertSeeText(__('Connecté'));
});
