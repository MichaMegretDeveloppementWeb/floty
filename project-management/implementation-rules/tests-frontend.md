# Tests frontend — Vitest, Vue Test Utils, Testing Library

> **Stack référence** : Vitest 4.1, Vue Test Utils 2.4, `@pinia/testing`, Vue 3.5, TypeScript 6.
> **Niveau d'exigence** : senior +. On teste ce qui apporte de la confiance, pas ce qui rassure faussement. Pas de tests qui reproduisent le template, pas de tests fragiles, pas de mocks de l'univers entier.
> **Documents liés** : `vue-composants.md`, `composables-services-utils.md`, `pinia-stores.md`, `typescript-dto.md`, `inertia-navigation.md`, `gestion-erreurs.md`.

---

## Pourquoi cette règle existe

Les tests frontend sont **piégés** : il est facile d'écrire beaucoup de tests qui passent mais ne détectent rien (couverture élevée, valeur faible). À l'inverse, l'absence de tests sur les zones critiques (composables, calculs, formulaires) coûte cher en bugs à long terme.

Cette règle pose :

- **Quoi tester** (et quoi ne pas tester).
- **Avec quels outils** (Vitest + Vue Test Utils + parfois Testing Library).
- **Comment** (patterns, fixtures, mocks).
- **Anti-patterns** repérés en revue senior.

---

## Stack et outils

### Vitest 4.1

Test runner natif Vite : intègre la même config que l'app (resolve, plugins Vue/TS, alias). Rapide, watch mode performant, compat Jest API.

### Vue Test Utils 2.4

Utilitaires officiels Vue 3 pour monter des composants en test : `mount`, `shallowMount`, `flushPromises`, `nextTick`.

### `@pinia/testing`

Helpers pour mocker les stores Pinia dans les tests de composants : `createTestingPinia`.

### Testing Library (`@testing-library/vue`) — optionnel mais recommandé

Approche **comportementale** plutôt que technique : on teste ce que l'utilisateur voit/fait, pas l'implémentation. Préféré pour les composants UI Kit et formulaires complexes.

> **Choix Floty V1** : Vitest + Vue Test Utils par défaut, Testing Library en complément pour les composants à fort volet utilisateur (formulaires, modals, sélecteurs interactifs).

---

## Configuration Vitest

```ts
// vitest.config.ts
import { defineConfig, mergeConfig } from 'vitest/config'
import viteConfig from './vite.config'

export default mergeConfig(viteConfig, defineConfig({
  test: {
    globals: true,
    environment: 'happy-dom', // léger et rapide vs jsdom (préféré pour Floty)
    setupFiles: ['./resources/js/test-setup.ts'],
    include: ['resources/js/**/*.spec.ts'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html', 'lcov'],
      exclude: [
        'node_modules/**',
        'resources/js/types/generated.d.ts',
        '**/*.config.ts',
        '**/test-setup.ts',
      ],
    },
  },
}))
```

### Setup global

```ts
// resources/js/test-setup.ts
import { config } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { vi } from 'vitest'

// Pinia testing par défaut
config.global.plugins = [createTestingPinia({ stubActions: false })]

// Stub Inertia <Link> globalement (pour ne pas avoir à le re-stubber dans chaque test)
config.global.stubs = {
  Link: { template: '<a><slot /></a>' },
}

// Mock des modules Wayfinder générés — voir docs/vitest-configuration.md
// pour la liste complète des mocks par controller.
vi.mock('@/actions/App/Http/Controllers/User/VehicleController', () => ({
  default: {
    index: () => ({ url: '/app/vehicles', method: 'get' }),
    show: (params: { vehicle: number }) => ({ url: `/app/vehicles/${params.vehicle}`, method: 'get' }),
    store: () => ({ url: '/app/vehicles', method: 'post' }),
    update: (params: { vehicle: number }) => ({ url: `/app/vehicles/${params.vehicle}`, method: 'put' }),
    destroy: (params: { vehicle: number }) => ({ url: `/app/vehicles/${params.vehicle}`, method: 'delete' }),
  },
}), { virtual: true })
```

---

## Conventions

### Emplacement des tests

Les tests vivent **adjacents** au code testé (convention idiomatique 2026), pas dans un dossier `__tests__/` séparé.

