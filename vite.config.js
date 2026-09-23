import { defineConfig } from 'vite';

/*
 * The collector: the script a host puts on its own public pages.
 *
 * The dashboard's script is built by `vite.admin.config.js`, and Tailwind
 * writes the stylesheet afterwards. Three producers, one directory, none
 * touching the others' files — so this step runs first and is the one allowed
 * to empty it.
 */
export default defineConfig({
    // `public` is where the package puts what it ships, and Vite reads that
    // name as "static files to copy verbatim". Saying so plainly avoids it
    // copying the directory into itself.
    publicDir: false,

    // No `base`: the format below forbids any splitting, so the produced file
    // holds no address to resolve.

    build: {
        outDir: 'public',

        // The only step that clears the directory, and there has to be one.
        emptyOutDir: true,

        // The browsers the collector reaches read the JavaScript of 2015.
        // Without a target, the compressor writes newer syntax of its own —
        // `catch {}` for one — and those browsers then refuse the whole file.
        target: 'es2015',

        rollupOptions: {
            /*
             * **The source is named for what it is, the output for the package
             * that ships it** · `collector.js` says what the file contains to
             * whoever opens it, `analytics.js` is what a host publishes, beside
             * the dashboard's `analytics-admin.js`.
             *
             * This line is where `public/analytics.js` comes from.
             */
            input: {
                analytics: 'resources/js/collector.js',
            },

            output: {
                /*
                 * **An immediately invoked function, not a module**: the kit
                 * emits a CLASSIC tag for a package's file, `<script src … defer>`. A module loaded that
                 * way is treated as an ordinary script, and its export
                 * declarations would throw in the browser.
                 *
                 * The source imports nothing and exports nothing: it already is
                 * an immediately invoked function. This format only keeps it
                 * that way.
                 */
                format: 'iife',
                entryFileNames: '[name].js',
            },
        },
    },
});
