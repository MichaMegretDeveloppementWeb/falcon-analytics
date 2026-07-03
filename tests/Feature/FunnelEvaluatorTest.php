<?php

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Funnels\Funnel;
use Falcon\Analytics\Funnels\FunnelEvaluator;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Create a visitor whose events happen in the given order. A string is a custom
 * event (matched by name); ['route' => x] is a pageview (matched by route).
 */
function funnelJourney(array $events, ?string $subjectType = null, bool $isBot = false): void
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
        $attrs = [
            'session_id' => $session->id,
            'visitor_id' => $visitor->id,
            // In the past (events always precede "now"), ordered by their rank.
            'occurred_at' => now()->subMinutes(10)->addSeconds($order),
        ];

        if (is_array($spec)) {
            $attrs['type'] = EventType::Pageview;
            $attrs['route'] = $spec['route'];
        } else {
            $attrs['type'] = EventType::Custom;
            $attrs['name'] = $spec;
        }

        Event::create($attrs);
    }
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
    $this->evaluator = app(FunnelEvaluator::class);
    $this->period = Period::ofDays(30);
    $this->funnel = (new Funnel('test', 'Test'))
        ->step('Vue', 1.0, event: 'ViewContent')
        ->step('Lead', 3.0, event: 'Lead')
        ->step('Inscription', 5.0, event: 'CompleteRegistration');
});

it('counts sequential step completion and never lets a step out-count the previous one', function () {
    funnelJourney(['ViewContent', 'Lead', 'CompleteRegistration']); // reaches step 3
    funnelJourney(['ViewContent', 'Lead']);                          // reaches step 2
    funnelJourney(['ViewContent']);                                  // reaches step 1
    funnelJourney(['Lead', 'CompleteRegistration']);                 // never enters (no view)
    funnelJourney(['CompleteRegistration', 'ViewContent', 'Lead']);  // reaches step 2 (register too early)

    $report = $this->evaluator->evaluate($this->funnel, $this->period, null);

    expect($report->entrants)->toBe(4)
        ->and($report->steps[0]->visitors)->toBe(4)
        ->and($report->steps[1]->visitors)->toBe(3)
        ->and($report->steps[2]->visitors)->toBe(1)
        ->and($report->steps[1]->conversionFromStart)->toBe(0.75)
        ->and($report->steps[2]->conversionFromStart)->toBe(0.25)
        ->and($report->steps[1]->conversionFromPrevious)->toBe(0.75)
        ->and($report->steps[2]->conversionFromPrevious)->toBe(1 / 3)
        ->and($report->steps[2]->score)->toBe(5.0)
        ->and($report->totalScore)->toBe(18.0);
});

it('scopes a funnel to the subject identity of the visitor, keeping anonymous early steps', function () {
    funnelJourney(['ViewContent', 'Lead'], subjectType: 'client');
    funnelJourney(['ViewContent', 'Lead']); // anonymous

    $all = $this->evaluator->evaluate($this->funnel, $this->period, null);
    $clients = $this->evaluator->evaluate($this->funnel, $this->period, 'client');

    expect($all->steps[0]->visitors)->toBe(2)
        ->and($clients->steps[0]->visitors)->toBe(1)
        ->and($clients->steps[1]->visitors)->toBe(1);
});

it('matches pageview steps by route and event steps by name', function () {
    $funnel = (new Funnel('reg', 'Inscription'))
        ->step('Page', 1.0, route: 'reg.page')
        ->step('Envoi', 5.0, event: 'reg.submit');

    funnelJourney([['route' => 'reg.page'], 'reg.submit']); // reaches step 2
    funnelJourney(['reg.submit']);                          // never enters (no page)
    funnelJourney([['route' => 'reg.page']]);               // reaches step 1

    $report = $this->evaluator->evaluate($funnel, $this->period, null);

    expect($report->steps[0]->visitors)->toBe(2)
        ->and($report->steps[1]->visitors)->toBe(1);
});

it('excludes bot sessions from funnel counts', function () {
    funnelJourney(['ViewContent', 'Lead']);                  // human
    funnelJourney(['ViewContent', 'Lead'], isBot: true);     // bot, must not count

    $report = $this->evaluator->evaluate($this->funnel, $this->period, null);

    expect($report->entrants)->toBe(1)
        ->and($report->steps[0]->visitors)->toBe(1)
        ->and($report->steps[1]->visitors)->toBe(1);
});

it('ignores events outside the period', function () {
    funnelJourney(['ViewContent', 'Lead']);

    $report = $this->evaluator->evaluate($this->funnel, Period::ofDays(7), null);
    $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00:00'));
    $reportLater = $this->evaluator->evaluate($this->funnel, Period::ofDays(30), null);

    expect($report->steps[0]->visitors)->toBe(1)
        ->and($reportLater->steps[0]->visitors)->toBe(0);
});
