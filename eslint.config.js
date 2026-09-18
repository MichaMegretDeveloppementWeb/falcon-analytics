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
         * The collector, which is written in ES5 on purpose.
         *
         * It runs on every page of every visitor of every host, it is wrapped
         * in an immediately invoked function, it declares `'use strict'`
         * itself, and nothing transpiles it — the build has no target, so
         * whatever syntax the source uses is the syntax that ships.
         *
         * **Two rules therefore do not apply here, and one of them would have
         * broken it.** `x != null` is the deliberate test for « neither null
         * nor undefined »; rewritten as `!== null` it would let `undefined`
         * through. Four of them, and `--fix` would have changed all four
         * without a word. So `eqeqeq` keeps its strictness except against
         * `null`, which is ESLint's own option for exactly this.
         *
         * `var` is the same choice, one step wider. It is not exempted because
         * `var` is good — it is exempted because rewriting 38 declarations in
         * a script that runs everywhere is a decision about which browsers this
         * collector must reach, and that decision is not written down anywhere
         * yet. The day it is, this block is what changes.
         */
        files: ['resources/js/collector.js'],
        rules: {
            ...rules,
            'no-var': 'off',
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
