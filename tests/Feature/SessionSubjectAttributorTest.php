<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Services\Dashboard\SessionSubjectAttributor;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\Fixtures\Models\TestClient;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

final class SessionSubjectAttributorTest extends TestCase
{
    use RefreshDatabase;

    private TestAdmin $admin;

    private SessionSubjectAttributor $attributor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
        $this->admin = TestAdmin::create([]);
        $this->attributor = new SessionSubjectAttributor;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function visitor(array $attributes = []): Visitor
    {
        return Visitor::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ], $attributes));
    }

    /**
     * Une session en base.
     *
     * `sessionRow` et non `session` · le banc HTTP de Laravel declare
     * `session()` en public, et la redefinir en prive est une erreur fatale au
     * chargement, pas un essai qui tombe.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function sessionRow(Visitor $visitor, array $attributes = []): Session
    {
        return Session::create(array_merge([
            'visitor_id' => $visitor->id,
            'started_at' => now(),
            'last_activity_at' => now(),
            'is_bot' => false,
            'pageview_count' => 1,
        ], $attributes));
    }

    // ── Le service ───────────────────────────────────────────────────────

    public function test_it_keeps_the_session_own_subject_when_it_was_authenticated(): void
    {
        $visitor = $this->visitor(['subject_type' => 'client', 'subject_id' => 7]);
        $session = $this->sessionRow($visitor, ['subject_type' => 'client', 'subject_id' => 7]);

        $attribution = $this->attributor->attribute([$session->load('visitor')])[$session->id];

        $this->assertSame('client', $attribution->guard);
        $this->assertSame(7, $attribution->id);
        $this->assertFalse($attribution->viaVisitor);
    }

    public function test_it_names_an_anonymous_session_after_its_visitor_stitched_subject(): void
    {
        $visitor = $this->visitor(['subject_type' => 'client', 'subject_id' => 7]);
        $this->sessionRow($visitor, ['subject_type' => 'client', 'subject_id' => 7]);
        $anonymous = $this->sessionRow($visitor);

        $attribution = $this->attributor->attribute([$anonymous->load('visitor')])[$anonymous->id];

        $this->assertSame('client', $attribution->guard);
        $this->assertSame(7, $attribution->id);
        $this->assertTrue($attribution->viaVisitor);
    }

    public function test_it_withholds_the_fallback_when_the_visitor_identified_sessions_disagree(): void
    {
        $visitor = $this->visitor(['subject_type' => 'client', 'subject_id' => 7]);
        $this->sessionRow($visitor, ['subject_type' => 'client', 'subject_id' => 7]);
        $this->sessionRow($visitor, ['subject_type' => 'lessor', 'subject_id' => 2]);
        $anonymous = $this->sessionRow($visitor);

        $this->assertArrayNotHasKey(
            $anonymous->id,
            $this->attributor->attribute([$anonymous->load('visitor')]),
        );
    }

    public function test_it_keeps_an_own_subject_even_when_the_visitor_is_ambiguous(): void
    {
        $visitor = $this->visitor(['subject_type' => 'client', 'subject_id' => 7]);
        $this->sessionRow($visitor, ['subject_type' => 'client', 'subject_id' => 7]);
        $lessorSession = $this->sessionRow($visitor, ['subject_type' => 'lessor', 'subject_id' => 2]);

        $attribution = $this->attributor->attribute([$lessorSession->load('visitor')])[$lessorSession->id];

        $this->assertSame('lessor', $attribution->guard);
        $this->assertSame(2, $attribution->id);
        $this->assertFalse($attribution->viaVisitor);
    }

    public function test_it_leaves_sessions_of_a_fully_anonymous_visitor_unattributed(): void
    {
        $anonymous = $this->sessionRow($this->visitor());

        $this->assertSame([], $this->attributor->attribute([$anonymous->load('visitor')]));
    }

    // ── Les écrans ───────────────────────────────────────────────────────

    public function test_it_shows_the_stitched_name_with_the_not_connected_hint_in_the_session_list(): void
    {
        $marie = TestClient::create(['first_name' => 'Marie', 'last_name' => 'Dupont']);
        $visitor = $this->visitor(['subject_type' => 'client', 'subject_id' => $marie->id]);
        $this->sessionRow($visitor);

        $this->actingAs($this->admin, 'admin')
            ->get(route('analytics.sessions'))
            ->assertSuccessful()
            ->assertSeeText('Marie Dupont')
            ->assertSeeText(__('Non connecté'));
    }

    public function test_it_names_the_visitor_on_the_detail_of_an_anonymous_session_and_flags_it_not_connected(): void
    {
        $marie = TestClient::create(['first_name' => 'Marie', 'last_name' => 'Dupont']);
        $visitor = $this->visitor(['subject_type' => 'client', 'subject_id' => $marie->id]);
        $session = $this->sessionRow($visitor);

        $this->actingAs($this->admin, 'admin')
            ->get(route('analytics.sessions.show', $session))
            ->assertSuccessful()
            ->assertSeeText('Marie Dupont')
            ->assertSeeText(__('Non connecté'))
            ->assertDontSeeText(__('Visiteur anonyme'));
    }

    public function test_it_keeps_the_session_detail_anonymous_when_the_visitor_is_unidentified(): void
    {
        $session = $this->sessionRow($this->visitor());

        $this->actingAs($this->admin, 'admin')
            ->get(route('analytics.sessions.show', $session))
            ->assertSuccessful()
            ->assertSeeText(__('Visiteur anonyme'));
    }

    public function test_it_marks_the_connected_sessions_on_the_visitor_detail(): void
    {
        $visitor = $this->visitor(['subject_type' => 'client', 'subject_id' => 7]);
        $this->sessionRow($visitor, ['subject_type' => 'client', 'subject_id' => 7]);
        $this->sessionRow($visitor);

        $this->actingAs($this->admin, 'admin')
            ->get(route('analytics.visitors.show', $visitor))
            ->assertSuccessful()
            ->assertSeeText(__('Connecté'));
    }
}
