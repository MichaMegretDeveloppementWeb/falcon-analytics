<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Enums\Authorization\Ability;
use Falcon\Analytics\Livewire\Admin\AdDetailPage;
use Falcon\Analytics\Livewire\Admin\AdForm;
use Falcon\Analytics\Livewire\Admin\AdsPage;
use Falcon\Analytics\Livewire\Admin\CampaignDetailPage;
use Falcon\Analytics\Livewire\Admin\CampaignForm;
use Falcon\Analytics\Livewire\Admin\CampaignsPage;
use Falcon\Analytics\Livewire\Admin\EventsPage;
use Falcon\Analytics\Livewire\Admin\FunnelsPage;
use Falcon\Analytics\Livewire\Admin\IntegrationsPage;
use Falcon\Analytics\Livewire\Admin\MarketingDashboardPage;
use Falcon\Analytics\Livewire\Admin\OverviewPage;
use Falcon\Analytics\Livewire\Admin\RealtimePage;
use Falcon\Analytics\Livewire\Admin\SessionDetailPage;
use Falcon\Analytics\Livewire\Admin\SessionsPage;
use Falcon\Analytics\Livewire\Admin\VisitorDetailPage;
use Falcon\Analytics\Livewire\Admin\VisitorsPage;
use Falcon\Analytics\Livewire\Admin\Widgets\AdDetailContent;
use Falcon\Analytics\Livewire\Admin\Widgets\CampaignDetailContent;
use Falcon\Analytics\Livewire\Admin\Widgets\EventsContent;
use Falcon\Analytics\Livewire\Admin\Widgets\FunnelsContent;
use Falcon\Analytics\Livewire\Admin\Widgets\MarketingDashboardContent;
use Falcon\Analytics\Livewire\Admin\Widgets\OverviewAcquisition;
use Falcon\Analytics\Livewire\Admin\Widgets\OverviewAudience;
use Falcon\Analytics\Livewire\Admin\Widgets\OverviewContent;
use Falcon\Analytics\Livewire\Admin\Widgets\OverviewEvents;
use Falcon\Analytics\Livewire\Admin\Widgets\OverviewHeadline;
use Falcon\Analytics\Livewire\Admin\Widgets\OverviewSearchQueries;
use Falcon\Analytics\Livewire\Admin\Widgets\SessionsHeadline;
use Falcon\Analytics\Livewire\Admin\Widgets\TrendChart;
use Falcon\Analytics\Livewire\Admin\Widgets\VisitorsHeadline;
use Falcon\Analytics\Tests\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;

/**
 * Every public method of an administration component is something a browser
 * can call, or trigger by setting a property. Each is written down here: a
 * read, a change to a form still on screen, or a gesture with the ability it
 * asks. A method added without being written down fails here, so no new
 * entrance opens without a decision.
 *
 * What the gestures actually ask is proven by EveryGestureAsksItsAbilityTest.
 */
final class EveryPublicMethodIsClassifiedTest extends TestCase
{
    /** Reads and redraws: nothing written, the screen's own ability covers them. */
    private const READ = 'read';

    /** Changes to a form still on screen: its save asks the ability. */
    private const FORM = 'form';

    /** Hooks Livewire runs itself and refuses to let a browser call. */
    private const HOOKS = '/^(mount|boot|booted|hydrate|dehydrate|rendering|rendered|exception)/';

