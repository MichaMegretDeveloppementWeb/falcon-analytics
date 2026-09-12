import { defineConfig } from 'vite';

/*
 * The script the package ships: the collector, and nothing else.
 *
 * Tailwind writes the stylesheet into the same directory afterwards, from the
 * command line. Two producers, one directory, neither touching the other's
 * files — so this step runs first and is the one allowed to empty it.
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

        rollupOptions: {
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
