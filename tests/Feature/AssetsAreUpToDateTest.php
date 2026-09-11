<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Ce que le paquet livre correspond-il aux sources qui le produisent ?
 *
 * Le paquet compile et livre du compilé · une feuille. Une application la
 * publie et la sert sans jamais la refabriquer, donc un fichier périmé lui
 * donne des écrans mal dessinés sans le moindre message.
 *
 * Le défaut est facile à commettre · on modifie une vue, on le voit marcher en
 * local parce qu'on vient de compiler, et on pousse sans rejouer la
 * compilation.
 *
 * **Les vues comptent autant que le CSS.** Le générateur d'utilitaires les lit ·
 * une classe ajoutée dans un écran change la feuille livrée aussi sûrement
 * qu'une règle écrite à la main.
 *
 * L'empreinte est écrite par `scripts/fingerprint.mjs`, appelé par
 * `npm run build`. Ce que cet essai ne peut pas dire, c'est si la compilation
 * rend deux fois le même fichier · ça demande node, et c'est le rôle de
 * `npm run check-assets`.
 *
 * Pas de base de données ici · il lit des fichiers, et rien d'autre.
 */
final class AssetsAreUpToDateTest extends TestCase
{
    public function test_the_shipped_files_exist(): void
    {
        foreach (['analytics.css', 'sources.sha'] as $file) {
            $this->assertFileExists(
                $this->publicPath($file),
                $file.' est absent de public/. Lancez `npm run build`.',
            );
        }
    }

    /**
     * Les huit couches, toutes présentes et dans cet ordre.
     *
     * C'est le contrat qui permet à la feuille du kit, à celle du paquet et à
     * celle de l'application de cohabiter · la première déclaration rencontrée
     * fixe l'ordre pour tout le document, et une feuille chargée plus tard ne
     * peut pas réordonner ce qui est déjà déclaré.
     *
     * Le compilateur a le droit de découper la déclaration — il pose les
     * couches qu'il remplit puis nomme le reste — et d'en ajouter des siennes,
     * une couche `properties` en tête. Elle ne réordonne rien : on lit donc
     * l'ordre RELATIF des huit, qui est le contrat, et non la liste brute.
     */
    public function test_the_eight_layers_are_declared_in_the_agreed_order(): void
    {
        $contract = [
            'theme', 'base', 'ui-components', 'ui-utilities',
            'pkg-components', 'pkg-utilities', 'components', 'utilities',
        ];

        $this->assertSame(
            $contract,
            array_values(array_intersect($this->layersDeclaredIn('analytics.css'), $contract)),
            "L'ordre des couches de analytics.css n'est plus celui de la suite.",
        );
    }

    /**
     * La feuille porte vraiment les utilitaires du paquet.
     *
     * Une feuille qui ne contiendrait que la ligne des couches passerait les
     * deux essais ci-dessus sans rien dessiner · c'est exactement l'état où le
     * paquet se trouvait avant que ses vues portent leur préfixe.
     */
    public function test_the_sheet_carries_prefixed_utilities(): void
    {
        $css = (string) file_get_contents($this->publicPath('analytics.css'));

        $this->assertGreaterThan(
            200,
            substr_count($css, '.an\:'),
            'La feuille ne porte presque aucune règle préfixée · le scan des vues a-t-il trouvé quelque chose ?',
        );

        $this->assertStringNotContainsString(
            'box-sizing',
            $css,
            'Le reset appartient au kit · deux resets sur une page se battent.',
        );
    }

    /**
     * Une source a-t-elle changé depuis la dernière compilation ?
     *
     * C'est ce qui transforme « il faut penser à recompiler » en un essai qui
     * échoue.
     */
    public function test_the_fingerprint_matches_the_sources(): void
    {
        $this->assertSame(
            $this->fingerprintOfSources(),
            trim((string) file_get_contents($this->publicPath('sources.sha'))),
            'Une source a changé depuis la dernière compilation. Lancez `npm run build`, puis commitez `public/`.',
        );
    }

    /**
     * Les couches dans l'ordre où le document les établit.
     *
     * Une couche compte à sa première apparition, qu'elle soit ouverte avec du
     * contenu ou seulement nommée dans une liste.
     *
     * @return list<string>
     */
    private function layersDeclaredIn(string $sheet): array
    {
        preg_match_all(
            '/@layer\s+([a-z0-9, -]+?)\s*[{;]/',
            (string) file_get_contents($this->publicPath($sheet)),
            $matches,
        );

        $seen = [];

        foreach ($matches[1] as $declaration) {
            foreach (explode(',', $declaration) as $layer) {
                $layer = trim($layer);

                if ($layer !== '' && ! in_array($layer, $seen, true)) {
                    $seen[] = $layer;
                }
            }
        }

        return $seen;
    }

    /**
     * Le même calcul que `scripts/fingerprint.mjs`, et il doit le rester.
     *
     * Les fins de ligne sont normalisées, sans quoi l'empreinte différerait
     * entre un poste Windows et un poste Unix pour un contenu identique.
     */
    private function fingerprintOfSources(): string
    {
        $root = $this->packagePath('');

        $named = [];

        foreach ($this->sourceFiles() as $file) {
            $named[str_replace('\\', '/', substr($file, strlen($root)))] = $file;
        }

        ksort($named, SORT_STRING);

        $hash = hash_init('sha256');

        foreach ($named as $name => $file) {
            hash_update($hash, $name);
            hash_update($hash, str_replace("\r\n", "\n", (string) file_get_contents($file)));
        }

        return hash_final($hash);
    }

    /** @return list<string> */
    private function sourceFiles(): array
    {
        $found = [];

        foreach ([['resources/css', '.css'], ['resources/views', '.blade.php']] as [$directory, $suffix]) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->packagePath($directory), FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && str_ends_with($file->getFilename(), $suffix)) {
                    $found[] = $file->getPathname();
                }
            }
        }

        return $found;
    }

    private function packagePath(string $path): string
    {
        return rtrim(dirname(__DIR__, 2).'/'.ltrim($path, '/'), '/').($path === '' ? '/' : '');
    }

    private function publicPath(string $file): string
    {
        return dirname(__DIR__, 2).'/public/'.$file;
    }
}
