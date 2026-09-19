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

    /**
     * What the archive would carry if the working tree were committed now.
     *
     * `git archive` reads a commit, and a file not committed yet is missing
     * from it: the test would pass until the very commit that ships the file.
     * So the working tree is written into a tree of its own, through an index
     * of its own, and the repository's index is left as it was.
     *
     * `--worktree-attributes`, for the same reason · a `.gitattributes` modified
     * and not committed yet is the one read.
     *
     * @return list<string>
     */
    private function archive(): array
    {
        $root = escapeshellarg(dirname(__DIR__, 2));
        $index = sys_get_temp_dir().DIRECTORY_SEPARATOR.'falcon-archive-index-'.getmypid();
        $added = 1;
        $written = 1;
        $tree = '';

        putenv("GIT_INDEX_FILE={$index}");

        try {
            exec("git -C {$root} add --all 2>&1", $unused, $added);
            $tree = trim((string) exec("git -C {$root} write-tree 2>&1", $unused, $written));
        } finally {
            putenv('GIT_INDEX_FILE');

            if (is_file($index)) {
                unlink($index);
            }
        }

        $output = [];
        $status = 0;
        exec(sprintf('git -C %s archive --worktree-attributes %s 2>&1 | tar -t 2>&1', $root, escapeshellarg($tree)), $output, $status);

        if ($added !== 0 || $written !== 0 || $status !== 0 || $output === []) {
            $this->markTestSkipped('git ou tar indisponible : la composition de l’archive ne peut pas être lue.');
        }

        return $output;
    }

    /** @return list<string> */
    private function archiveEntries(): array
    {
        $top = [];

        foreach ($this->archive() as $path) {
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
        $output = $this->archive();

        foreach (['resources/css/', 'resources/js/', 'tests/', 'scripts/', 'node_modules/'] as $absent) {
            $this->assertEmpty(
                array_filter($output, fn (string $path): bool => str_starts_with($path, $absent)),
                "L’archive porte {$absent}, qu’un hôte n’a aucune raison de recevoir.",
            );
        }
    }
}
