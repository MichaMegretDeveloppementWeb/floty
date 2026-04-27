import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vitest/config';

/**
 * Configuration Vitest — tests unitaires des composables et utils
 * frontend.
 *
 * - `happy-dom` plus rapide que `jsdom` pour les hooks simples
 * - alias `@` aligné avec `vite.config.ts` et `tsconfig.json`
 * - inclut uniquement les `*.test.ts` / `*.spec.ts` sous `resources/js/`
 */
export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'happy-dom',
        globals: true,
        include: ['resources/js/**/*.{test,spec}.ts'],
        css: false,
    },
});
