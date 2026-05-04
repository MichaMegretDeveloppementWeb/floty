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
        // Tests sous tests/js/ (séparé du source, miroir resources/js/)
        // — cohérence avec PHPUnit + lisibilité de la couverture (ADR-0013 R13).
        include: ['tests/js/**/*.{test,spec}.ts'],
        // Mock global de `@inertiajs/vue3` (cf. ADR-0020) pour tester
        // les composables qui consomment `router.reload` etc.
        setupFiles: ['./tests/js/setup/inertia-mock.ts'],
        css: false,
        coverage: {
            provider: 'v8',
            // HTML pour inspection locale, lcov pour outils CI, json-summary
            // pour seuils.
            reporter: ['text', 'html', 'lcov', 'json-summary'],
            reportsDirectory: './coverage/js',
            // Périmètre source : on instrumente uniquement les composables
            // / utils / pages testables (la couverture de composants .vue
            // purs présentationnels n'apporte rien et fausse les chiffres).
            include: [
                'resources/js/Composables/**/*.ts',
                'resources/js/Utils/**/*.ts',
                'resources/js/lib/**/*.ts',
            ],
            exclude: [
                '**/*.d.ts',
                '**/types/**',
                'resources/js/Composables/**/*.test.ts',
            ],
            // Pas de seuil bloquant en V1 ; on observe la trend en CI
            // d'abord. Seuils strict à activer après ~10 PR (cf. ADR
            // futur si besoin).
        },
    },
});
