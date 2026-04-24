# Fiche — Configuration Vitest Floty

> **Tâche associée** : `tasks/phase-00-init/03-install-vitest.md`
> **Références** : `implementation-rules/tests-frontend.md`

---

## Packages à installer

```bash
npm install -D vitest @vue/test-utils @pinia/testing happy-dom
npm install -D @testing-library/vue @testing-library/dom
npm install -D @vitest/coverage-v8 @vitest/ui
```

Optionnel (utile pour CI) :
```bash
npm install -D @vitest/browser  # si un jour on ajoute browser mode
```

## `vitest.config.ts`

À la racine du projet :

```ts
import { defineConfig, mergeConfig } from 'vitest/config'
import viteConfig from './vite.config'

export default mergeConfig(viteConfig, defineConfig({
  test: {
    globals: true,                // expect/describe/it globaux, pas besoin d'import
    environment: 'happy-dom',     // DOM léger, plus rapide que jsdom
    setupFiles: ['./resources/js/test-setup.ts'],
    include: ['resources/js/**/*.spec.ts'],
    exclude: ['node_modules/**', 'vendor/**', 'public/**'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html', 'lcov'],
      include: ['resources/js/**/*.{ts,vue}'],
      exclude: [
        'node_modules/**',
        'resources/js/types/**',           // générés
        'resources/js/actions/**',         // générés par Wayfinder
        'resources/js/routes/**',          // générés par Wayfinder
        'resources/js/wayfinder/**',       // généré
        '**/*.config.ts',
        '**/test-setup.ts',
        '**/*.spec.ts',
      ],
    },
  },
}))
```

## `resources/js/test-setup.ts`

```ts
import { config } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { vi } from 'vitest'

// Pinia testing global (stubActions: false → actions réelles, on mock au cas par cas)
config.global.plugins = [createTestingPinia({ stubActions: false })]

// Stub global <Link> Inertia pour que les tests n'aient pas besoin de Router monté
config.global.stubs = {
  Link: { template: '<a><slot /></a>' },
  // D'autres composants Inertia peuvent s'ajouter ici au besoin (Deferred, Head, etc.)
}

// Mock Wayfinder : les fonctions Wayfinder retournent `{ url, method }`.
// Dans les tests on les mock par pattern car le fichier `@/actions` est généré et
// n'existe peut-être pas dans l'environnement test selon quand on a lancé
// php artisan wayfinder:generate.
vi.mock('@/actions/App/Http/Controllers/User/VehicleController', () => ({
  default: {
    index: () => ({ url: '/app/vehicles', method: 'get' }),
    show: (params: { vehicle: number }) => ({ url: `/app/vehicles/${params.vehicle}`, method: 'get' }),
    store: () => ({ url: '/app/vehicles', method: 'post' }),
    update: (params: { vehicle: number }) => ({ url: `/app/vehicles/${params.vehicle}`, method: 'put' }),
    destroy: (params: { vehicle: number }) => ({ url: `/app/vehicles/${params.vehicle}`, method: 'delete' }),
  },
}), { virtual: true })

// Autres mocks Wayfinder à ajouter au fur et à mesure des controllers créés.

// Mock Inertia usePage pour avoir des shared props par défaut
vi.mock('@inertiajs/vue3', async () => {
  const actual = await vi.importActual<typeof import('@inertiajs/vue3')>('@inertiajs/vue3')
  return {
    ...actual,
    usePage: () => ({
      props: {
        flash: { success: null, error: null, warning: null, info: null },
        auth: { user: null },
        appName: 'Floty',
      },
    }),
    router: {
      get: vi.fn(),
      post: vi.fn(),
      put: vi.fn(),
      patch: vi.fn(),
      delete: vi.fn(),
      visit: vi.fn(),
      reload: vi.fn(),
      cancelAll: vi.fn(),
      on: vi.fn(),
    },
  }
})
```

## Scripts `package.json`

```json
{
  "scripts": {
    "test": "vitest",
    "test:ci": "vitest run",
    "test:coverage": "vitest run --coverage",
    "test:ui": "vitest --ui"
  }
}
```

## Smoke test initial

Créer `resources/js/test-setup.spec.ts` :

```ts
import { describe, it, expect } from 'vitest'

describe('Vitest setup', () => {
  it('runs', () => {
    expect(true).toBe(true)
  })

  it('has happy-dom', () => {
    const div = document.createElement('div')
    div.textContent = 'hello'
    expect(div.textContent).toBe('hello')
  })
})
```

Lancer :
```bash
npm run test:ci
```

Doit afficher :
```
 ✓ resources/js/test-setup.spec.ts  (2)
   ✓ Vitest setup (2)
     ✓ runs
     ✓ has happy-dom
 Test Files  1 passed (1)
 Tests       2 passed (2)
```

## Notes

- **`happy-dom` vs `jsdom`** : on choisit happy-dom pour la vitesse (2-3× plus rapide que jsdom).
- **Mock Wayfinder** : les fonctions Wayfinder sont toutes mockées dans `test-setup.ts`. Au fur et à mesure qu'on ajoute des controllers, on complète la liste.
- **`createTestingPinia({ stubActions: false })`** : on utilise les vraies actions des stores dans les tests (plus précis). On peut override au cas par cas avec `stubActions: true` pour espionner.
- **Coverage** : exclure les fichiers générés (Wayfinder, Spatie types) de la coverage pour éviter de gonfler artificiellement.
- **Watch mode en dev** : lancer `npm run test` pour le mode watch. `npm run test:ui` pour l'UI graphique.
