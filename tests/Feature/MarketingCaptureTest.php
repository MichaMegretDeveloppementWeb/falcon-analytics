<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;

final class MarketingCaptureTest extends TestCase
{
    use RefreshDatabase;

    private function ingestLanding(string $url): void
    {
        $this->withoutDefer()
            ->withHeader('Origin', config('app.url'))
            ->postJson('/__analytics', [
                'sent_at' => 1000,
                'events' => [['type' => 'pageview', 'ts' => 1000, 'route' => 'home', 'url' => $url]],
            ])
            ->assertNoContent();
    }

    /**
     * @return array<string, array{string, array<string, string>}>
     */
    public static function landingUrls(): array
    {
        return [
            'marketing params' => ['https://boutique.test/?src=meta_ete&creative=cabrio', ['src' => 'meta_ete', 'creative' => 'cabrio']],
            'no params' => ['https://boutique.test/voitures', []],
            'deep path keeps params' => ['https://boutique.test/voitures/cabriolet?src=meta_ete', ['src' => 'meta_ete']],
            'coexists with utm' => ['https://boutique.test/?utm_source=meta&src=meta_ete', ['utm_source' => 'meta', 'src' => 'meta_ete']],
            'accented value (percent-encoded)' => ['https://boutique.test/?campaign=%C3%A9te-2026', ['campaign' => 'éte-2026']],
            'value with a space (plus-encoded)' => ['https://boutique.test/?campaign=ete+2026', ['campaign' => 'ete 2026']],
            'empty values dropped' => ['https://boutique.test/?a=&b=x', ['b' => 'x']],
            'parameter names are case-sensitive' => ['https://boutique.test/?Src=Meta&AD=Cabrio', ['Src' => 'Meta', 'AD' => 'Cabrio']],
        ];
    }

    /**
     * The key order of a JSON object carries no meaning, and MySQL changes it.
     *
     * It normalises on write, sorting by key length then alphabetically:
     * `?utm_source=meta&src=meta_ete` comes back with `src` before
     * `utm_source`. SQLite kept the text as it stood, and the assertion was
     * unknowingly relying on that peculiarity of an engine that is not the
     * production one.
     *
     * Both sides are sorted rather than canonicalised:
     * `assertEqualsCanonicalizing` goes through `sort()`, which throws the keys
     * away, and a test comparing only values would accept `src` in place of
     * `creative`.
     *
     * @param  array<string, string>  $expected
     */
    #[DataProvider('landingUrls')]
    public function test_it_captures_the_landing_url_parameters_through_the_full_pipeline(string $url, array $expected): void
    {
        $this->ingestLanding($url);

        $captured = Session::query()->latest('id')->firstOrFail()->mkt_params ?? [];

        ksort($captured);
        ksort($expected);

        $this->assertSame($expected, $captured);
    }

    public function test_it_caps_an_overly_long_value_at_150_characters(): void
    {
        $this->ingestLanding('https://boutique.test/?campaign='.str_repeat('x', 200).'&ad=cabrio');

        $params = Session::query()->latest('id')->firstOrFail()->mkt_params ?? [];

        $this->assertSame(150, mb_strlen($params['campaign']));
        $this->assertSame('cabrio', $params['ad']);
    }

    public function test_it_keeps_distinct_params_on_separate_sessions(): void
    {
        $this->ingestLanding('https://boutique.test/?src=meta_ete&creative=cabrio');
        $this->flushSession();

        $this->ingestLanding('https://boutique.test/?src=meta_ete&creative=suv');

        $sessions = Session::query()->orderBy('id')->get();

        $first = $sessions->get(0);
        $second = $sessions->get(1);

        $this->assertCount(2, $sessions);
        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame(['src' => 'meta_ete', 'creative' => 'cabrio'], $first->mkt_params);
        $this->assertSame(['src' => 'meta_ete', 'creative' => 'suv'], $second->mkt_params);
    }
}
