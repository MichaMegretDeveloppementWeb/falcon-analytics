<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Services\LandingParamsResolver;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Support\Collection;

final class LandingParamsResolverTest extends TestCase
{
    private LandingParamsResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new LandingParamsResolver;
    }

    public function test_it_extracts_the_landing_url_query_parameters(): void
    {
        $this->assertSame(
            ['src' => 'meta_ete', 'creative' => 'cabrio'],
            $this->resolver->resolve('https://vantadrive.ch/voitures?src=meta_ete&creative=cabrio'),
        );
    }

    public function test_it_returns_an_empty_array_without_a_query_string_or_url(): void
    {
        $this->assertSame([], $this->resolver->resolve('https://vantadrive.ch/voitures'));
        $this->assertSame([], $this->resolver->resolve(null));
    }

    public function test_it_drops_empty_values_and_array_style_params(): void
    {
        $this->assertSame(
            ['a' => '1', 'd' => '2'],
            $this->resolver->resolve('https://vantadrive.ch/?a=1&b=&c[]=x&d=2'),
        );
    }

    public function test_it_caps_overly_long_values(): void
    {
        $params = $this->resolver->resolve('https://vantadrive.ch/?campaign='.str_repeat('x', 200));

        $this->assertSame(150, mb_strlen($params['campaign']));
    }

    public function test_it_caps_the_number_of_parameters(): void
    {
        $query = Collection::make(range(1, 40))->map(fn (int $i): string => "p{$i}=v{$i}")->implode('&');

        $this->assertCount(30, $this->resolver->resolve("https://vantadrive.ch/?{$query}"));
    }
}
