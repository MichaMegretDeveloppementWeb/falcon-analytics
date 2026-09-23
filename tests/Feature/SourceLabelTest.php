<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Support\SourceLabel;
use Falcon\Analytics\Tests\TestCase;

final class SourceLabelTest extends TestCase
{
    public function test_it_maps_known_channels_to_their_translated_label_case_insensitively(): void
    {
        $this->assertSame('Direct', SourceLabel::for('direct'));
        $this->assertSame('Recherche naturelle', SourceLabel::for('ORGANIC'));
        $this->assertSame('Social naturel', SourceLabel::for('social'));
        $this->assertSame('Payant', SourceLabel::for('paid'));
        $this->assertSame('Référent', SourceLabel::for('referral'));
        $this->assertSame('Référent', SourceLabel::for('campaign'));
        $this->assertSame('E-mail', SourceLabel::for('email'));
    }

    public function test_an_absent_channel_is_direct_and_an_unknown_one_keeps_its_own_name(): void
    {
        $this->assertSame('Direct', SourceLabel::for(''));
        $this->assertSame('Direct', SourceLabel::for(null));
        $this->assertSame('Affiliate', SourceLabel::for('affiliate'));
    }

    public function test_it_describes_each_channel_without_repeating_what_the_label_says(): void
    {
        $this->assertSame('Accès direct', SourceLabel::description('direct'));
        $this->assertSame('Trafic publicitaire', SourceLabel::description('PAID'));
        $this->assertSame('Site référent', SourceLabel::description('referral'));
        $this->assertSame('Site référent', SourceLabel::description('campaign'));
        $this->assertSame('Campagne e-mail', SourceLabel::description('email'));
        $this->assertSame('Provenance inconnue', SourceLabel::description('affiliate'));
    }

    public function test_an_absent_channel_is_described_as_the_direct_one_it_is_labelled(): void
    {
        // The label calls a missing source direct, so the line under it cannot say unknown.
        $this->assertSame(SourceLabel::description('direct'), SourceLabel::description(''));
        $this->assertSame(SourceLabel::description('direct'), SourceLabel::description(null));
    }
}
