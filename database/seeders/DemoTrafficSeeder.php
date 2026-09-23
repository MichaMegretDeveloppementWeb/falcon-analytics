<?php

declare(strict_types=1);

namespace Falcon\Analytics\Database\Seeders;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Actions\ArchiveClosedDaysAction;
use Falcon\Analytics\Actions\IngestEventsAction;
use Falcon\Analytics\DTOs\IncomingBatch;
use Falcon\Analytics\DTOs\IncomingEvent;
use Falcon\Analytics\DTOs\RequestSnapshot;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Repositories\SessionWriteRepository;
use Illuminate\Database\Seeder;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Throwable;

/**
 * Visits spread over the past days, recorded the way the collector's are ·
 * through the ingestion, the clock set to each visit's start.
 *
 * The one thing written aside is the place of a visit, which the ingestion
 * only finds in the host's geolocation database.
 *
 * **The days it writes into are summarised again afterwards**, those already
 * summarised included · the nightly run only goes forward, since a real visit
 * never lands in the past.
 *
 * @phpstan-type Step array{type: EventType, name: ?string, route: ?string, url: ?string, text: ?string}
 * @phpstan-type Page array{route: ?string, url: string}
 */
final class DemoTrafficSeeder extends Seeder
{
    /** How visits arrive, by weight. */
    private const CHANNELS = ['direct' => 35, 'search' => 25, 'social' => 15, 'referral' => 10, 'campaign' => 15];

    private const REFERRERS = [
        'search' => ['https://www.google.com/', 'https://www.bing.com/', 'https://duckduckgo.com/'],
        'social' => ['https://www.facebook.com/', 'https://www.instagram.com/', 'https://www.linkedin.com/'],
        'referral' => ['https://annuaire.example/', 'https://blog.example/'],
    ];

