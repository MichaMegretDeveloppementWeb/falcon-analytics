import { defineConfig } from 'vite';

/*
 * The dashboard's script: the Alpine components of the administration screens.
 *
 * A pass of its own, not a second input of `vite.config.js`: the format below
 * takes one entry per build. It runs after the collector's pass, so it
 * adds its file and leaves the directory as it found it.
 */
export default defineConfig({
    publicDir: false,

    build: {
        outDir: 'public',
        emptyOutDir: false,

        rollupOptions: {
            input: {
                'analytics-admin': 'resources/js/analytics-admin.js',
            },

            output: {
                // The kit includes a package's file with a classic deferred
                // tag: an immediately invoked function, never a module.
                format: 'iife',
                entryFileNames: '[name].js',
            },

            // Alpine and Livewire come from the page, never from this bundle.
            external: ['alpinejs', 'livewire'],
        },
    },
});