```
resources/js/
├── Composables/User/
│   ├── useFiscalYear.ts
│   └── useFiscalYear.spec.ts                ← test à côté
├── Components/Domain/Vehicle/
│   ├── VehicleCard.vue
│   └── VehicleCard.spec.ts
├── Utils/format/
│   ├── formatEuro.ts
│   └── formatEuro.spec.ts
└── Stores/User/
    ├── fiscalYearStore.ts
    └── fiscalYearStore.spec.ts
```

### Nommage

- Suffixe `.spec.ts` (pas `.test.ts`) — cohérent avec convention Vue.
- Le fichier teste un seul module (correspondance 1:1 avec le code testé).

### Structure d'un test

```ts
import { describe, it, expect, beforeEach, vi } from 'vitest'

describe('NomDuModule', () => {
  beforeEach(() => {
    // Setup commun
  })

  describe('méthodeSpécifique', () => {
    it('comportement attendu dans le cas X', () => {
      // arrange
      // act
      // assert
    })

    it('comportement attendu dans le cas Y', () => {
      // ...
    })
  })
})
```

---

## Quoi tester (matrice par type de code)

| Type | Tester ? | Niveau de couverture |
|---|---|---|
| **Util pur** (`format`, `validate`, `parse`) | **Oui** systématiquement | Couverture quasi 100 % (faciles, donnent confiance) |
| **Composable** | **Oui** (logique réactive) | Cas nominaux + edge cases + cleanup |
| **Store Pinia** | **Oui** (state, getters, actions) | State initial, actions principales, getters dérivés |
| **Composant UI Kit** (`Components/Ui/`) | **Oui** (rendu, accessibilité, interactions) | Variantes, props, emits, slots, a11y |
| **Composant Domain** (`Components/Domain/`) | **Oui** si logique non triviale | Cas nominaux, props, interactions |
| **Page Inertia** (`Pages/.../*.vue`) | **À évaluer** | Smoke test (rend sans erreur) + interactions critiques |
| **Partial de page** | **À évaluer** | Si logique propre : oui ; si juste rendu de props : pas critique |
| **Layout** | Smoke test | Rend correctement avec slot |
| **Types Spatie Data générés** | **Non** | Gérés par le générateur, pas de logique à tester |

### Quoi NE PAS tester

| Type | Pourquoi pas |
|---|---|
| Code généré (`generated.d.ts`) | Aucune logique, géré par Spatie |
| Implémentation interne réactive (Vue gère) | On teste les comportements, pas Vue |
| Pure transcription de design (composant UI sans logique) | Tests fragiles qui se cassent à chaque changement de classe Tailwind |
| Configuration (`vite.config.ts`, `tsconfig.json`) | Logique nulle, contrats stables |
| Wrappers triviaux (composant qui ne fait que `<slot />`) | Aucune valeur ajoutée |

---

## Patterns par type de code

### 1. Tester un util (le plus simple)

```ts
// resources/js/Utils/format/formatEuro.spec.ts
import { describe, it, expect } from 'vitest'
import { formatEuro } from './formatEuro'

describe('formatEuro', () => {
  it.each([
    [0, '0,00 €'],
    [100, '1,00 €'],
    [12345, '123,45 €'],
    [1_234_567_89, '1 234 567,89 €'],
    [-500, '-5,00 €'],
  ])('%i centimes → %s', (cents, expected) => {
    expect(formatEuro(cents)).toBe(expected)
  })
})
```

### 2. Tester un util avec validation complexe

```ts
// resources/js/Utils/validation/sirenChecksum.spec.ts
import { describe, it, expect } from 'vitest'
import { isValidSiren } from './sirenChecksum'

describe('isValidSiren', () => {
  describe('cas valides (algorithme de Luhn correct)', () => {
    it.each([
      '732829320',  // Carrefour
      '552120222',  // Renault
      '343262321',  // EDF
    ])('reconnaît %s comme valide', (siren) => {
      expect(isValidSiren(siren)).toBe(true)
    })
  })

  describe('cas invalides (format)', () => {
    it.each([
      '',
      '12345678',          // 8 chiffres
      '1234567890',        // 10 chiffres
      'ABC123456',         // contient des lettres
      '732 829 320',       // contient des espaces
    ])('rejette %s', (siren) => {
      expect(isValidSiren(siren)).toBe(false)
    })
  })

  describe('cas invalides (checksum)', () => {
    it.each([
      '732829321',  // bon format, checksum incorrect
      '000000000',  // tout zéros
    ])('rejette %s', (siren) => {
      expect(isValidSiren(siren)).toBe(false)
    })
  })
})
```

