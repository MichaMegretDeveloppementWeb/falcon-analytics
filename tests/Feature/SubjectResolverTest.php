<?php

use Falcon\Analytics\Services\SubjectResolver;
use Falcon\Analytics\Tests\Fixtures\Models\TestClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->resolver = new SubjectResolver;
    config()->set('analytics.identity.subjects', [
        'client' => ['label' => 'Client', 'name' => ['first_name', 'last_name']],
    ]);
});

it('returns the configured label, falling back to the humanised guard name', function () {
    expect($this->resolver->label('client'))->toBe('Client')
        ->and($this->resolver->label('lessor'))->toBe('Lessor');
});

it('resolves the display name from the guard model derived from the auth config', function () {
    $client = TestClient::create(['first_name' => 'Marie', 'last_name' => 'Dupont']);

    expect($this->resolver->name('client', $client->id))->toBe('Marie Dupont');
});

it('batch-resolves several names in a single map', function () {
    $a = TestClient::create(['first_name' => 'Alice', 'last_name' => 'Martin']);
    $b = TestClient::create(['first_name' => 'Bob', 'last_name' => 'Durand']);

    expect($this->resolver->names('client', [$a->id, $b->id]))
        ->toBe([$a->id => 'Alice Martin', $b->id => 'Bob Durand']);
});

it('matches subject ids by a single word on any name column', function () {
    $rene = TestClient::create(['first_name' => 'René', 'last_name' => 'Roy']);
    TestClient::create(['first_name' => 'Marie', 'last_name' => 'Dupont']);

    expect($this->resolver->matchIds('client', 'Roy'))->toBe([$rene->id]);
});

it('matches subject ids by a full name spanning several columns, in any order', function () {
    $rene = TestClient::create(['first_name' => 'René', 'last_name' => 'Roy']);
    TestClient::create(['first_name' => 'Marie', 'last_name' => 'Dupont']);

    expect($this->resolver->matchIds('client', 'René Roy'))->toBe([$rene->id])
        ->and($this->resolver->matchIds('client', 'Roy René'))->toBe([$rene->id]);
});

it('matches no subject id for a blank term', function () {
    TestClient::create(['first_name' => 'René', 'last_name' => 'Roy']);

    expect($this->resolver->matchIds('client', '   '))->toBe([]);
});

it('falls back to the configured fallback columns when the name columns are empty', function () {
    config()->set('analytics.identity.subjects.client', [
        'label' => 'Client', 'name' => ['last_name'], 'fallback' => ['first_name'],
    ]);

    $client = TestClient::create(['first_name' => 'Solo', 'last_name' => '']);

    expect($this->resolver->name('client', $client->id))->toBe('Solo');
});

it('falls back to label and id when the name cannot be resolved', function () {
    expect($this->resolver->display('client', 999999))->toBe('Client #999999')
        ->and($this->resolver->display('client', null))->toBe('Client');
});

it('returns null for a guard without a subject config, never throwing', function () {
    expect($this->resolver->name('admin', 1))->toBeNull();
});
