/*
 * L'empreinte des sources qui decident de ce que le paquet livre.
 *
 * Ecrite a chaque compilation, relue par `AssetsAreUpToDateTest`. C'est ce qui
 * transforme « il faut penser a recompiler » en un essai qui echoue.
 *
 * Ce qui entre dedans : le script, les feuilles de style, ET LES VUES. Les vues
 * comptent parce que le generateur d'utilitaires les lit · une classe ajoutee
 * dans un ecran change la feuille livree aussi surement qu'une regle ecrite a
 * la main.
 *
 * Les fins de ligne sont normalisees avant hachage : sans cela l'empreinte
 * change entre un poste Windows et un poste Unix, et l'essai echouerait sur une
 * difference qui n'existe pas.
 */

import { createHash } from 'node:crypto';
import { mkdirSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join, relative, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

/*
 * `fileURLToPath` et non `.pathname` · une URL est encodee, un chemin ne l'est
 * pas. Le paquet pose dans un dossier dont le nom porte une espace donnait un
 * `%20` litteral, et la compilation s'arretait sur un fichier introuvable.
 *
 * Elle enleve aussi la barre oblique qui precede la lettre de lecteur sous
 * Windows.
 */
const root = fileURLToPath(new URL('..', import.meta.url));

/** Tous les fichiers d'un dossier dont le nom finit par un des suffixes donnes. */
function filesUnder(directory, ...suffixes) {
    return readdirSync(join(root, directory), { recursive: true, withFileTypes: true })
        .filter((entry) => entry.isFile() && suffixes.some((suffix) => entry.name.endsWith(suffix)))
        .map((entry) => join(entry.parentPath, entry.name));
}

const files = [
    ...filesUnder(join('resources', 'js'), '.js'),
    ...filesUnder(join('resources', 'css'), '.css'),
    ...filesUnder(join('resources', 'views'), '.blade.php'),
]
    // Trie sur le chemin relatif en barres obliques : l'ordre doit etre le meme
    // quel que soit le systeme, sinon l'empreinte l'est aussi.
    .map((file) => [relative(root, file).split(sep).join('/'), file])
    .sort(([a], [b]) => (a < b ? -1 : a > b ? 1 : 0));

const hash = createHash('sha256');

for (const [name, file] of files) {
    hash.update(name);
    hash.update(readFileSync(file, 'utf8').replace(/\r\n/g, '\n'));
}

mkdirSync(join(root, 'public'), { recursive: true });
writeFileSync(join(root, 'public', 'sources.sha'), `${hash.digest('hex')}\n`);

console.log(`empreinte des sources ecrite · ${files.length} fichiers`);