    private const AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Safari/605.1.15',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Mobile Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:130.0) Gecko/20100101 Firefox/130.0',
        'Mozilla/5.0 (iPad; CPU OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Mobile/15E148 Safari/604.1',
    ];

    private const ROBOT = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

    private const TEXTS = ['Nous contacter', 'Voir les tarifs', 'En savoir plus', 'Prendre rendez-vous'];

    /** Country, region, city, latitude, longitude. */
    private const PLACES = [
        ['FR', 'Île-de-France', 'Paris', 48.8566, 2.3522],
        ['FR', 'Auvergne-Rhône-Alpes', 'Lyon', 45.7640, 4.8357],
        ['CH', 'Genève', 'Genève', 46.2044, 6.1432],
        ['BE', 'Bruxelles-Capitale', 'Bruxelles', 50.8503, 4.3517],
        ['CA', 'Québec', 'Montréal', 45.5017, -73.5673],
    ];

    /** Route names that belong to a package or to the framework, not to the site. */
    private const FOREIGN = ['analytics.', 'livewire.', 'sanctum.', 'ignition.', 'debugbar.'];

    public function __construct(
        private readonly IngestEventsAction $ingest,
        private readonly SessionWriteRepository $sessions,
        private readonly ArchiveClosedDaysAction $archive,
        private readonly EventRegistry $events,
        private readonly FunnelRegistry $funnels,
        private readonly Router $router,
    ) {}

    /**
     * @return array<string, int>
     */
    public function run(int $days = 30, int $visits = 600): array
    {
        $before = (int) Session::query()->max('id');
        $random = new Randomizer(new Mt19937($before + $visits));
        $now = CarbonImmutable::now();
        $clock = CarbonImmutable::getTestNow();

        try {
            $this->visits($random, $this->moments($random, $now, $days, $visits), $this->pages(), $now);
        } finally {
            CarbonImmutable::setTestNow($clock);
        }

        $this->placeWhatHasNoPlace($before);

        $timeout = (int) config('analytics.session.timeout_minutes');
        $this->sessions->closeIdleSessions($now->subMinutes($timeout), $timeout);

        $summarised = $this->archive->executeFrom($this->firstDayWritten($before, $now));

        return ['visite|visites' => $visits, 'journée résumée|journées résumées' => count($summarised)];
    }

    /** The day the earliest visit of this pass started, where summarising again begins. */
    private function firstDayWritten(int $before, CarbonImmutable $now): CarbonImmutable
    {
        $earliest = Session::query()->where('id', '>', $before)->min('started_at');

        return is_string($earliest) ? CarbonImmutable::parse($earliest) : $now;
    }

    /**
     * When each visit starts, oldest first · by day and in the daytime, the
     * last few within the live window.
     *
     * @return list<CarbonImmutable>
     */
    private function moments(Randomizer $random, CarbonImmutable $now, int $days, int $visits): array
    {
        $live = min(3, $visits);
        $moments = [];

        for ($rank = 0; $rank < $visits - $live; $rank++) {
            $moment = $now->startOfDay()->subDays($random->getInt(0, $days))->setTime($random->getInt(8, 22), $random->getInt(0, 59));

            $moments[] = $moment->lessThan($now) ? $moment : $now->subSeconds($random->getInt(60, max(60, (int) $now->startOfDay()->diffInSeconds($now))));
        }

        for ($rank = 0; $rank < $live; $rank++) {
            $moments[] = $now->subSeconds($random->getInt(30, 900));
        }

        usort($moments, static fn (CarbonImmutable $a, CarbonImmutable $b): int => $a <=> $b);

        return $moments;
    }

    /**
     * @param  list<CarbonImmutable>  $moments
     * @param  list<Page>  $pages
     */
    private function visits(Randomizer $random, array $moments, array $pages, CarbonImmutable $now): void
    {
        $seen = [];
        $lastLeft = [];
        $returnable = 0;

        foreach ($moments as $moment) {
            while ($returnable < count($seen) && $seen[$returnable][1]->lessThanOrEqualTo($moment->subHour())) {
                $returnable++;
            }

            $uuid = $returnable > 0 && $random->getInt(1, 4) === 1 ? $seen[$random->getInt(0, $returnable - 1)][0] : null;
            $uuid = $uuid !== null && $lastLeft[$uuid]->lessThanOrEqualTo($moment->subHour()) ? $uuid : (string) Str::uuid();

            $channel = $this->pick($random, self::CHANNELS);
            $steps = $this->journey($random, $pages, $channel);
            $gaps = array_map(static fn (): int => $random->getInt(15, 120), $steps);
            $start = $moment->min($now->subSeconds(array_sum($gaps) + 1));

            CarbonImmutable::setTestNow($start);
            $this->ingest->execute($uuid, null, $this->snapshot($random), new IncomingBatch($this->timed($steps, $gaps, $start), $this->referrer($random, $channel)));

            $seen[] = [$uuid, $start];
            $lastLeft[$uuid] = $start->addSeconds(array_sum($gaps));
        }
    }

    /**
     * What one visit does · pages, sometimes a click, a declared event or a
     * declared funnel walked through.
     *
     * @param  list<Page>  $pages
     * @return list<Step>
     */
    private function journey(Randomizer $random, array $pages, string $channel): array
    {
        $landing = $pages[$random->getInt(0, count($pages) - 1)];
        $steps = [$this->pageview($landing['route'], $landing['url'].$this->campaignLink($random, $channel))];

        for ($more = $random->getInt(0, 3); $more > 0; $more--) {
            $page = $pages[$random->getInt(0, count($pages) - 1)];
            $steps[] = $this->pageview($page['route'], $page['url']);
        }

        if ($random->getInt(1, 10) <= 4) {
            $steps[] = ['type' => EventType::Click, 'name' => null, 'route' => $landing['route'], 'url' => $landing['url'], 'text' => self::TEXTS[$random->getInt(0, count(self::TEXTS) - 1)]];
        }

        foreach ($this->events->all() as $event) {
            if ($random->getInt(1, 100) <= ($channel === 'campaign' ? 35 : 10)) {
                $steps[] = $this->custom($event->name);
            }
        }

        return [...$steps, ...$this->funnelWalk($random, $pages)];
    }

    /**
     * Sometimes a declared funnel, walked step by step until the visitor
     * stops.
     *
     * @param  list<Page>  $pages
     * @return list<Step>
     */
    private function funnelWalk(Randomizer $random, array $pages): array
    {
        $steps = [];

        foreach ($this->funnels->all() as $funnel) {
            if ($random->getInt(1, 5) !== 1) {
                continue;
            }

            foreach ($funnel->steps() as $step) {
                $route = $step->routeNames()[0] ?? null;
                $event = $step->eventNames()[0] ?? null;

                $steps[] = $event !== null ? $this->custom($event) : $this->pageview($route, $this->urlOf($route, $pages));

                if ($random->getInt(1, 10) > 7) {
                    break;
                }
            }
        }

        return $steps;
    }

    /**
     * @param  list<Step>  $steps
     * @param  list<int>  $gaps
     * @return list<IncomingEvent>
     */
    private function timed(array $steps, array $gaps, CarbonImmutable $start): array
    {
        $events = [];
        $at = $start;

        foreach ($steps as $rank => $step) {
            $events[] = new IncomingEvent(
                type: $step['type'],
                occurredAt: $at,
                name: $step['name'],
                route: $step['route'],
                url: $step['url'],
                targetText: $step['text'],
            );

            $at = $at->addSeconds($gaps[$rank]);
        }

        return $events;
    }

    /** @return Step */
    private function pageview(?string $route, ?string $url): array
    {
        return ['type' => EventType::Pageview, 'name' => null, 'route' => $route, 'url' => $url, 'text' => null];
    }

    /** @return Step */
    private function custom(string $name): array
    {
        return ['type' => EventType::Custom, 'name' => $name, 'route' => null, 'url' => null, 'text' => null];
    }

    /** The query string of a demonstration campaign's link, for a visit that came through one. */
    private function campaignLink(Randomizer $random, string $channel): string
    {
        if ($channel !== 'campaign') {
            return '';
        }

        $campaignLink = array_keys(DemoMarketingSeeder::CAMPAIGNS)[$random->getInt(0, count(DemoMarketingSeeder::CAMPAIGNS) - 1)];
        $campaign = DemoMarketingSeeder::CAMPAIGNS[$campaignLink];
        $adLink = array_keys($campaign['ads'])[$random->getInt(0, count($campaign['ads']) - 1)];

        return '?'.$campaign['link'].'&utm_campaign='.$campaignLink.'&utm_content='.$adLink;
    }

    private function referrer(Randomizer $random, string $channel): ?string
    {
        $referrers = self::REFERRERS[$channel] ?? [];

        return $referrers === [] ? null : $referrers[$random->getInt(0, count($referrers) - 1)];
    }

    private function snapshot(Randomizer $random): RequestSnapshot
    {
        $agent = $random->getInt(1, 100) <= 3 ? self::ROBOT : self::AGENTS[$random->getInt(0, count(self::AGENTS) - 1)];
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return new RequestSnapshot(ip: '192.0.2.'.$random->getInt(1, 254), userAgent: $agent, host: is_string($host) ? $host : 'localhost');
    }

    /**
     * @param  array<string, int>  $weights
     */
    private function pick(Randomizer $random, array $weights): string
    {
        $draw = $random->getInt(1, array_sum($weights));

        foreach ($weights as $choice => $weight) {
            $draw -= $weight;

            if ($draw <= 0) {
                return $choice;
            }
        }

        return array_key_first($weights) ?? '';
    }

    /**
     * The host's public pages · named, reached by GET, without a parameter and
     * outside any authentication.
     *
     * @return list<Page>
     */
    private function pages(): array
    {
        $pages = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            if ($this->isAPublicPage($route)) {
                $pages[] = ['route' => $route->getName(), 'url' => $this->withAPath(url($route->uri()))];
            }
        }

        return $pages !== [] ? $pages : [['route' => null, 'url' => $this->withAPath(url('/'))]];
    }

    /** An address as a browser sends it · the site's root carries its slash. */
    private function withAPath(string $url): string
    {
        return parse_url($url, PHP_URL_PATH) === null ? $url.'/' : $url;
    }

    private function isAPublicPage(Route $route): bool
    {
        $name = $route->getName();

        if ($name === null || ! in_array('GET', $route->methods(), true) || str_contains($route->uri(), '{') || Str::startsWith($name, self::FOREIGN)) {
            return false;
        }

        foreach ($route->middleware() as $middleware) {
            if (Str::startsWith($middleware, ['auth', 'guest'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<Page>  $pages
     */
    private function urlOf(?string $route, array $pages): ?string
    {
        foreach ($pages as $page) {
            if ($page['route'] === $route) {
                return $page['url'];
            }
        }

        try {
            return $route !== null ? $this->withAPath(route($route)) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * A place for every new visit the host's geolocation could not place, one
     * statement per place.
     */
    private function placeWhatHasNoPlace(int $before): void
    {
        foreach (self::PLACES as $rank => [$country, $region, $city, $latitude, $longitude]) {
            Session::query()
                ->where('id', '>', $before)
                ->whereNull('country')
                ->whereRaw('id % ? = ?', [count(self::PLACES), $rank])
                ->update(['country' => $country, 'region' => $region, 'city' => $city, 'latitude' => $latitude, 'longitude' => $longitude]);
        }
    }
}
