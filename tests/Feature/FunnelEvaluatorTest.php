<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Funnels\Funnel;
use Falcon\Analytics\Funnels\FunnelBranch;
use Falcon\Analytics\Funnels\FunnelEvaluator;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class FunnelEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private FunnelEvaluator $evaluator;

    private Period $period;

    private Funnel $funnel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
        $this->evaluator = app(FunnelEvaluator::class);
        $this->period = Period::ofDays(30);
        $this->funnel = (new Funnel('test', 'Test'))
            ->step('Vue', 1.0, event: 'ViewContent')
            ->step('Lead', 3.0, event: 'Lead')
            ->step('Inscription', 5.0, event: 'CompleteRegistration');
    }

    /**
     * Un visiteur dont les evenements arrivent dans l'ordre donne.
     *
     * Une chaine est un evenement personnalise, apparie par son nom ;
     * `['route' => x]` est une page vue, appariee par sa route.
     *
     * @param  list<string|array{route: string}>  $events
     */
    private function journey(array $events, ?string $subjectType = null, bool $isBot = false): void
    {
        $visitor = Visitor::create([
            'uuid' => (string) Str::uuid(),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'subject_type' => $subjectType,
            'subject_id' => $subjectType !== null ? 1 : null,
        ]);

        $session = Session::create([
            'visitor_id' => $visitor->id,
            'started_at' => now(),
            'last_activity_at' => now(),
            'is_bot' => $isBot,
        ]);

        foreach ($events as $order => $spec) {
            $attributes = [
                'session_id' => $session->id,
                'visitor_id' => $visitor->id,
                // Dans le passe, les evenements precedant toujours « maintenant »,
                // et ranges par leur rang.
                'occurred_at' => now()->subMinutes(10)->addSeconds($order),
            ];

            if (is_array($spec)) {
                $attributes['type'] = EventType::Pageview;
                $attributes['route'] = $spec['route'];
            } else {
                $attributes['type'] = EventType::Custom;
                $attributes['name'] = $spec;
            }

            Event::create($attributes);
        }
    }

    public function test_it_counts_sequential_step_completion_and_never_lets_a_step_out_count_the_previous_one(): void
    {
        $this->journey(['ViewContent', 'Lead', 'CompleteRegistration']); // atteint l'etape 3
        $this->journey(['ViewContent', 'Lead']);                         // atteint l'etape 2
        $this->journey(['ViewContent']);                                 // atteint l'etape 1
        $this->journey(['Lead', 'CompleteRegistration']);                // n'entre jamais, pas de vue
        $this->journey(['CompleteRegistration', 'ViewContent', 'Lead']); // etape 2, inscription trop tot

        $report = $this->evaluator->evaluate($this->funnel, $this->period, null);

        $this->assertSame(4, $report->entrants);
        $this->assertSame(4, $report->steps[0]->visitors);
        $this->assertSame(3, $report->steps[1]->visitors);
        $this->assertSame(1, $report->steps[2]->visitors);
        $this->assertSame(0.75, $report->steps[1]->conversionFromStart);
        $this->assertSame(0.25, $report->steps[2]->conversionFromStart);
        $this->assertSame(0.75, $report->steps[1]->conversionFromPrevious);
        $this->assertSame(1 / 3, $report->steps[2]->conversionFromPrevious);
        $this->assertSame(5.0, $report->steps[2]->score);
        $this->assertSame(18.0, $report->totalScore);
    }

    public function test_it_scopes_a_funnel_to_the_subject_identity_keeping_anonymous_early_steps(): void
    {
        $this->journey(['ViewContent', 'Lead'], subjectType: 'client');
        $this->journey(['ViewContent', 'Lead']); // anonyme

        $all = $this->evaluator->evaluate($this->funnel, $this->period, null);
        $clients = $this->evaluator->evaluate($this->funnel, $this->period, 'client');

        $this->assertSame(2, $all->steps[0]->visitors);
        $this->assertSame(1, $clients->steps[0]->visitors);
        $this->assertSame(1, $clients->steps[1]->visitors);
    }

    public function test_it_matches_pageview_steps_by_route_and_event_steps_by_name(): void
    {
        $funnel = (new Funnel('reg', 'Inscription'))
            ->step('Page', 1.0, route: 'reg.page')
            ->step('Envoi', 5.0, event: 'reg.submit');

        $this->journey([['route' => 'reg.page'], 'reg.submit']); // atteint l'etape 2
        $this->journey(['reg.submit']);                          // n'entre jamais, pas de page
        $this->journey([['route' => 'reg.page']]);               // atteint l'etape 1

        $report = $this->evaluator->evaluate($funnel, $this->period, null);

        $this->assertSame(2, $report->steps[0]->visitors);
        $this->assertSame(1, $report->steps[1]->visitors);
    }

    public function test_it_excludes_bot_sessions_from_funnel_counts(): void
    {
        $this->journey(['ViewContent', 'Lead']);                 // humain
        $this->journey(['ViewContent', 'Lead'], isBot: true);    // robot, ne doit pas compter

        $report = $this->evaluator->evaluate($this->funnel, $this->period, null);

        $this->assertSame(1, $report->entrants);
        $this->assertSame(1, $report->steps[0]->visitors);
        $this->assertSame(1, $report->steps[1]->visitors);
    }

    public function test_it_ignores_events_outside_the_period(): void
    {
        $this->journey(['ViewContent', 'Lead']);

        $report = $this->evaluator->evaluate($this->funnel, Period::ofDays(7), null);
        $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00:00'));
        $reportLater = $this->evaluator->evaluate($this->funnel, Period::ofDays(30), null);

        $this->assertSame(1, $report->steps[0]->visitors);
        $this->assertSame(0, $reportLater->steps[0]->visitors);
    }

    // ── Branches parallèles ──────────────────────────────────────────────
    //
    // Un jalon est souvent joignable par plus d'un chemin : un formulaire
    // ouvert depuis l'une ou l'autre de deux pages, une inscription menée par
    // l'un ou l'autre de deux parcours. Posées en étapes consécutives, elles
    // se liraient « passé par l'une, PUIS par l'autre » et rendraient des
    // zéros. Les branches se tiennent à la même profondeur, et le rapport dit
    // par où les visiteurs sont entrés.

    public function test_it_advances_a_branched_step_whichever_branch_the_visitor_takes(): void
    {
        $funnel = (new Funnel('branched', 'Branched'))
            ->step('Vue', 1.0, event: 'ViewContent')
            ->step('Formulaire', 8.0, anyOf: [
                FunnelBranch::event('Questionnaire', 'form.quiz'),
                FunnelBranch::route('Contact', 'contact'),
            ])
            ->step('Envoi', 100.0, event: 'Lead');

        $this->journey(['ViewContent', 'form.quiz', 'Lead']);            // par le questionnaire
        $this->journey(['ViewContent', ['route' => 'contact'], 'Lead']); // par la page de contact
        $this->journey(['ViewContent', 'form.quiz']);                    // s'arrete au formulaire
        $this->journey(['ViewContent', 'Lead']);                         // saute l'etape

        $report = $this->evaluator->evaluate($funnel, $this->period, null);

        $this->assertSame(4, $report->entrants);
        $this->assertSame(3, $report->steps[1]->visitors);
        $this->assertSame(2, $report->steps[2]->visitors);
    }

    public function test_it_reports_how_many_visitors_came_through_each_branch(): void
    {
        $funnel = (new Funnel('branched', 'Branched'))
            ->step('Formulaire', 8.0, anyOf: [
                FunnelBranch::event('Questionnaire', 'form.quiz'),
                FunnelBranch::route('Contact', 'contact'),
            ]);

        $this->journey(['form.quiz']);
        $this->journey(['form.quiz']);
        $this->journey([['route' => 'contact']]);

        $report = $this->evaluator->evaluate($funnel, $this->period, null);

        $this->assertSame(['Questionnaire' => 2, 'Contact' => 1], $report->steps[0]->branches);
    }

    /**
     * Une branche que personne n'a prise doit paraitre quand meme · un zero est
     * une lecture, et une ligne qui disparait ressemble a un defaut plutot qu'a
     * une absence.
     */
    public function test_it_keeps_an_untaken_branch_in_the_report_at_zero(): void
    {
        $funnel = (new Funnel('branched', 'Branched'))
            ->step('Formulaire', 8.0, anyOf: [
                FunnelBranch::event('Questionnaire', 'form.quiz'),
                FunnelBranch::route('Contact', 'contact'),
            ]);

        $this->journey(['form.quiz']);

        $report = $this->evaluator->evaluate($funnel, $this->period, null);

        $this->assertSame(['Questionnaire' => 1, 'Contact' => 0], $report->steps[0]->branches);
    }

    public function test_it_counts_a_visitor_once_even_when_several_branches_match(): void
    {
        $funnel = (new Funnel('branched', 'Branched'))
            ->step('Formulaire', 8.0, anyOf: [
                FunnelBranch::event('Questionnaire', 'form.quiz'),
                FunnelBranch::route('Contact', 'contact'),
            ])
            ->step('Envoi', 100.0, event: 'Lead');

        $this->journey(['form.quiz', ['route' => 'contact'], 'Lead']);

        $report = $this->evaluator->evaluate($funnel, $this->period, null);

        $this->assertSame(1, $report->steps[0]->visitors);
        $this->assertSame(['Questionnaire' => 1, 'Contact' => 0], $report->steps[0]->branches);
        $this->assertSame(1, $report->steps[1]->visitors);
    }

    public function test_it_leaves_the_branches_empty_on_a_step_declared_without_them(): void
    {
        $this->journey(['ViewContent']);

        $report = $this->evaluator->evaluate($this->funnel, $this->period, null);

        $this->assertSame([], $report->steps[0]->branches);
    }

    public function test_it_refuses_a_step_that_mixes_branches_with_a_single_matcher(): void
    {
        $this->assertThrows(
            fn () => (new Funnel('bad', 'Bad'))->step('Mixte', 1.0, event: 'a', anyOf: [
                FunnelBranch::event('Un', 'b'),
                FunnelBranch::event('Deux', 'c'),
            ]),
            InvalidArgumentException::class,
        );
    }

    public function test_it_refuses_a_branched_step_with_a_single_branch(): void
    {
        $this->assertThrows(
            fn () => (new Funnel('bad', 'Bad'))->step('Seule', 1.0, anyOf: [
                FunnelBranch::event('Un', 'b'),
            ]),
            InvalidArgumentException::class,
        );
    }
}
