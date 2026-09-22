<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\TestCase;

/**
 * Chaque processus d'essai publie les fichiers compilés dans son propre
 * dossier.
 *
 * **Le jumeau du cache de démarrage, et le troisième des trois choses qu'un
 * passage en parallèle doit donner à chaque processus.** Les quatre processus
 * démarrent la même application d'essai, dont `public_path()` est UN seul
 * dossier. Sur un poste, les copies sont déjà en place et personne ne publie ·
 * sur un dépôt fraîchement récupéré, le dossier est vide, les quatre publient
 * en même temps, et l'un rend une page pendant qu'un autre recopie encore
 * `icons.svg`. La garde de péremption du kit lit un fichier à moitié écrit,
 * refuse de construire l'adresse, et **l'essai qui tombe ne parle de rien de
 * tout ça**.
 *
 * Mesuré sur la chaîne d'intégration · vert, rouge, vert sur le même code.
 * C'est l'allure qu'a un dossier partagé vu du dehors : celle du hasard.
 */
final class EachWorkerPublishesIntoItsOwnDirectoryTest extends TestCase
{
    public function test_the_booted_application_publishes_where_this_worker_publishes(): void
    {
        $token = getenv('TEST_TOKEN');
        $expected = $this->publishedDirectory(is_string($token) ? $token : '');

        $this->assertSame(
            str_replace('\\', '/', $expected),
            str_replace('\\', '/', public_path()),
            "L'application d'essai doit publier là où ce processus publie.",
        );
    }

    public function test_a_named_worker_gets_a_directory_of_its_own_and_a_sequential_run_keeps_the_default(): void
    {
        $this->assertStringEndsWith('/laravel/public-essai', str_replace('\\', '/', $this->publishedDirectory('essai')));
        $this->assertStringEndsWith('/laravel/public', str_replace('\\', '/', $this->publishedDirectory('')));
    }

    /**
     * The bench keeps the rule private, as it should: it is read here through
     * the class rather than restated, so a change of shape cannot leave this
     * test agreeing with a rule nobody applies any more.
     */
    private function publishedDirectory(string $token): string
    {
        $method = new \ReflectionMethod(TestCase::class, 'publishedDirectoryFor');

        return (string) $method->invoke(null, $token);
    }
}
