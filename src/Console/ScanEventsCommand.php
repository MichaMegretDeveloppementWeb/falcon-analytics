<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console;

use Falcon\Analytics\Events\EventRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Compares the events actually used in the code (data-track-event attributes,
 * Analytics::record calls with a literal name or an Enum::Case->name) with the
 * events declared in the events file, so the declared list stays the single
 * source of truth. Dynamic names computed at runtime cannot be detected and must
 * be declared by hand.
 */
final class ScanEventsCommand extends Command
{
    protected $signature = 'analytics:events:scan {--fix : Append the events used in the code but not yet declared}';

    protected $description = 'Reconcile the declared tracked events with those used in the code.';

    public function handle(EventRegistry $registry): int
    {
        $declared = $registry->names();
        $used = $this->scanUsedEvents();

        $usedNotDeclared = array_values(array_diff($used, $declared));
        $declaredNotUsed = array_values(array_diff($declared, $used));

        $this->components->info(sprintf('%d event(s) declared, %d found in the code.', count($declared), count($used)));

        foreach ($usedNotDeclared as $name) {
            $this->components->warn("Used in the code but not declared: {$name}");
        }

        foreach ($declaredNotUsed as $name) {
            $this->components->warn("Declared but not found in the code (runtime-computed name, or unused?): {$name}");
        }

        if ($usedNotDeclared === []) {
            $this->components->info('Every event used in the code is declared.');

            return self::SUCCESS;
        }

        if ($this->option('fix')) {
            return $this->appendMissing($usedNotDeclared);
        }

        $this->components->info('Run again with --fix to append the undeclared events to the events file.');

        return self::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function scanUsedEvents(): array
    {
        /** @var list<string> $paths */
        $paths = (array) config('analytics.events_scan_paths', ['app', 'resources/views']);
        $names = [];

        foreach ($paths as $relative) {
            $dir = is_dir((string) $relative) ? (string) $relative : base_path((string) $relative);

            if (! is_dir($dir)) {
                continue;
            }

            foreach (File::allFiles($dir) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $content = (string) file_get_contents($file->getRealPath());

                preg_match_all('/data-track-event\s*=\s*["\']([^"\']+)["\']/', $content, $attributes);
                preg_match_all('/record\(\s*["\']([^"\']+)["\']/', $content, $literals);
                preg_match_all('/record\(\s*[\\\\\w]+::(\w+)->name/', $content, $enums);

                $names = array_merge($names, $attributes[1], $literals[1], $enums[1]);
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param  list<string>  $names
     */
    private function appendMissing(array $names): int
    {
        $path = config('analytics.events_path') ?: base_path('app/Analytics/events.php');

        if (! is_string($path) || ! is_file($path)) {
            $this->components->error('Events file not found; create app/Analytics/events.php first.');

            return self::FAILURE;
        }

        $lines = "\n// Added by analytics:events:scan. Review each label.\n";

        foreach ($names as $name) {
            $lines .= sprintf('TrackedEvent::define(%s, %s);'.PHP_EOL, var_export($name, true), var_export($name, true));
        }

        file_put_contents($path, $lines, FILE_APPEND);

        $this->components->info(sprintf('Appended %d event(s) to the events file. Review their labels.', count($names)));

        return self::SUCCESS;
    }
}
