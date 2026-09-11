<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Aucune classe du paquet ne sort sans son préfixe.
 *
 * C'est ce qui permet à la feuille du paquet de partager une page avec celle
 * du kit et celle de l'hôte sans que l'une annule l'autre · deux noms
 * différents ne se battent pas. Une même règle simple et sa variante
 * responsive ont la même spécificité au-delà du point de rupture, et c'est
 * alors la feuille chargée en second qui gagne, en silence.
 *
 * **Une classe oubliée n'est pas générée du tout** · le générateur ne connaît
 * que les noms préfixés, donc l'élément se dessine sans style et rien ne le
 * dit. C'est la panne que cet essai refuse.
 *
 * Il vaut surtout pour plus tard · il attrape l'écran qu'on ajoutera dans six
 * mois en recopiant du Tailwind ordinaire.
 *
 * Il ne lit pas les commentaires · un `bg-white` cité dans une explication n'a
 * jamais rien dessiné.
 *
 * Pas de base de données ici, donc pas de `TestCase` du paquet · il lit des
 * fichiers, et rien d'autre.
 */
final class EveryClassCarriesThePrefixTest extends TestCase
{
    /**
     * Des formes qui n'appartiennent qu'à Tailwind, et qui ne peuvent donc pas
     * être autre chose qu'une classe oubliée.
     *
     * Les variantes d'abord · leur forme est sans ambiguïté.
     *
     * **Ce qui manque ici ne sera pas attrapé**, et c'est le seul défaut de
     * cette approche. La liste couvre donc tout ce que les vues emploient,
     * relevé sur elles, et pas seulement les familles les plus courantes.
     *
     * Une famille en est volontairement absente · `cursor-`, parce que le kit
     * a une icône nommée `cursor-arrow-rays` et qu'aucune règle de forme ne
     * distingue un nom d'icône d'une classe.
     *
     * @var list<string>
     */
    private const SHAPES = [
        'dark:', 'hover:', 'focus:', 'focus-within:', 'group-hover:', 'disabled:', 'peer-checked:',
        'sm:', 'md:', 'lg:', 'xl:', 'wide:', 'max-md:', 'last:', 'first:', 'placeholder:',
        'bg-', 'text-', 'border-', 'rounded-', 'ring-', 'shadow-', 'divide-', 'fill-', 'stroke-',
        'px-', 'py-', 'pt-', 'pb-', 'pl-', 'pr-', 'p-',
        'mx-', 'my-', 'mt-', 'mb-', 'ml-', 'mr-',
        'gap-', 'gap-x-', 'gap-y-', 'space-x-', 'space-y-',
        'w-', 'h-', 'min-w-', 'min-h-', 'max-w-', 'max-h-',
        'flex-', 'items-', 'justify-', 'grid-cols-', 'col-span-', 'row-span-',
        'shrink-', 'grow-', 'order-', 'self-', 'place-', 'content-', 'object-', 'aspect-',
        'font-', 'leading-', 'tracking-', 'align-', 'whitespace-', 'break-', 'truncate-',
        'opacity-', 'overflow-', 'transition-', 'animate-', 'backdrop-', 'tabular-',
        'inset-', 'top-', 'bottom-', 'left-', 'right-', 'z-', 'pointer-events-',
    ];

    public function test_no_view_writes_an_unprefixed_class(): void
    {
        $found = [];

        foreach ($this->views() as $name => $file) {
            $source = $this->withoutComments((string) file_get_contents($file));

            preg_match_all('/[A-Za-z0-9:_.\/%\[\]#!-]+/', $source, $matches);

            foreach ($matches[0] as $token) {
                if ($this->looksLikeAForgottenClass($token)) {
                    $found[] = "{$name} · {$token}";
                }
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($found)),
            "Ces classes n'ont pas leur préfixe. Elles ne seront pas générées, et l'élément\n"
            .'se dessinera sans style, sans la moindre erreur. Écrivez-les `an:…`.',
        );
    }

    /**
     * Des noms qui portent la forme d'un utilitaire sans en être un.
     *
     * Ce sont des attributs de présentation SVG, écrits sur un `<path>` ou
     * passés à un composant du kit · `stroke-width="2.5"`. Les préfixer les
     * casserait, et les ignorer ne cache rien · une vraie classe de la même
     * famille s'écrit `fill-gray-800`, jamais `fill-opacity`.
     *
     * @var list<string>
     */
    private const SVG_ATTRIBUTES = [
        'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-opacity', 'stroke-dasharray',
        'fill-opacity', 'fill-rule', 'fill-box',
    ];

    private function looksLikeAForgottenClass(string $token): bool
    {
        // Déjà préfixée, ou une classe crochet écrite à la main.
        if (str_starts_with($token, 'an:') || str_starts_with($token, 'an-')) {
            return false;
        }

        if (in_array($token, self::SVG_ATTRIBUTES, true)) {
            return false;
        }

        foreach (self::SHAPES as $shape) {
            if (str_starts_with($token, $shape)) {
                return true;
            }
        }

        return false;
    }

    /** Un commentaire n'a jamais rien dessiné · il sort du champ. */
    private function withoutComments(string $source): string
    {
        foreach (['/\{\{--.*?--\}\}/s', '/<!--.*?-->/s', '/\/\*.*?\*\//s', '/(^|\s)\/\/[^\n]*/m'] as $shape) {
            $source = (string) preg_replace($shape, ' ', $source);
        }

        return $source;
    }

    /** @return array<string, string> */
    private function views(): array
    {
        $root = dirname(__DIR__, 2).'/resources/views';

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        $found = [];

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && str_ends_with($file->getFilename(), '.blade.php')) {
                $found[str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1))] = $file->getPathname();
            }
        }

        ksort($found);

        return $found;
    }
}