### 3. Tester un composable simple

```ts
// resources/js/Composables/User/useFiscalYear.spec.ts
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useFiscalYear } from './useFiscalYear'

// Mock Inertia usePage
vi.mock('@inertiajs/vue3', () => ({
  usePage: () => ({
    props: { fiscalYear: 2024 },
  }),
  router: { get: vi.fn() },
}))

describe('useFiscalYear', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('retourne l\'année des props Inertia', () => {
    const { year } = useFiscalYear()
    expect(year.value).toBe(2024)
  })

  it('isCurrentYear est true si année = courante', () => {
    vi.setSystemTime(new Date('2024-06-15'))
    const { isCurrentYear } = useFiscalYear()
    expect(isCurrentYear.value).toBe(true)
    vi.useRealTimers()
  })
})
```

### 4. Tester un composable avec lifecycle (timer)

```ts
// resources/js/Composables/User/useToast.spec.ts
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { useToast } from './useToast'

describe('useToast', () => {
  beforeEach(() => {
    // Reset état partagé
    const { toasts } = useToast()
    toasts.value = []
  })

  it('ajoute un toast à la pile', () => {
    const { toasts, pushToast } = useToast()
    pushToast({ variant: 'success', message: 'Test' })
    expect(toasts.value).toHaveLength(1)
    expect(toasts.value[0]?.message).toBe('Test')
    expect(toasts.value[0]?.variant).toBe('success')
  })

  it('dismisse automatiquement après le délai par défaut', () => {
    vi.useFakeTimers()
    const { toasts, pushToast } = useToast()
    pushToast({ variant: 'info', message: 'Auto-dismiss' })
    expect(toasts.value).toHaveLength(1)
    vi.advanceTimersByTime(4000)
    expect(toasts.value).toHaveLength(0)
    vi.useRealTimers()
  })

  it('respecte un délai personnalisé', () => {
    vi.useFakeTimers()
    const { toasts, pushToast } = useToast()
    pushToast({ variant: 'info', message: 'Custom', duration: 1000 })
    vi.advanceTimersByTime(500)
    expect(toasts.value).toHaveLength(1)
    vi.advanceTimersByTime(600)
    expect(toasts.value).toHaveLength(0)
    vi.useRealTimers()
  })

  it('ne dismisse pas si duration est 0', () => {
    vi.useFakeTimers()
    const { toasts, pushToast } = useToast()
    pushToast({ variant: 'error', message: 'Sticky', duration: 0 })
    vi.advanceTimersByTime(60_000)
    expect(toasts.value).toHaveLength(1)
    vi.useRealTimers()
  })
})
```

### 5. Tester un store Pinia

```ts
// resources/js/Stores/User/fiscalYearStore.spec.ts
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useFiscalYearStore } from './fiscalYearStore'

vi.mock('@inertiajs/vue3', () => ({
  router: { reload: vi.fn() },
}))

describe('useFiscalYearStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  describe('state initial', () => {
    it('initialise avec l\'année courante', () => {
      const store = useFiscalYearStore()
      expect(store.year).toBe(new Date().getFullYear())
      expect(store.isCurrent).toBe(true)
    })
  })

  describe('setYear', () => {
    it('modifie l\'année', () => {
      const store = useFiscalYearStore()
      store.setYear(2024)
      expect(store.year).toBe(2024)
    })

    it('refuse les années avant 2024', () => {
      const store = useFiscalYearStore()
      expect(() => store.setYear(2023)).toThrow()
    })

    it('refuse les années trop futures', () => {
      const store = useFiscalYearStore()
      expect(() => store.setYear(3000)).toThrow()
    })
  })

  describe('getters dérivés', () => {
    it('isPast est true si année < courante', () => {
      const store = useFiscalYearStore()
      store.setYear(2024)
      expect(store.isPast).toBe(true)
      expect(store.isCurrent).toBe(false)
      expect(store.isFuture).toBe(false)
    })

    it('yearLabel formate correctement', () => {
      const store = useFiscalYearStore()
      store.setYear(2024)
      expect(store.yearLabel).toBe('Exercice 2024')
    })
  })
})
```

