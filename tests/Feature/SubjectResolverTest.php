<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Services\SubjectResolver;
use Falcon\Analytics\Tests\Fixtures\Models\TestClient;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class SubjectResolverTest extends TestCase
{
    use RefreshDatabase;

    private SubjectResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new SubjectResolver;

        config()->set('analytics.identity.subjects', [
            'client' => ['label' => 'Client', 'name' => ['first_name', 'last_name']],
        ]);
    }

    public function test_it_returns_the_configured_label_falling_back_to_the_humanised_guard_name(): void
    {
        $this->assertSame('Client', $this->resolver->label('client'));
        $this->assertSame('Lessor', $this->resolver->label('lessor'));
    }

    public function test_it_resolves_the_display_name_from_the_guard_model_derived_from_the_auth_config(): void
    {
        $client = TestClient::create(['first_name' => 'Marie', 'last_name' => 'Dupont']);

        $this->assertSame('Marie Dupont', $this->resolver->name('client', $client->id));
    }

    public function test_it_batch_resolves_several_names_in_a_single_map(): void
    {
        $a = TestClient::create(['first_name' => 'Alice', 'last_name' => 'Martin']);
        $b = TestClient::create(['first_name' => 'Bob', 'last_name' => 'Durand']);

        $this->assertSame(
            [$a->id => 'Alice Martin', $b->id => 'Bob Durand'],
            $this->resolver->names('client', [$a->id, $b->id]),
        );
    }

    public function test_it_matches_subject_ids_by_a_single_word_on_any_name_column(): void
    {
        $rene = TestClient::create(['first_name' => 'René', 'last_name' => 'Roy']);
        TestClient::create(['first_name' => 'Marie', 'last_name' => 'Dupont']);

        $this->assertSame([$rene->id], $this->resolver->matchIds('client', 'Roy'));
    }

    public function test_it_matches_subject_ids_by_a_full_name_spanning_several_columns_in_any_order(): void
    {
        $rene = TestClient::create(['first_name' => 'René', 'last_name' => 'Roy']);
        TestClient::create(['first_name' => 'Marie', 'last_name' => 'Dupont']);

        $this->assertSame([$rene->id], $this->resolver->matchIds('client', 'René Roy'));
        $this->assertSame([$rene->id], $this->resolver->matchIds('client', 'Roy René'));
    }

    public function test_it_matches_no_subject_id_for_a_blank_term(): void
    {
        TestClient::create(['first_name' => 'René', 'last_name' => 'Roy']);

        $this->assertSame([], $this->resolver->matchIds('client', '   '));
    }

    public function test_it_falls_back_to_the_configured_fallback_columns_when_the_name_columns_are_empty(): void
    {
        config()->set('analytics.identity.subjects.client', [
            'label' => 'Client', 'name' => ['last_name'], 'fallback' => ['first_name'],
        ]);

        $client = TestClient::create(['first_name' => 'Solo', 'last_name' => '']);

        $this->assertSame('Solo', $this->resolver->name('client', $client->id));
    }

    public function test_it_falls_back_to_label_and_id_when_the_name_cannot_be_resolved(): void
    {
        $this->assertSame('Client #999999', $this->resolver->display('client', 999999));
        $this->assertSame('Client', $this->resolver->display('client', null));
    }

    public function test_it_returns_null_for_a_guard_without_a_subject_config_never_throwing(): void
    {
        $this->assertNull($this->resolver->name('admin', 1));
    }
}
