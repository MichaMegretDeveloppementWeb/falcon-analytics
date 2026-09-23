/*
 * A fingerprint of the sources that decide what the package ships.
 *
 * Written on every build, read back by `AssetsAreUpToDateTest`. This is what
 * turns "remember to rebuild" into a test that fails.
 *
 * What goes into it: the script, the stylesheets, AND THE VIEWS. The views
 * count because the utility generator reads them — a class added to a screen
 * changes the shipped stylesheet as surely as a hand-written rule does. And
 * the world map, copied as it stands.
 *
 * Line endings are normalised before hashing: without that the fingerprint
 * differs between a Windows machine and a Unix one, and the test would fail on
 * a difference that does not exist.
 */

import { createHash } from 'node:crypto';
import { mkdirSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join, relative, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

/*
 * `fileURLToPath`, not `.pathname`: a URL is encoded, a path is not, so a
 * directory whose name carries a space would come back as a literal `%20` and
 * name a file that does not exist.
 *
 * It also strips the slash that precedes the drive letter under Windows.
 */
const root = fileURLToPath(new URL('..', import.meta.url));

/** Every file under a directory whose name ends with one of the given suffixes. */
function filesUnder(directory, ...suffixes) {
    return readdirSync(join(root, directory), { recursive: true, withFileTypes: true })
        .filter((entry) => entry.isFile() && suffixes.some((suffix) => entry.name.endsWith(suffix)))
        .map((entry) => join(entry.parentPath, entry.name));
}

const files = [
    ...filesUnder(join('resources', 'js'), '.js'),
    ...filesUnder(join('resources', 'css'), '.css'),
    ...filesUnder(join('resources', 'views'), '.blade.php'),
    ...filesUnder(join('resources', 'svg'), '.svg'),
]
    // Sorted on the relative path with forward slashes: the order has to be the
    // same whatever the system, otherwise so is the fingerprint.
    .map((file) => [relative(root, file).split(sep).join('/'), file])
    .sort(([a], [b]) => (a < b ? -1 : a > b ? 1 : 0));

const hash = createHash('sha256');

for (const [name, file] of files) {
    hash.update(name);
    hash.update(readFileSync(file, 'utf8').replace(/\r\n/g, '\n'));
}

mkdirSync(join(root, 'public'), { recursive: true });
writeFileSync(join(root, 'public', 'sources.sha'), `${hash.digest('hex')}\n`);

console.log(`empreinte des sources écrite · ${files.length} fichiers`);