### 6. Tester un composant UI Kit

```ts
// resources/js/Components/Ui/Button/Button.spec.ts
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import Button from './Button.vue'

describe('Button', () => {
  it('rend le slot', () => {
    const wrapper = mount(Button, {
      slots: { default: 'Cliquer ici' },
    })
    expect(wrapper.text()).toBe('Cliquer ici')
  })

  it('émet click au clic', async () => {
    const wrapper = mount(Button, {
      slots: { default: 'Cliquer' },
    })
    await wrapper.trigger('click')
    expect(wrapper.emitted('click')).toHaveLength(1)
  })

  it('n\'émet pas click si disabled', async () => {
    const wrapper = mount(Button, {
      props: { disabled: true },
      slots: { default: 'Cliquer' },
    })
    await wrapper.trigger('click')
    expect(wrapper.emitted('click')).toBeUndefined()
  })

  it('n\'émet pas click si loading', async () => {
    const wrapper = mount(Button, {
      props: { loading: true },
      slots: { default: 'Envoi…' },
    })
    await wrapper.trigger('click')
    expect(wrapper.emitted('click')).toBeUndefined()
  })

  it('a l\'attribut disabled HTML quand loading', () => {
    const wrapper = mount(Button, {
      props: { loading: true },
      slots: { default: 'Envoi…' },
    })
    expect(wrapper.attributes('disabled')).toBeDefined()
  })

  describe('variants', () => {
    it.each([
      ['primary', 'bg-primary-600'],
      ['danger', 'bg-error'],
      ['ghost', 'bg-transparent'],
    ])('variant=%s applique %s', (variant, expectedClass) => {
      const wrapper = mount(Button, {
        props: { variant: variant as 'primary' | 'danger' | 'ghost' },
        slots: { default: 'Test' },
      })
      expect(wrapper.classes()).toContain(expectedClass)
    })
  })
})
```

### 7. Tester un composant avec store Pinia

```ts
// resources/js/Components/Layouts/Partials/YearSelector.spec.ts
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { useFiscalYearStore } from '@/Stores/User/fiscalYearStore'
import YearSelector from './YearSelector.vue'

describe('YearSelector', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('affiche l\'année du store', () => {
    const wrapper = mount(YearSelector, {
      global: {
        plugins: [createTestingPinia({
          initialState: { fiscalYear: { year: 2024 } },
          stubActions: false,
        })],
      },
    })

    expect(wrapper.text()).toContain('2024')
  })

  it('appelle previousYear au clic sur ←', async () => {
    const wrapper = mount(YearSelector, {
      global: {
        plugins: [createTestingPinia({
          initialState: { fiscalYear: { year: 2024 } },
          stubActions: true, // stub les actions pour les espionner
        })],
      },
    })

    const store = useFiscalYearStore()
    await wrapper.find('[data-test="prev-year"]').trigger('click')
    expect(store.previousYear).toHaveBeenCalled()
  })
})
```

### 8. Tester un composant avec Testing Library (approche comportementale)

```ts
// resources/js/Components/Ui/Input/TextInput.spec.ts
import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/vue'
import { fireEvent } from '@testing-library/dom'
import TextInput from './TextInput.vue'

describe('TextInput (comportemental)', () => {
  it('l\'utilisateur peut saisir du texte', async () => {
    const { emitted } = render(TextInput, {
      props: { label: 'Immatriculation', modelValue: '' },
    })

    const input = screen.getByLabelText('Immatriculation')
    await fireEvent.update(input, 'AB-123-CD')

    expect(emitted()['update:modelValue']?.[0]).toEqual(['AB-123-CD'])
  })

  it('affiche un message d\'erreur quand invalid', () => {
    render(TextInput, {
      props: {
        label: 'Email',
        modelValue: 'invalide',
        invalid: true,
      },
      slots: {
        error: 'Email invalide',
      },
    })

    // Utilisateur voit l'erreur
    expect(screen.getByText('Email invalide')).toBeTruthy()
  })

  it('input a aria-invalid quand invalid', () => {
    render(TextInput, {
      props: { label: 'Email', modelValue: '', invalid: true },
    })

    const input = screen.getByLabelText('Email')
    expect(input.getAttribute('aria-invalid')).toBe('true')
  })
})
```

