import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { ESLint } from 'eslint';
import { describe, expect, test } from 'vitest';

/*
 * The collector reaches the browsers that can send a beacon, which read the
 * JavaScript of 2015 and no later. The shipped file is read as such, because
 * a compressor that rewrites it into newer syntax would take those browsers
 * away without anything else saying so.
 */

const reader = new ESLint({
    overrideConfigFile: true,
    overrideConfig: { languageOptions: { ecmaVersion: 2015, sourceType: 'script' } },
});

/** What a browser of 2015 cannot read in that code. */
async function unreadable(code) {
    const [result] = await reader.lintText(code);

    return result.messages.filter((message) => message.fatal).map((message) => message.message);
}

describe('the shipped collector', () => {
    test('reads as the JavaScript of 2015', async () => {
        const shipped = readFileSync(join(import.meta.dirname, '../../public/analytics.js'), 'utf8');

        expect(await unreadable(shipped)).toEqual([]);
    });

    test('and the reading refuses what those browsers cannot read', async () => {
        expect(await unreadable('var a = b ?? c;')).not.toEqual([]);
    });
});
