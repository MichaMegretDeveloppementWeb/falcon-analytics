<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\Visitor\DeviceShare;
use Falcon\Analytics\DTOs\Dashboard\Visitor\SourceShare;
use Falcon\Analytics\DTOs\Dashboard\Visitor\VisitorDetail;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Services\SubjectResolver;
use Falcon\Analytics\Support\ChartPalette;
use Falcon\Analytics\Support\DeviceLabel;
use Falcon\Analytics\Support\DurationLabel;
use Falcon\Analytics\Support\NumberLabel;

/**
 * Prepares the detail screen of a visitor from the visitor and the aggregates
 * of their sessions, already read · the view receives values, and reads
 * nothing.
 *
 * @internal
 */
final readonly class VisitorDetailBuilder
{
    public function __construct(private SubjectResolver $subjects) {}

    /**
     * @param  array{sessions: int, pageviews: int, seconds: int, devices: array<string, int>, sources: array<string, int>}  $engagement
     */
    public function build(Visitor $visitor, array $engagement): VisitorDetail
    {
        $sessions = $engagement['sessions'];
        [$name, $kind] = $this->identity($visitor);

        return new VisitorDetail(
            id: $visitor->id,
            uuid: $visitor->uuid,
            name: $name,
            kind: $kind,
            isIdentified: self::isIdentified($visitor),
            isReturning: $visitor->session_count > 1,
            firstSeenAt: $visitor->first_seen_at,
            sessionCount: $visitor->session_count,
            pageviewCount: $engagement['pageviews'],
            averageDuration: DurationLabel::for($sessions > 0 ? (int) round($engagement['seconds'] / $sessions) : 0),
            pagesPerSession: NumberLabel::for($sessions > 0 ? $engagement['pageviews'] / $sessions : 0, 1),
            deviceSessions: array_sum($engagement['devices']),
            devices: self::devices($engagement['devices']),
            sources: self::sources($engagement['sources']),
        );
    }

    /**
     * What the visitor is called, and what kind of visitor they are.
     *
     * @return array{string, string}
     */
    private function identity(Visitor $visitor): array
    {
        $type = $visitor->subject_type;

        if ($type === null || $type === '') {
            return [__('Visiteur #:id', ['id' => $visitor->id]), __('Visiteur anonyme')];
        }

        $id = (int) $visitor->subject_id;
        $subject = $this->subjects->shownNames([[$type, $id]])[$type.':'.$id];

        return [$subject->name, $subject->label];
    }

    private static function isIdentified(Visitor $visitor): bool
    {
        return $visitor->subject_type !== null && $visitor->subject_type !== '';
    }

    /**
     * @param  array<array-key, int>  $devices  device => sessions · a numeric key comes back as an integer
     * @return list<DeviceShare>
     */
    private static function devices(array $devices): array
    {
        $total = array_sum($devices);

        if ($total <= 0) {
            return [];
        }

        $shares = [];
        foreach ($devices as $device => $sessions) {
            $shares[] = new DeviceShare(DeviceLabel::for((string) $device), $sessions, (int) round($sessions / $total * 100), ChartPalette::SERIES[count($shares)] ?? '--an-series-6');
        }

        return $shares;
    }

    /**
     * @param  array<array-key, int>  $sources  source => sessions · a numeric key comes back as an integer
     * @return list<SourceShare>
     */
    private static function sources(array $sources): array
    {
        $total = array_sum($sources);

        if ($total <= 0) {
            return [];
        }

        $shares = [];
        foreach ($sources as $source => $sessions) {
            $shares[] = new SourceShare((string) $source, $sessions, (int) round($sessions / $total * 100));
        }

        return $shares;
    }
}
