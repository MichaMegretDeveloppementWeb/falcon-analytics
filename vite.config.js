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

    // No `base` here, unlike the kit. It needs one to have its on-demand chunk
    // fetched from beside the script that asks for it. The format below forbids
    // any splitting, so no address is written into the produced file and there
    // is nothing to resolve.

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
             * that ships it**, and the two therefore differ · the skeleton has
             * them matching, as `ui.js` does in the kit.
             *
             * `collector.js` says what the file contains to whoever opens it.
             * `analytics.js` is what a host publishes, beside the dashboard's
             * `analytics-admin.js`. Renaming either would lose one of the two
             * readings.
             *
             * This is the line to come back to when asking where
             * `public/analytics.js` is written.
             */
            input: {
                analytics: 'resources/js/collector.js',
            },

            output: {
                /*
                 * **An immediately invoked function, not a module**, and this
                 * is not a matter of taste: the kit emits a CLASSIC tag for a
                 * package's file, `<script src … defer>`. A module loaded that
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