### 9. Tester un formulaire avec `useForm` Inertia

```ts
// resources/js/Pages/User/Vehicles/Create/Partials/VehicleForm.spec.ts
import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import VehicleForm from './VehicleForm.vue'

const mockPost = vi.fn()
const mockReset = vi.fn()

vi.mock('@inertiajs/vue3', async () => {
  const actual = await vi.importActual<typeof import('@inertiajs/vue3')>('@inertiajs/vue3')
  return {
    ...actual,
    useForm: vi.fn(() => ({
      data: () => ({ immatriculation: '', marque: '', modele: '' }),
      immatriculation: '',
      marque: '',
      modele: '',
      errors: {},
      processing: false,
      post: mockPost,
      reset: mockReset,
    })),
  }
})

describe('VehicleForm', () => {
  it('appelle post au submit', async () => {
    const wrapper = mount(VehicleForm)
    await wrapper.find('form').trigger('submit')
    expect(mockPost).toHaveBeenCalled()
  })

  it('désactive le bouton quand processing', async () => {
    // Re-mock useForm pour ce test
    const { useForm } = await import('@inertiajs/vue3')
    vi.mocked(useForm).mockReturnValueOnce({
      // ...
      processing: true,
    } as ReturnType<typeof useForm>)

    const wrapper = mount(VehicleForm)
    expect(wrapper.find('button[type="submit"]').attributes('disabled')).toBeDefined()
  })
})
```

---

## Fixtures typées

Les **fixtures** sont des données de test typées via les types Spatie Data générés. Elles évitent de redéclarer manuellement la structure dans chaque test.

```ts
// resources/js/test-fixtures/vehicle.ts
import type { VehicleData, VehicleListItemData } from '@/types'

export function makeVehicleListItem(overrides: Partial<VehicleListItemData> = {}): VehicleListItemData {
  return {
    id: 1,
    licensePlate: 'AB-123-CD',
    brand: 'Renault',
    model: 'Clio',
    vehicleUserType: 'VP',
    energySource: 'gasoline',
    photoUrl: null,
    isActive: true,
    ...overrides,
  }
}

export function makeVehicle(overrides: Partial<VehicleData> = {}): VehicleData {
  return {
    id: 1,
    licensePlate: 'AB-123-CD',
    brand: 'Renault',
    model: 'Clio',
    vehicleUserType: 'VP',
    bodyType: 'CI',
    seatsCount: 5,
    firstFrenchRegistrationDate: '2020-01-15',
    firstOriginRegistrationDate: '2020-01-15',
    firstEconomicUseDate: '2020-02-01',
    acquisitionDate: '2020-02-01',
    exitDate: null,
    exitReason: null,
    currentStatus: 'active',
    mileageCurrent: 45000,
    vin: null,
    color: null,
    photoUrl: null,
    notes: null,
    currentFiscalCharacteristics: makeVehicleFiscalCharacteristics(),
    fiscalCharacteristicsHistory: [],
    ...overrides,
  }
}

// ... etc.
```

### Usage

```ts
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import VehicleCard from './VehicleCard.vue'
import { makeVehicleListItem } from '@/test-fixtures/vehicle'

describe('VehicleCard', () => {
  it('affiche l\'immatriculation', () => {
    const vehicle = makeVehicleListItem({ immatriculation: 'XY-789-ZA' })
    const wrapper = mount(VehicleCard, { props: { vehicle } })
    expect(wrapper.text()).toContain('XY-789-ZA')
  })

  it('affiche le badge inactif si isActive=false', () => {
    const vehicle = makeVehicleListItem({ isActive: false })
    const wrapper = mount(VehicleCard, { props: { vehicle } })
    expect(wrapper.find('[data-test="status-badge"]').text()).toContain('Inactif')
  })
})
```

> **Avantage** : si `VehicleListItemData` change côté Spatie Data, le builder `makeVehicleListItem` lève une erreur TS au build → on met à jour la fixture, on ne se retrouve pas avec des tests qui passent sur des données obsolètes.

### Emplacement des fixtures

