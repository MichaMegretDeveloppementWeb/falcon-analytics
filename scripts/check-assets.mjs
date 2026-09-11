/*
 * Recompiler a cote, et comparer a ce qui est commite.
 *
 * L'empreinte des sources, elle, dit « une source a change depuis la derniere
 * compilation ». Elle ne dit rien d'une compilation qui ne rendrait pas deux
 * fois le meme fichier · un outil qui glisse une date, un ordre qui depend du
 * systeme de fichiers, une version d'outil differente. C'est ce que ce
 * controle attrape, et lui seul.
 *
 * Il tourne la ou node existe · en integration continue, pas dans la suite PHP.
 */

import { execSync } from 'node:child_process';
import { readdirSync, readFileSync, rmSync } from 'node:fs';
import { join, relative, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('..', import.meta.url));
const shipped = join(root, 'public');
const rebuilt = join(root, '.build-check');

/*
 * `sources.sha` reste dehors · il est ecrit par un script qui vise `public/`
 * en dur, et ce qu'il garantit est deja garanti ailleurs, par un essai PHP.
 */
const ignored = new Set(['sources.sha']);

/** Les fichiers d'un dossier, en chemins relatifs a barres obliques, tries. */
function contentsOf(directory) {
    return readdirSync(directory, { recursive: true, withFileTypes: true })
        .filter((entry) => entry.isFile())
        .map((entry) => relative(directory, join(entry.parentPath, entry.name)).split(sep).join('/'))
        .filter((name) => !ignored.has(name))
        .sort();
}

function run(command) {
    execSync(command, { cwd: root, stdio: 'inherit' });
}

function fail(message) {
    console.error(`\n  ${message}\n`);
    console.error('  Recompilez avec `npm run build`, puis commitez `public/`.\n');
    rmSync(rebuilt, { recursive: true, force: true });
    process.exit(1);
}

run(`tailwindcss -i resources/css/analytics.css -o ${JSON.stringify('.build-check/analytics.css')} --minify`);

const before = contentsOf(shipped);
const after = contentsOf(rebuilt);

const missing = after.filter((name) => !before.includes(name));
const extra = before.filter((name) => !after.includes(name));

if (missing.length > 0) {
    fail(`La recompilation produit des fichiers absents de public/ : ${missing.join(', ')}`);
}

if (extra.length > 0) {
    fail(`public/ porte des fichiers que la recompilation ne produit pas : ${extra.join(', ')}`);
}

for (const name of after) {
    const shippedBytes = readFileSync(join(shipped, name));
    const rebuiltBytes = readFileSync(join(rebuilt, name));

    if (!shippedBytes.equals(rebuiltBytes)) {
        fail(`Le fichier livre ne correspond plus a ses sources : ${name}`);
    }
}

rmSync(rebuilt, { recursive: true, force: true });

console.log(`\n  Reproductible · ${after.length} fichiers identiques a la recompilation.\n`);
