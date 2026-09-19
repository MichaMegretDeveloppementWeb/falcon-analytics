/*
 * The world's base map, shipped as a file of its own so the browser keeps it
 * between the refreshes of the realtime screen.
 *
 * Copied as it stands, into `public/` or into the directory given: the
 * reproducibility check rebuilds beside.
 */

import { copyFileSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('..', import.meta.url));
const target = join(root, process.argv[2] ?? 'public');

mkdirSync(target, { recursive: true });
copyFileSync(join(root, 'resources', 'svg', 'world-map.svg'), join(target, 'world-map.svg'));

console.log('carte du monde copiée');
