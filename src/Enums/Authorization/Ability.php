<?php

declare(strict_types=1);

namespace Falcon\Analytics\Enums\Authorization;

/**
 * What an account may open and do in the package, as a tree of named gates.
 *
 * An ability the host does not define answers like its parent, and the root
 * answers yes to any signed-in account. A host restricts by defining one of
 * these names with `Gate::define()`: the whole branch below it follows.
 */
enum Ability: string
{
    case Analytics = 'analytics';
    case Audience = 'analytics.audience';
    case Overview = 'analytics.overview';
    case Realtime = 'analytics.realtime';
    case Sessions = 'analytics.sessions';
    case Visitors = 'analytics.visitors';
    case VisitorsDelete = 'analytics.visitors.delete';
    case Events = 'analytics.events';
    case Funnels = 'analytics.funnels';
    case Integrations = 'analytics.integrations';
    case IntegrationsManage = 'analytics.integrations.manage';
    case Marketing = 'analytics.marketing';
    case MarketingDashboard = 'analytics.marketing-dashboard';
    case Campaigns = 'analytics.campaigns';
    case CampaignsEdit = 'analytics.campaigns.edit';
    case CampaignsDelete = 'analytics.campaigns.delete';
    case Ads = 'analytics.ads';
    case AdsEdit = 'analytics.ads.edit';
    case AdsDelete = 'analytics.ads.delete';

    /** The ability this one answers like while the host has not defined it. */
    public function parent(): ?self
    {
        return match ($this) {
            self::Analytics => null,
            self::Audience, self::Marketing => self::Analytics,
            self::Overview, self::Realtime, self::Sessions, self::Visitors,
            self::Events, self::Funnels, self::Integrations => self::Audience,
            self::MarketingDashboard, self::Campaigns, self::Ads => self::Marketing,
            self::VisitorsDelete => self::Visitors,
            self::IntegrationsManage => self::Integrations,
            self::CampaignsEdit => self::Campaigns,
            self::CampaignsDelete => self::CampaignsEdit,
            self::AdsEdit => self::Ads,
            self::AdsDelete => self::AdsEdit,
        };
    }

    /** Whether this ability is the given one, or lies somewhere below it. */
    public function isWithin(self $branch): bool
    {
        for ($ability = $this; $ability !== null; $ability = $ability->parent()) {
            if ($ability === $branch) {
                return true;
            }
        }

        return false;
    }
}
