/*
 * Rebuild beside, and compare with what is committed.
 *
 * The source fingerprint says "a source changed since the last build". It says
 * nothing about a build that would not produce the same file twice: a tool
 * slipping a date in, an order that depends on the filesystem, a different tool
 * version. That is what this check catches, and only this one.
 *
 * It runs where node exists: in continuous integration, not in the PHP suite.
 */

import { execSync } from 'node:child_process';
import { readdirSync, readFileSync, rmSync } from 'node:fs';
import { join, relative, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('..', import.meta.url));
const shipped = join(root, 'public');
const rebuilt = join(root, '.build-check');

/*
 * `sources.sha` stays out: it is written by a script that targets `public/`
 * outright, and what it guarantees is already guaranteed elsewhere, by a PHP
 * test.
 */
const ignored = new Set(['sources.sha']);

/** A directory's files, as sorted relative paths with forward slashes. */
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

run(`vite build --outDir ${JSON.stringify(relative(root, rebuilt))} --emptyOutDir`);
run(`vite build --config vite.admin.config.js --outDir ${JSON.stringify(relative(root, rebuilt))}`);
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
        fail(`Le fichier livré ne correspond plus à ses sources : ${name}`);
    }
}

rmSync(rebuilt, { recursive: true, force: true });

console.log(`\n  Reproductible · ${after.length} fichiers identiques à la recompilation.\n`);
