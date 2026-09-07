<?php

use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

/*
 * How a screen reaches the browser.
 *
 * Until 2026-09-07 each screen was a full-page Livewire component mounted by
 * `Route::livewire`, naming its own layout through `->layout()`. It now goes
 * route → controller → thin view → component, the way falcon/booking has always
 * done it, and the host layout is extended rather than filled as a slot.
 *
 * The suite's forty-four route assertions already prove the screens answer.
 * What they cannot see is where the screen lands, because they run against the
 * package's own shell. These two do.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    View::addLocation(__DIR__.'/../Fixtures/views');

    config()->set('analytics.dashboard.layout', 'host-shell');
});

it('renders the screen inside the section of the host layout', function () {
    $response = $this->actingAs(TestAdmin::create(['email' => 'admin@example.test']), 'admin')
        ->get(route('analytics.overview'));

    /*
     * Both halves matter. The chrome alone would mean the section name is wrong
     * and the screen fell on the floor — silently, since Blade yields an empty
     * section without complaining. The screen alone would mean the layout was
     * never extended.
     *
     * The needle is taken from the body of the screen and nowhere else. The
     * screen's own name would not do: it is in the <title> too, so the
     * assertion held even with the section deliberately misnamed.
     */
    $response->assertOk()
        ->assertSee('chrome fourni par le gabarit')
        ->assertSee(__('Visiteurs et appareils'));
});

/**
 * The reason the conversion was worth doing.
 *
 * The error state used to carry a layout of its own, so a failed read replaced
 * the entire page. The controller decides the chrome now, and the component
 * only fills the section: what is lost is the panel, not the way out of it.
 */
it('keeps the host chrome when the screen fails to read its data', function () {
    Schema::drop('falcon_analytics_sessions');

    $response = $this->actingAs(TestAdmin::create(['email' => 'admin@example.test']), 'admin')
        ->get(route('analytics.sessions'));

    $response->assertOk()
        ->assertSee(__('Données indisponibles'))
        ->assertSee('chrome fourni par le gabarit');
});

/** The title is composed by the controller, before the screen renders. */
it('lets the controller name the browser tab', function () {
    $this->actingAs(TestAdmin::create(['email' => 'admin@example.test']), 'admin')
        ->get(route('analytics.realtime'))
        ->assertSee('<title>'.__('Temps réel').' · '.__('Analytics').'</title>', false);
});

/**
 * Two screens name the record they show, and that title moved out of the
 * component when the screens were converted. The suite visits both already, but
 * only asserts on the body — this is the half that changed hands.
 */
it('lets the controller name the tab after the record it shows', function () {
    $campaign = Campaign::create([
        'name' => 'Été 2026',
        'platform' => 'Meta',
        'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']],
    ]);

    $this->actingAs(TestAdmin::create(['email' => 'admin@example.test']), 'admin')
        ->get(route('marketing.campaigns.show', $campaign))
        ->assertSee('<title>Été 2026 · '.__('Marketing').'</title>', false);
});
