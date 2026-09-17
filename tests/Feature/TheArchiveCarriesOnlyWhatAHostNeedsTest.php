<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\TestCase;

/**
 * Ce qu'un hôte reçoit, et rien d'autre.
 *
 * Un hôte n'installe pas un dépôt, il installe une **archive** · ce que
 * `git archive` produit, une fois les `export-ignore` appliqués. Tout ce qui y
 * entre par accident est téléchargé par chaque projet, à chaque déploiement, et
 * suggère un outillage que l'hôte n'a pas à connaître.
 *
 * **Une liste blanche, et pas une liste noire.** Un oubli dans une liste noire
 * ne se voit jamais · c'est exactement ce qui est arrivé le 2026-09-14, où
 * `eslint.config.js` et `testbench.yaml` partaient chez l'hôte parce que
 * personne ne les avait ajoutés à `.gitattributes`. Écrite en liste blanche, la
 * garantie tient aussi pour le fichier d'outillage que quelqu'un ajoutera
 * demain · il faudra le nommer ici, donc y penser.
 */
final class TheArchiveCarriesOnlyWhatAHostNeedsTest extends TestCase
{
    /**
     * Tout ce qu'une application a besoin de recevoir, et la raison de chacun.
     *
     * @var list<string>
     */
    private const SHIPPED = [
        'CHANGELOG.md',     // ce qu'une mise à jour demande de faire
        'README.md',        // la porte d'entrée
        'composer.json',    // le manifeste
        'composer.lock',    // l'environnement d'essai de l'auteur, pour référence
        'config/',          // les réglages publiables
        'database/',        // les migrations
        'docs/',            // la documentation fait partie de la livraison
        'public/',          // les fichiers déjà compilés
        'resources/',       // les vues · le style et les scripts sont exclus
        'routes/',
        'src/',
    ];

    /** @return list<string> */
    private function archiveEntries(): array
    {
        $root = dirname(__DIR__, 2);

        $output = [];
        $status = 0;
        exec(sprintf('git -C %s archive --worktree-attributes HEAD 2>&1 | tar -t 2>&1', escapeshellarg($root)), $output, $status);

        if ($status !== 0 || $output === []) {
            $this->markTestSkipped('git ou tar indisponible : la composition de l’archive ne peut pas être lue.');
        }

        $top = [];

        foreach ($output as $path) {
            $position = strpos($path, '/');
            $top[] = $position === false ? $path : substr($path, 0, $position + 1);
        }

        $top = array_values(array_unique($top));
        sort($top);

        return $top;
    }

    public function test_it_ships_exactly_what_a_host_needs(): void
    {
        $expected = self::SHIPPED;
        sort($expected);

        $this->assertSame(
            $expected,
            $this->archiveEntries(),
            'L’archive a changé de composition. Ajoutez l’entrée à la liste ci-dessus si un hôte en a '
            .'besoin, sinon à `export-ignore` dans .gitattributes.',
        );
    }

    /**
     * Et les trois choses dont l'absence est le plus coûteuse à découvrir tard.
     *
     * Le style et les scripts sources, parce que **livrés, ils ne peuvent pas
     * servir** · la feuille atteint les couches et le thème du kit par un
     * chemin relatif qui ne mène nulle part une fois le paquet sous les
     * dépendances d'un hôte. Les laisser dit le contraire.
     */
    public function test_it_never_ships_the_build_chain_or_the_bench(): void
    {
        $root = dirname(__DIR__, 2);

        $output = [];
        $status = 0;
        exec(sprintf('git -C %s archive --worktree-attributes HEAD 2>&1 | tar -t 2>&1', escapeshellarg($root)), $output, $status);

        if ($status !== 0 || $output === []) {
            $this->markTestSkipped('git ou tar indisponible.');
        }

        foreach (['resources/css/', 'resources/js/', 'tests/', 'scripts/', 'node_modules/'] as $absent) {
            $this->assertEmpty(
                array_filter($output, fn (string $path): bool => str_starts_with($path, $absent)),
                "L’archive porte {$absent}, qu’un hôte n’a aucune raison de recevoir.",
            );
        }
    }
}
