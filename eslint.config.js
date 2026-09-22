import js from '@eslint/js';
import globals from 'globals';

/*
 * The same rules as the kit, and deliberately the same: a variable kept without
 * being used, a comparison that converts behind your back, a declaration whose
 * scope spills over, a promise nobody awaits.
 *
 * **The collector is what this is for.** It is 308 lines that run on every page
 * of every visitor of every host — the most executed script the suite ships —
 * and until 2026-09-13 it was the only one nothing read mechanically. The kit
 * had a linter from the start; this package simply never got one.
 */
const rules = {
    'no-unused-vars': ['error', {
        argsIgnorePattern: '^_',
        varsIgnorePattern: '^_',

        // A `catch` whose error is deliberately dropped says so by naming it
        // `_e`. The alternative — `catch {}` — is ES2019, which the collector
        // cannot use.
        caughtErrorsIgnorePattern: '^_',
    }],
    eqeqeq: ['error', 'always'],
    'no-var': 'error',
    'prefer-const': 'error',
    'no-shadow': 'error',
    'require-await': 'error',
    'no-promise-executor-return': 'error',
};

export default [
    {
        /*
         * `public/` is produced by vite: the linter would be judging compiler
         * output, which says nothing about what anyone wrote. `.build-check/`
         * is the same thing, rebuilt for a comparison.
         */
        ignores: ['public/**', '.build-check/**', 'node_modules/**', 'vendor/**'],
    },

    js.configs.recommended,

    {
        /* What the package sends to the browser. */
        files: ['resources/js/**/*.js'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                ...globals.browser,

                /*
                 * Supplied by the host or by the kit, never imported here:
                 * without this declaration every use would come back as an
                 * unknown symbol and drown the report.
                 */
                Alpine: 'readonly',
                Livewire: 'readonly',
            },
        },
        rules,
    },

    {
        /*
         * The collector, which reaches the browsers that can send a beacon ·
         * they read the JavaScript of 2015 and no later, so its source is read
         * as such, and a classic script rather than a module.
         *
         * **One rule is relaxed, because it would have broken it.** `x != null`
         * is the deliberate test for « neither null nor undefined »; rewritten
         * as `!== null` it would let `undefined` through, and `--fix` would
         * have done it without a word. So `eqeqeq` keeps its strictness except
         * against `null`, which is ESLint's own option for exactly this.
         */
        files: ['resources/js/collector.js'],
        languageOptions: {
            ecmaVersion: 2015,
            sourceType: 'script',
        },
        rules: {
            ...rules,
            eqeqeq: ['error', 'always', { null: 'ignore' }],
        },
    },

    {
        /* The build chain, which runs under node. */
        files: ['scripts/**/*.{js,mjs}', 'vite.config.js', 'vite.admin.config.js', 'vitest.config.js'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: globals.node,
        },
        rules,
    },

    {
        /*
         * The script tests · a browser's globals, since that is the environment
         * they run the package's own files in, plus node's for the runner.
         */
        files: ['tests/js/**/*.js'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: { ...globals.browser, ...globals.node },
        },
        rules,
    },
];
