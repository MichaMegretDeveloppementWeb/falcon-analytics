<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Unit;

use Falcon\Analytics\Services\SourceResolver;
use PHPUnit\Framework\TestCase;

final class SourceResolverTest extends TestCase
{
    private SourceResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new SourceResolver;
    }

    public function test_it_classifies_direct_traffic(): void
    {
        $this->assertSame('direct', $this->resolver->resolve('https://vantadrive.ch/', null, 'vantadrive.ch')->source);
    }

    public function test_it_treats_an_internal_referrer_as_direct(): void
    {
        $this->assertSame(
            'direct',
            $this->resolver->resolve('https://vantadrive.ch/x', 'https://www.vantadrive.ch/', 'vantadrive.ch')->source,
        );
    }

    public function test_it_classifies_organic_search(): void
    {
        $this->assertSame(
            'organic',
            $this->resolver->resolve('https://vantadrive.ch/', 'https://www.google.com/search?q=x', 'vantadrive.ch')->source,
        );
    }

    public function test_it_classifies_social(): void
    {
        $this->assertSame(
            'social',
            $this->resolver->resolve('https://vantadrive.ch/', 'https://facebook.com/', 'vantadrive.ch')->source,
        );
    }

    public function test_it_classifies_referral(): void
    {
        $this->assertSame(
            'referral',
            $this->resolver->resolve('https://vantadrive.ch/', 'https://someblog.example/', 'vantadrive.ch')->source,
        );
    }

    public function test_it_derives_paid_source_and_utm_parameters_utm_winning_over_the_referrer(): void
    {
        $acquisition = $this->resolver->resolve(
            'https://vantadrive.ch/?utm_source=meta&utm_medium=cpc&utm_campaign=spring',
            'https://facebook.com/',
            'vantadrive.ch',
        );

        $this->assertSame('paid', $acquisition->source);
        $this->assertSame('meta', $acquisition->utmSource);
        $this->assertSame('cpc', $acquisition->utmMedium);
        $this->assertSame('spring', $acquisition->utmCampaign);
    }

    public function test_it_classifies_google_ads_auto_tagging_as_paid_even_without_utm(): void
    {
        $this->assertSame(
            'paid',
            $this->resolver->resolve('https://vantadrive.ch/?gclid=abc123', 'https://www.google.com/', 'vantadrive.ch')->source,
        );
    }

    public function test_it_classifies_microsoft_ads_as_paid(): void
    {
        $this->assertSame(
            'paid',
            $this->resolver->resolve('https://vantadrive.ch/?msclkid=abc', null, 'vantadrive.ch')->source,
        );
    }

    public function test_it_classifies_paid_social_utm_medium_as_paid(): void
    {
        $this->assertSame(
            'paid',
            $this->resolver->resolve(
                'https://vantadrive.ch/?utm_source=meta&utm_medium=paid_social',
                'https://facebook.com/',
                'vantadrive.ch',
            )->source,
        );
    }

    /** Un clic organique depuis Facebook porte `fbclid` lui aussi. */
    public function test_it_does_not_treat_fbclid_as_paid(): void
    {
        $this->assertSame(
            'social',
            $this->resolver->resolve('https://vantadrive.ch/?fbclid=xyz', 'https://facebook.com/', 'vantadrive.ch')->source,
        );
    }

    public function test_it_returns_null_utm_when_absent(): void
    {
        $acquisition = $this->resolver->resolve('https://vantadrive.ch/', null, 'vantadrive.ch');

        $this->assertNull($acquisition->utmSource);
        $this->assertNull($acquisition->utmCampaign);
    }

    public function test_it_truncates_oversized_utm_values_to_the_column_length(): void
    {
        $long = str_repeat('a', 400);

        $acquisition = $this->resolver->resolve(
            'https://vantadrive.ch/?utm_source='.$long.'&utm_campaign='.$long,
            null,
            'vantadrive.ch',
        );

        $this->assertSame(str_repeat('a', 150), $acquisition->utmSource);
        $this->assertSame(str_repeat('a', 150), $acquisition->utmCampaign);
    }
}
