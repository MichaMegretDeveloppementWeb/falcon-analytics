<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

use Falcon\Analytics\Console\InstallCommand;

/**
 * What the host imports, and the single source of these paths. {@see
 * InstallCommand} writes them, {@see CheckCommand} reads them back.
 *
 * The shape and the name are those of `BookingAssets::hostImports()` and
 * `KitAssets::hostImports()`: three packages answering the same question answer
 * it in the same words.
 */
final class AnalyticsAssets
{
    /**
     * Configuration key, kind of file, path from the project root.
     *
     * The collector goes into the public site's script and never into the back
     * office's, an administrator's visits not being measured.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function hostImports(): array
    {
        return [
            'admin_css' => ['css', 'vendor/falcon/analytics/resources/css/analytics-admin.css'],
            'web_js' => ['js', 'vendor/falcon/analytics/resources/js/collector.js'],
        ];
    }
}