```
resources/js/
└── test-fixtures/                  ← partagés entre les tests
    ├── vehicle.ts
    ├── entreprise.ts
    ├── attribution.ts
    └── declaration.ts
```

---

## Mocks — règles d'usage

### Mocker uniquement les frontières externes

| Frontière | Mocker ? | Pourquoi |
|---|---|---|
| Appels Inertia (`router.get`, `useForm.post`) | **Oui** | Pas d'appel réseau en test unitaire |
| `usePage()` Inertia | **Oui** | Contrôler les props pour scénarios de test |
| Modules Wayfinder (`@/actions/...`) | **Oui** (global setup) | Fichiers générés — absents de l'environnement test tant que `php artisan wayfinder:generate` n'est pas lancé |
| Fonctions du DOM (rare) | **Au cas par cas** | `happy-dom` couvre la plupart |
| Composants enfants | **Rarement** (`shallowMount` si besoin) | Préférer mount complet pour tester l'intégration |
| Composables internes au projet | **Non** | Tester avec leur vraie implémentation |

### Pattern — mock global des actions Wayfinder

Déjà fait dans `test-setup.ts` (cf. plus haut). Tous les tests héritent. Chaque controller utilisé par le front doit être mocké une fois (liste complète maintenue dans `docs/vitest-configuration.md`).

### Pattern — mock ponctuel d'Inertia

```ts
import { vi } from 'vitest'

vi.mock('@inertiajs/vue3', async () => {
  const actual = await vi.importActual<typeof import('@inertiajs/vue3')>('@inertiajs/vue3')
  return {
    ...actual,
    router: {
      get: vi.fn(),
      post: vi.fn(),
      visit: vi.fn(),
    },
    usePage: () => ({
      props: { auth: { user: null }, flash: { success: null, error: null } },
    }),
  }
})
```

---

## Tests de tests — la pyramide

```
              ┌──────────────────┐
              │  E2E (Playwright)│   ← V2+, peu nombreux, parcours critiques
              │     ~5-10 tests   │
              └──────────────────┘
            ┌──────────────────────┐
            │   Composants intégrés │   ← Pages Inertia, formulaires complexes
            │      ~30-50 tests     │
            └──────────────────────┘
        ┌──────────────────────────────┐
        │  Unitaires (utils, composables, │   ← Le plus gros
        │  stores, composants UI Kit)     │      ~100-200 tests
        └──────────────────────────────┘
```

### Floty V1 — focus

- **Unitaires** : utils 100 %, composables 90 %, stores 90 %, UI Kit 80 %.
- **Intégrés** : composants Domain importants, formulaires critiques (création véhicule, déclaration).
- **E2E** : pas en V1. À évaluer V2.

---

## Anti-patterns (repérés en revue senior)

### Sur la conception du test

