import { defineConfig } from 'vitest/config';

/*
 * Separate from the two Vite configurations, which describe what the package
 * ships. Nothing here reaches the build, and nothing there reaches these tests.
 *
 * `jsdom`, because the components under test read and write the page.
 */
export default defineConfig({
    test: {
        environment: 'jsdom',
        include: ['tests/js/**/*.test.js'],
    },
});