    /** @var array<class-string, array<string, self::READ|self::FORM|Ability>> */
    private const METHODS = [
        OverviewPage::class => ['render' => self::READ],
        RealtimePage::class => ['render' => self::READ],
        VisitorsPage::class => ['render' => self::READ, 'sortBy' => self::READ, 'updatedPeriod' => self::READ, 'updatedSearch' => self::READ, 'updatedSubject' => self::READ],
        VisitorDetailPage::class => ['render' => self::READ, 'forget' => Ability::VisitorsDelete],
        EventsPage::class => ['render' => self::READ],
        FunnelsPage::class => ['render' => self::READ],
        SessionsPage::class => ['render' => self::READ, 'sortBy' => self::READ, 'updatedDevice' => self::READ, 'updatedPeriod' => self::READ, 'updatedSearch' => self::READ, 'updatedSource' => self::READ, 'updatedSubject' => self::READ],
        SessionDetailPage::class => ['render' => self::READ],
        IntegrationsPage::class => [
            'render' => self::READ,
            'reloadProperties' => Ability::IntegrationsManage,
            'selectProperty' => Ability::IntegrationsManage,
            'syncNow' => Ability::IntegrationsManage,
            'disconnectConfirmed' => Ability::IntegrationsManage,
        ],
        MarketingDashboardPage::class => ['render' => self::READ],
        CampaignsPage::class => [
            'render' => self::READ,
            'refresh' => self::READ,
            'updatedSearch' => self::READ,
            'confirmDelete' => Ability::CampaignsDelete,
            'deleteConfirmed' => Ability::CampaignsDelete,
        ],
        CampaignDetailPage::class => [
            'render' => self::READ,
            'refresh' => self::READ,
            'fillAdMetrics' => self::READ,
            'deleteCampaignConfirmed' => Ability::CampaignsDelete,
            'confirmDeleteAd' => Ability::AdsDelete,
            'deleteAdConfirmed' => Ability::AdsDelete,
        ],
        CampaignForm::class => [
            'render' => self::READ,
            'addCampaignCondition' => self::FORM,
            'removeCampaignCondition' => self::FORM,
            'editCampaign' => Ability::CampaignsEdit,
            'saveCampaign' => Ability::CampaignsEdit,
        ],
        AdsPage::class => ['render' => self::READ, 'updatedSearch' => self::READ],
        AdDetailPage::class => ['render' => self::READ, 'refresh' => self::READ],
        AdForm::class => [
            'render' => self::READ,
            'addAdCondition' => self::FORM,
            'removeAdCondition' => self::FORM,
            'addObjective' => self::FORM,
            'removeObjective' => self::FORM,
            'editAd' => Ability::AdsEdit,
            'saveAd' => Ability::AdsEdit,
        ],
        AdDetailContent::class => ['placeholder' => self::READ, 'render' => self::READ],
        CampaignDetailContent::class => ['placeholder' => self::READ, 'render' => self::READ],
        EventsContent::class => ['placeholder' => self::READ, 'render' => self::READ],
        FunnelsContent::class => ['placeholder' => self::READ, 'render' => self::READ],
        MarketingDashboardContent::class => ['placeholder' => self::READ, 'render' => self::READ],
        OverviewAcquisition::class => ['placeholder' => self::READ, 'render' => self::READ],
        OverviewAudience::class => ['placeholder' => self::READ, 'render' => self::READ],
        OverviewContent::class => ['placeholder' => self::READ, 'render' => self::READ],
        OverviewEvents::class => ['placeholder' => self::READ, 'render' => self::READ],
        OverviewHeadline::class => ['placeholder' => self::READ, 'render' => self::READ],
        OverviewSearchQueries::class => ['placeholder' => self::READ, 'render' => self::READ],
        SessionsHeadline::class => ['placeholder' => self::READ, 'render' => self::READ],
        TrendChart::class => ['placeholder' => self::READ, 'render' => self::READ],
        VisitorsHeadline::class => ['placeholder' => self::READ, 'render' => self::READ],
    ];

    public function test_every_public_method_of_the_administration_is_written_down(): void
    {
        $written = [];

        foreach (self::METHODS as $component => $methods) {
            $written[$component] = array_keys($methods);
            sort($written[$component]);
        }

        ksort($written);

        $this->assertSame($written, $this->publicMethods(), 'A public method is an entrance: say whether it reads, fills a form, or asks an ability.');
    }

    /** @return array<class-string, list<string>> */
    private function publicMethods(): array
    {
        $source = (string) realpath(__DIR__.'/../../src');
        $found = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source.'/Livewire/Admin')) as $file) {
            /** @var SplFileInfo $file */
            if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), 'Concerns')) {
                continue;
            }

            $class = 'Falcon\\Analytics\\'.strtr(substr($file->getPathname(), strlen($source) + 1, -4), ['/' => '\\']);

            if (! class_exists($class)) {
                $this->fail("{$class} is not loadable under the name its path spells.");
            }

            $reflection = new ReflectionClass($class);

            if (! $reflection->isAbstract()) {
                $found[$class] = $this->entrancesOf($reflection, $source);
            }
        }

        ksort($found);

        return $found;
    }

    /**
     * @param  ReflectionClass<object>  $component
     * @return list<string>
     */
    private function entrancesOf(ReflectionClass $component, string $source): array
    {
        $methods = [];

        foreach ($component->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $ownFile = str_starts_with((string) realpath((string) $method->getFileName()), $source);

            if ($ownFile && ! $method->isStatic() && preg_match(self::HOOKS, $method->getName()) !== 1) {
                $methods[] = $method->getName();
            }
        }

        sort($methods);

        return $methods;
    }
}
