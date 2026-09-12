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
    protected $signature = 'analytics:events:scan {--fix : Ajoute les événements employés dans le code mais pas encore déclarés}';

    protected $description = 'Compare les événements déclarés à ceux réellement employés dans le code.';

    public function handle(EventRegistry $registry): int
    {
        $declared = $registry->names();
        $used = $this->scanUsedEvents();

        $usedNotDeclared = array_values(array_diff($used, $declared));
        $declaredNotUsed = array_values(array_diff($declared, $used));

        $this->components->info(sprintf('%d événement(s) déclaré(s), %d trouvé(s) dans le code.', count($declared), count($used)));

        foreach ($usedNotDeclared as $name) {
            $this->components->warn("Employé dans le code mais non déclaré : {$name}");
        }

        foreach ($declaredNotUsed as $name) {
            $this->components->warn("Déclaré mais introuvable dans le code (nom calculé à l’exécution, ou inemployé ?) : {$name}");
        }

        if ($usedNotDeclared === []) {
            $this->components->info('Chaque événement employé dans le code est déclaré.');

            return self::SUCCESS;
        }

        if ($this->option('fix') === true) {
            return $this->appendMissing($usedNotDeclared);
        }

        $this->components->info('Relancez avec --fix pour ajouter les événements non déclarés au fichier des événements.');

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
            $dir = is_dir($relative) ? $relative : base_path($relative);

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
        $configured = config('analytics.events_path');
        $path = is_string($configured) && $configured !== ''
            ? $configured
            : base_path('app/Analytics/events.php');

        if (! is_file($path)) {
            $this->components->error('Fichier des événements introuvable ; créez d’abord app/Analytics/events.php.');

            return self::FAILURE;
        }

        $lines = "\n// Added by analytics:events:scan. Review each label.\n";

        foreach ($names as $name) {
            $lines .= sprintf('TrackedEvent::define(%s, %s);'.PHP_EOL, var_export($name, true), var_export($name, true));
        }

        if (file_put_contents($path, $lines, FILE_APPEND) === false) {
            $this->components->error('Écriture du fichier des événements impossible ; vérifiez ses permissions.');

            return self::FAILURE;
        }

        $this->components->info(sprintf('%d événement(s) ajouté(s) au fichier des événements. Relisez leurs libellés.', count($names)));

        return self::SUCCESS;
    }
}
