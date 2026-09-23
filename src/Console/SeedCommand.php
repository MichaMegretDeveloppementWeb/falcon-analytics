<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console;

use Falcon\Analytics\Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Throwable;

/**
 * Fills the screens with invented visits, through the seeder chain · refuses
 * outside development, and each pass adds.
 *
 * @internal
 */
final class SeedCommand extends Command
{
    protected $signature = 'analytics:seed
                            {--days=30 : Sur combien de jours passés répartir les visites}
                            {--visits=600 : Combien de visites ajouter}
                            {--force : Exécuter hors développement}';

    protected $description = 'Peuple la base de visites inventées, de quoi éprouver les écrans. Jamais en production sans --force.';

    public function handle(DatabaseSeeder $chain): int
    {
        if (! $this->mayRunHere()) {
            return self::FAILURE;
        }

        $counts = $this->counts();

        if ($counts === null) {
            return self::FAILURE;
        }

        try {
            $this->components->info('Peuplement.');
            $chain->run($counts['days'], $counts['visits'], $this->told(...));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Peuplement terminé. Relancez la commande pour en ajouter davantage.');

        return self::SUCCESS;
    }

    /** Whether writing invented data is acceptable here · the refusal names the environment. */
    private function mayRunHere(): bool
    {
        if ($this->laravel->environment('local', 'testing') || $this->option('force') === true) {
            return true;
        }

        $this->components->error(sprintf(
            'L’environnement est « %s ». Cette commande crée de fausses visites et de fausses campagnes. '
            .'Relancez avec --force si c’est vraiment ce que vous voulez.',
            $this->laravel->environment(),
        ));

        return false;
    }

    /**
     * The options, read and checked before anything is written · the days
     * stay within the retention, where every day can be summarised again.
     *
     * @return array{days: int, visits: int}|null
     */
    private function counts(): ?array
    {
        foreach (['days', 'visits'] as $option) {
            $value = $this->option($option);

            if (! is_numeric($value) || (int) $value < 0) {
                $this->components->error("--{$option} attend un nombre positif, « ".(is_string($value) ? $value : '?').' » reçu.');

                return null;
            }
        }

        $days = (int) $this->option('days');
        $retention = config('analytics.retention_days');

        if (is_int($retention) && $retention >= 1 && $days > $retention) {
            $this->components->error(
                "--days dépasse la conservation réglée ({$retention} jours, analytics.retention_days) · des visites "
                .'plus anciennes seraient effacées à la purge suivante, sans avoir compté dans les classements.'
            );

            return null;
        }

        return ['days' => $days, 'visits' => (int) $this->option('visits')];
    }

    /**
     * One line of the report · each key names what was counted as
     * « singulier|pluriel », and a zero is left out.
     *
     * @param  array<string, int>  $written
     */
    private function told(string $section, array $written): void
    {
        $parts = [];

        foreach ($written as $what => $count) {
            if ($count === 0) {
                continue;
            }

            [$singular, $plural] = array_pad(explode('|', $what, 2), 2, $what);
            $parts[] = $count.' '.($count > 1 ? $plural : $singular);
        }

        $this->components->twoColumnDetail(
            $section,
            $parts === [] ? '<fg=gray>rien à ajouter</>' : '<fg=green>'.implode(', ', $parts).'</>',
        );
    }
}