| Anti-pattern | Correction |
|---|---|
| Test qui reproduit le template (snapshot inutile) | Tester les comportements, pas la structure HTML |
| `expect(wrapper.html()).toMatchSnapshot()` partout | Snapshots fragiles, à utiliser parcimonieusement |
| Test qui couvre toutes les classes CSS | Tester la **présence** d'une classe sémantique (`disabled`, `error`) si pertinent, pas toutes |
| Test sans `expect` (pas d'assertion) | Au moins un `expect` par test |
| Test avec plusieurs comportements (« il fait ceci, cela, et puis aussi cela ») | Un test = un comportement |
| Test conditionnel (`if (X) expect(...)`) | Si conditionnel, écrire deux tests distincts |

### Sur l'usage de Vitest / Vue Test Utils

| Anti-pattern | Correction |
|---|---|
| `shallowMount` par défaut | `mount` complet pour les tests d'intégration ; `shallowMount` uniquement quand on isole vraiment un composant |
| Pas de `await` sur `wrapper.trigger()` ou `wrapper.setProps()` | Toujours `await` (Vue update est async) |
| Setup et teardown dans chaque `it` au lieu de `beforeEach` | Factoriser dans `beforeEach` |
| Tests qui dépendent de l'ordre d'exécution | Tests isolés et indépendants |
| `vi.useFakeTimers()` sans `vi.useRealTimers()` à la fin | Restaurer toujours les timers réels |
| `vi.clearAllMocks()` jamais appelé | Au moins dans `beforeEach` du describe principal |
| Tester des détails d'implémentation (refs internes, computed privés) | Tester l'API publique : props, emits, slots, render |

### Sur les mocks

| Anti-pattern | Correction |
|---|---|
| Mocker un composable Floty pour le tester via le composant | Tester le composable séparément, ne pas le mocker dans le composant |
| Mocker `Math.random` pour stabiliser un test | Refactor le code pour injecter le random en paramètre |
| Mock global qui contamine d'autres tests | Mocks locaux ou reset systématique |
| `vi.mock()` au milieu du test (au lieu du top du fichier) | `vi.mock()` doit être au top niveau (hoisting) |

### Sur les fixtures

| Anti-pattern | Correction |
|---|---|
| Fixture redéfinie dans chaque test | Fixture partagée dans `test-fixtures/` |
| Fixture non typée (object literal) | Typer via le DTO Spatie Data |
| Fixture avec valeurs aléatoires (`Math.random`) | Valeurs déterministes pour reproductibilité |
| Fixture qui dépend du temps courant (`new Date()`) | Date fixe ou injectée |

### Sur la couverture

| Anti-pattern | Correction |
|---|---|
| Viser 100 % de couverture systématiquement | Viser **valeur** des tests, pas % aveugle |
| Test pour atteindre la couverture sans assertion utile | Mieux pas de test qu'un test trompeur |
| Couverture comme seul indicateur qualité | Combiner couverture + revue + tests humains |

---

## Checklist — avant de considérer une feature comme testée

### Côté code testé

- [ ] Util : tests des cas nominaux + edge cases.
- [ ] Composable : tests des comportements + cleanup des effets.
- [ ] Store : tests state initial + actions + getters dérivés.
- [ ] Composant UI Kit : tests des variantes + emits + a11y essentielle.
- [ ] Composant Domain : tests des comportements + interactions.
- [ ] Formulaire : tests submit + validation + désactivation pendant processing.

### Côté tests eux-mêmes

- [ ] Fichier `.spec.ts` adjacent au fichier testé.
- [ ] Tests isolés (pas de dépendance d'ordre).
- [ ] `await` sur toutes les actions async.
- [ ] Mocks limités aux frontières externes (Inertia, route).
- [ ] Fixtures typées via DTO Spatie Data.
- [ ] Pas de test qui reproduit le template (HTML structure).
- [ ] Pas de snapshot abusif.
- [ ] Couverture rapportée mais pas optimisée à l'aveugle.

---

## Workflow CI/CD

### Tests en local

```bash
# Mode watch (dev)
npm run test

# Single run
npm run test:ci

# Avec couverture
npm run test:coverage
```

### `package.json` (extrait)

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

### Pipeline CI (GitHub Actions ou autre)

```yaml
# .github/workflows/test.yml (esquisse)
- name: Install
  run: npm ci

- name: Lint
  run: npm run lint

- name: Type check
  run: npm run typecheck

- name: Tests frontend
  run: npm run test:ci

- name: Build assets
  run: npm run build
```

---

## Cohérence avec les autres règles

- **Composants Vue** (props, emits, slots à tester) : voir `vue-composants.md`.
- **Composables, services, utils** (patterns testables) : voir `composables-services-utils.md`.
- **Stores Pinia** (`createTestingPinia`, `stubActions`) : voir `pinia-stores.md`.
- **TypeScript et DTO** (fixtures typées) : voir `typescript-dto.md`.
- **Navigation Inertia** (mock `router`, `useForm`) : voir `inertia-navigation.md`.
- **Gestion des erreurs** (tester les flash, validation 422) : voir `gestion-erreurs.md`.
- **Performance UI** (tests de perf en dehors de cette stack — profiler manuel) : voir `performance-ui.md`.

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 1.0 | 24/04/2026 | Micha MEGRET | Rédaction initiale — stack Vitest 4 + Vue Test Utils 2.4 + `@pinia/testing` + Testing Library, conventions (`.spec.ts` adjacents, structure describe/it), matrice quoi tester, patterns par type de code (util, composable simple, composable lifecycle, store Pinia, composant UI Kit, composant avec store, Testing Library comportemental, formulaire `useForm`), fixtures typées via DTO Spatie Data, mocks (frontières externes uniquement), pyramide des tests Floty V1, anti-patterns repérés en revue senior, workflow CI/CD, checklist. |
