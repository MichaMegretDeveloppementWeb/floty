# Composables et utils — règles d'implémentation

> **Stack référence** : Vue 3.5, TypeScript 6, Inertia v3.
> **Niveau d'exigence** : senior +. Distinction stricte entre logique réactive (composable) et fonction pure (util). Pas de couche « service frontend » distincte — l'écosystème Vue 3 + Inertia n'en a pas besoin.
> **Documents liés** : `vue-composants.md`, `typescript-dto.md`, `pinia-stores.md`, `inertia-navigation.md`, `architecture-solid.md`.

---

## Pourquoi cette règle existe

Vue 3 + TypeScript offrent **deux mécanismes de réutilisation** côté frontend, souvent confondus par des développeurs juniors ou intermédiaires :

| Mécanisme | Réactivité | État | Cycle de vie Vue |
|---|---|---|---|
| **Composable** (`useXxx`) | Oui | Oui (refs, computed) | Oui (lifecycle hooks possibles) |
| **Utility** (fonction pure) | Non | Non | Non |

Confondre les deux conduit à :

- Des composables qui devraient être de simples fonctions pures (pollution réactivité inutile).
- Des utils qui contiennent de l'état partagé caché (bombe à retardement).

> **Note sur l'absence de couche « service frontend »** : sur un projet Vue 3 + Inertia, la couche « service » classique (présente en architecture Java/.NET ou en SPA pure type Angular) est **structurellement remplacée** par les composables (état réactif partagé via ref hors fonction), Pinia (état applicatif identifié), et Inertia (toutes les opérations réseau). Pour Floty V1, **aucun cas d'usage ne justifie** une telle couche. Si un besoin futur émergeait (ex: singleton avec lifecycle complexe pour intégrer une lib tierce stateful), ce serait à documenter en exception, pas comme convention par défaut.

Cette règle pose les **frontières claires** et donne les **patterns canoniques** pour chaque cas.

---

## 1. Composable — `useXxx`

### Définition

Un composable est une **fonction** qui :

1. Préfixe son nom par `use` (convention Vue stricte).
2. Encapsule de la **logique réactive** (utilise `ref`, `computed`, `watch`, `provide/inject`, lifecycle hooks).
3. Est appelée **dans le `setup` d'un composant** (ou dans un autre composable).
4. Retourne typiquement un objet de refs et de fonctions.

### Quand l'utiliser

- Logique réactive **réutilisable** dans plusieurs composants.
- Encapsulation d'un comportement complexe lié à Vue (gestion d'état UI, effets de bord, intégration browser API).
- Composition de plusieurs concepts (réactivité + lifecycle + watchers).

### Pattern de référence

```ts
// resources/js/Composables/User/useFiscalYear.ts
import { computed, type ComputedRef } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

export type UseFiscalYearReturn = {
  year: ComputedRef<number>
  setYear: (newYear: number) => void
  isCurrentYear: ComputedRef<boolean>
}

export function useFiscalYear(): UseFiscalYearReturn {
  const page = usePage()

  const year = computed<number>(() => {
    const fromProps = page.props.fiscalYear as number | undefined
    return fromProps ?? new Date().getFullYear()
  })

  const isCurrentYear = computed(() => year.value === new Date().getFullYear())

  const setYear = (newYear: number): void => {
    router.get(route('user.dashboard'), { fiscalYear: newYear }, {
      preserveScroll: true,
      preserveState: true,
      only: ['fiscalYear', 'vehicles', 'declarations'],
    })
  }

  return { year, setYear, isCurrentYear }
}
```

### Usage

```vue
<script setup lang="ts">
import { useFiscalYear } from '@/Composables/User/useFiscalYear'

const { year, isCurrentYear, setYear } = useFiscalYear()
</script>

<template>
  <div>
    <span>Année fiscale : {{ year }}</span>
    <Badge v-if="isCurrentYear" variant="info">Année en cours</Badge>
    <button @click="setYear(year - 1)">Année précédente</button>
  </div>
</template>
```

### Composable avec lifecycle

```ts
// resources/js/Composables/Shared/useKeyboardShortcuts.ts
import { onMounted, onBeforeUnmount } from 'vue'

export type ShortcutMap = Record<string, (event: KeyboardEvent) => void>

export function useKeyboardShortcuts(shortcuts: ShortcutMap): void {
  const handler = (event: KeyboardEvent): void => {
    const key = [
      event.metaKey && 'meta',
      event.ctrlKey && 'ctrl',
      event.shiftKey && 'shift',
      event.altKey && 'alt',
      event.key.toLowerCase(),
    ]
      .filter(Boolean)
      .join('+')

    const handler = shortcuts[key]
    if (handler) {
      event.preventDefault()
      handler(event)
    }
  }

  onMounted(() => {
    window.addEventListener('keydown', handler)
  })

  onBeforeUnmount(() => {
    window.removeEventListener('keydown', handler)
  })
}
```

```vue
<script setup lang="ts">
import { useKeyboardShortcuts } from '@/Composables/Shared/useKeyboardShortcuts'

useKeyboardShortcuts({
  'ctrl+s': () => save(),
  'ctrl+z': () => undo(),
  'escape': () => closeModal(),
})
</script>
```

### Composable avec état local persistant

```ts
// resources/js/Composables/User/useToast.ts
import { ref, type Ref } from 'vue'
import type { ToastPayload } from '@/types/ui'

const toasts: Ref<Array<ToastPayload & { id: number }>> = ref([])
let nextId = 0

export type UseToastReturn = {
  toasts: Ref<Array<ToastPayload & { id: number }>>
  pushToast: (payload: ToastPayload) => void
  dismissToast: (id: number) => void
}

export function useToast(): UseToastReturn {
  const pushToast = (payload: ToastPayload): void => {
    const id = nextId++
    toasts.value.push({ ...payload, id })

    if (payload.duration !== 0) {
      setTimeout(() => dismissToast(id), payload.duration ?? 4000)
    }
  }

  const dismissToast = (id: number): void => {
    toasts.value = toasts.value.filter((t) => t.id !== id)
  }

  return { toasts, pushToast, dismissToast }
}
```

> **Note** : `toasts` est défini **hors** de la fonction `useToast()` → état partagé entre toutes les instances. Utile ici (un seul container de toasts pour toute l'app). Pour un état isolé par instance, déclarer le `ref` **dans** la fonction.

### Règles strictes

| Règle | Pourquoi |
|---|---|
| **Préfixe `use`** obligatoire | Convention Vue, IDE/lint reconnaissent les composables |
| **Une responsabilité** par composable | Pas de `useEverything` |
| **Type de retour explicite** (`UseXxxReturn`) | Réviewabilité, IDE inference, contrat clair |
| **Pas d'effet de bord à l'import** | Le composable ne fait rien tant qu'il n'est pas appelé dans un setup |
| **Cleanup obligatoire** des effets (timers, listeners) via `onBeforeUnmount` | Sinon fuite mémoire |
| **Pas d'appel HTTP direct** sauf via Inertia ou via service abstrait | Sinon couplage et difficultés de test |
| **Documentation JSDoc** pour les composables non triviaux | Facilite la prise en main |

### Composable vs `<script setup>` direct

**Quand extraire en composable ?**

- Si la logique est utilisée dans **≥ 2 composants** : extraire.
- Si la logique fait > 30 lignes dans `<script setup>` ET pourrait être logiquement isolée : extraire pour la lisibilité, même si utilisée à un seul endroit.
- Sinon : laisser dans `<script setup>` (pas de sur-engineering).

---

## 2. Util — fonction pure

### Définition

Un util est une **fonction pure** :

1. **Pas d'état** (pas de variable globale, pas de closure mutante).
2. **Sortie déterministe** : mêmes entrées → même sortie.
3. **Pas d'effet de bord** : ne modifie rien hors de son scope.
4. **Pas d'import Vue ni Inertia**.

### Quand l'utiliser

- Formatage (date, monnaie, immatriculation, SIREN).
- Validation de format (regex, checksums).
- Calculs simples sans état.
- Transformations de données (mapping, agrégation simple).

### Pattern de référence

```ts
// resources/js/Utils/format/formatEuro.ts
export function formatEuro(amountInCents: number): string {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 2,
  }).format(amountInCents / 100)
}
```

```ts
// resources/js/Utils/format/formatDate.ts
import { format, parseISO } from 'date-fns'
import { fr } from 'date-fns/locale'

export function formatDate(isoString: string, pattern: string = 'dd/MM/yyyy'): string {
  return format(parseISO(isoString), pattern, { locale: fr })
}

export function formatDateLong(isoString: string): string {
  return format(parseISO(isoString), 'EEEE d MMMM yyyy', { locale: fr })
}
```

```ts
// resources/js/Utils/validation/frenchPlate.ts
const FRENCH_PLATE_REGEX = /^[A-Z]{2}-?\d{3}-?[A-Z]{2}$/

export function isValidFrenchPlate(plate: string): boolean {
  return FRENCH_PLATE_REGEX.test(plate.toUpperCase().trim())
}

export function normalizeFrenchPlate(plate: string): string {
  return plate.toUpperCase().trim().replace(/-/g, '')
}
```

```ts
// resources/js/Utils/validation/sirenChecksum.ts
/**
 * Vérifie le checksum d'un numéro SIREN (algorithme de Luhn).
 * @param siren - Numéro SIREN (9 chiffres, sans espaces).
 */
export function isValidSiren(siren: string): boolean {
  if (!/^\d{9}$/.test(siren)) return false

  let sum = 0
  for (let i = 0; i < 9; i++) {
    let digit = Number.parseInt(siren[i]!, 10)
    if (i % 2 === 1) {
      digit *= 2
      if (digit > 9) digit -= 9
    }
    sum += digit
  }

  return sum % 10 === 0
}
```

### Règles strictes

| Règle | Pourquoi |
|---|---|
| **Pure** : pas d'effet de bord | Garantie de testabilité |
| **Stateless** : pas de variable mutable au niveau module | Si état partagé requis, utiliser un composable avec ref hors fonction |
| **Type de retour explicite** | Réviewabilité |
| **Pas d'import Vue ni Inertia** | Util réutilisable côté tests, en isolation |
| **Documentation JSDoc** quand le comportement n'est pas évident | Ex: algorithme de Luhn |
| **Tests systématiques** | Faciles à écrire, donnent de la confiance |

### Anti-patterns d'util

| Anti-pattern | Correction |
|---|---|
| Util qui mute son input | `array.sort()` non, `[...array].sort()` oui |
| Util avec `let` au niveau module | Promouvoir en composable (ref hors fonction) |
| Util qui fait un appel HTTP | Composable Inertia |
| Util qui appelle `console.log` | Side effect |
| Util qui dépend de la date du jour sans la prendre en paramètre | Passer `now: Date` en paramètre pour être testable |

---

## 3. Frontière entre composable et util

### Tableau de décision

| Question | Réponse → Concept |
|---|---|
| « J'ai besoin de réactivité Vue (ref, computed, watch) » | **Composable** |
| « J'ai besoin de hooks de cycle de vie Vue » | **Composable** |
| « J'ai besoin d'un état partagé entre plusieurs composants, sans Pinia » | **Composable** (avec ref hors fonction) ou Pinia si vraiment cross-page applicatif |
| « J'ai besoin d'encapsuler une intégration de lib tierce avec état » | **Composable** avec ref hors fonction, ou exception documentée si vraiment besoin d'un singleton TS pur |
| « J'ai besoin d'une fonction pure de transformation/formatage/validation » | **Util** |
| « J'ai besoin d'un calcul fiscal » | **Backend** (ADR-0001) — pas côté front |

### Exemples concrets Floty

| Besoin | Concept | Emplacement |
|---|---|---|
| Récupérer/changer l'année fiscale courante (lié à Inertia) | Composable | `Composables/User/useFiscalYear.ts` |
| Pile globale de toasts | Composable (état hors fonction) | `Composables/User/useToast.ts` |
| Raccourcis clavier globaux | Composable (lifecycle) | `Composables/Shared/useKeyboardShortcuts.ts` |
| Debounce d'une fonction | Composable | `Composables/Shared/useDebouncedRef.ts` (ou utiliser `@vueuse/core`) |
| Formater un montant en euros | Util | `Utils/format/formatEuro.ts` |
| Formater une date | Util | `Utils/format/formatDate.ts` |
| Valider une immatriculation | Util | `Utils/validation/frenchPlate.ts` |
| Valider un SIREN | Util | `Utils/validation/sirenChecksum.ts` |
| **Calculer le cumul LCD** | **Backend** | `App\Services\User\Attribution\LcdCumulCalculationService` |

---

## 4. Composition — un composable peut utiliser un util

Un composable peut **composer** des utils. C'est le pattern naturel.

```ts
// resources/js/Composables/User/useVehicleSearch.ts
import { ref, computed } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { router } from '@inertiajs/vue3'
import { normalizeFrenchPlate } from '@/Utils/validation/frenchPlate'

export function useVehicleSearch() {
  const query = ref('')

  const normalizedQuery = computed(() => normalizeFrenchPlate(query.value))

  const search = useDebounceFn((q: string): void => {
    router.get(route('user.vehicles.index'), { search: q }, {
      preserveState: true,
      preserveScroll: true,
      only: ['vehicles'],
      replace: true,
    })
  }, 300)

  return { query, normalizedQuery, search }
}
```

Ce composable utilise :

- Un util (`normalizeFrenchPlate`).
- Une lib externe (`useDebounceFn` de VueUse).
- L'API Inertia.

C'est correct et lisible.

---

## 5. Organisation et arborescence

### Composables

```
resources/js/Composables/
├── User/                                       ← composables pour l'espace connecté
│   ├── useFiscalYear.ts
│   ├── useLcdCumul.ts
│   ├── useToast.ts
│   ├── useVehicleSearch.ts
│   └── useWeeklyEntrySelection.ts
├── Web/                                        ← composables pour la partie publique
│   └── useContactForm.ts
└── Shared/                                     ← composables transverses
    ├── useDebouncedRef.ts
    └── useKeyboardShortcuts.ts
```

### Utils

```
resources/js/Utils/
├── format/
│   ├── formatEuro.ts
│   ├── formatDate.ts
│   ├── formatImmatriculation.ts
│   └── formatSiren.ts
├── validation/
│   ├── frenchPlate.ts
│   └── sirenChecksum.ts
└── fiscal/
    └── computeProrata.ts            ← uniquement pour affichage estimatif (jamais calcul officiel)
```

### Conventions de nommage

| Type | Format fichier | Format export |
|---|---|---|
| Composable | `useXxx.ts` (camelCase préfixe `use`) | `export function useXxx()` |
| Util | `formatXxx.ts`, `isValidXxx.ts` (camelCase, verbe en tête) | `export function formatXxx()` |

---

## 6. Tests — chaque mécanisme se teste différemment

### Composable

Tests via `@vue/test-utils` ou via mounting d'un composant qui consomme le composable.

```ts
// useToast.spec.ts
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useToast } from './useToast'

describe('useToast', () => {
  beforeEach(() => {
    // Reset l'état partagé entre tests
    const { toasts } = useToast()
    toasts.value = []
  })

  it('ajoute un toast à la pile', () => {
    const { toasts, pushToast } = useToast()
    pushToast({ variant: 'success', message: 'Test' })
    expect(toasts.value).toHaveLength(1)
    expect(toasts.value[0]?.message).toBe('Test')
  })

  it('dismisse un toast après le délai', async () => {
    vi.useFakeTimers()
    const { toasts, pushToast } = useToast()
    pushToast({ variant: 'info', message: 'Auto', duration: 100 })
    expect(toasts.value).toHaveLength(1)
    vi.advanceTimersByTime(150)
    expect(toasts.value).toHaveLength(0)
    vi.useRealTimers()
  })
})
```

### Util

Tests les plus simples — fonction pure, input/output.

```ts
// frenchPlate.spec.ts
import { describe, it, expect } from 'vitest'
import { isValidFrenchPlate, normalizeFrenchPlate } from './frenchPlate'

describe('isValidFrenchPlate', () => {
  it.each([
    ['AB-123-CD', true],
    ['AB123CD', true],
    ['ab-123-cd', true],
    ['AB-12-CD', false],
    ['1234567', false],
    ['', false],
  ])('valide %s → %s', (input, expected) => {
    expect(isValidFrenchPlate(input)).toBe(expected)
  })
})

describe('normalizeFrenchPlate', () => {
  it('uppercase et retire les tirets', () => {
    expect(normalizeFrenchPlate('ab-123-cd')).toBe('AB123CD')
  })
})
```

> Détails complets sur les tests : voir `tests-frontend.md`.

---

## 7. Anti-patterns récapitulatif

### Confusions de concepts

| Anti-pattern | Correction |
|---|---|
| Util avec état mutable au niveau module | Promouvoir en composable (ref hors fonction) |
| Composable qui ne contient aucune réactivité | C'est probablement un util |
| Composable nommé sans préfixe `use` (`fiscalYear()`) | Toujours `useFiscalYear` |
| Composable appelé hors `setup` (dans un module pur) | Composable doit être appelé dans `setup` ou autre composable |
| Création d'un dossier `resources/js/Services/` | Pas de couche service frontend en Floty — confiner dans Composables ou Utils |

### Sur les composables

| Anti-pattern | Correction |
|---|---|
| `useEverything` qui fait 200 lignes | Diviser en plusieurs composables ciblés |
| Effet de bord à l'import du composable | Composable doit être lazy : la fonction est appelée pour activer l'effet |
| Listener `window` sans cleanup | `onBeforeUnmount` obligatoire |
| `setInterval` sans cleanup | Idem |
| Type de retour `any` ou inferred sans contrôle | Type explicite `UseXxxReturn` |
| État global au niveau module sans réflexion (singleton de fait) | Documenter explicitement le partage si voulu |

### Sur les utils

| Anti-pattern | Correction |
|---|---|
| Util qui mute son input | Retourner une nouvelle valeur immutable |
| Util avec `let foo = ...` au niveau module | Promouvoir en composable |
| Util qui appelle `console.log` ou `localStorage` | Side effect → composable |
| Util qui dépend de `new Date()` sans paramètre | Passer la date en paramètre pour être testable |
| Util qui fait un `fetch` ou `axios` | Composable Inertia |

---

## 8. Checklist — avant de considérer un composable/util comme « terminé »

### Composable

- [ ] Préfixe `use` dans le nom de fichier et de fonction.
- [ ] `<script setup>` consume-able (appelé dans setup).
- [ ] Type de retour explicite (`UseXxxReturn`).
- [ ] Pas d'effet de bord à l'import.
- [ ] Cleanup de tous les effets (timers, listeners, abonnements).
- [ ] Documentation JSDoc si non trivial.
- [ ] Test `.spec.ts` adjacent.
- [ ] Pas de logique métier (calcul fiscal, validation business) — délégué au backend.

### Util

- [ ] Fonction pure (pas d'état, pas d'effet de bord).
- [ ] Type de retour explicite.
- [ ] Pas d'import Vue ni Inertia.
- [ ] Test `.spec.ts` adjacent (cas nominaux + edge cases).
- [ ] JSDoc si comportement non évident.
- [ ] Nom commence par un verbe (`format`, `parse`, `is`, `has`, `compute`).

---

## Cohérence avec les autres règles

- **Architecture en couches** (où vivent ces 2 mécanismes côté front) : voir `architecture-solid.md`.
- **Composants Vue** (consommation des composables, props/emits) : voir `vue-composants.md`.
- **TypeScript et DTO** (types de retour, ré-exports, generics) : voir `typescript-dto.md`.
- **Stores Pinia** (différence avec composable à état partagé) : voir `pinia-stores.md`.
- **Navigation Inertia** (composables qui utilisent `router`, `usePage`) : voir `inertia-navigation.md`.
- **Tests frontend** (`.spec.ts` adjacents, fixtures typées) : voir `tests-frontend.md`.
- **Performance UI** (composables d'optimisation comme `useDebouncedRef`) : voir `performance-ui.md`.

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 1.1 | 24/04/2026 | Micha MEGRET | Passe d'audit (étape 5.6) — A2 corrigé : suppression de la couche « Service frontend » qui n'avait aucun cas d'usage justifié en Floty V1 et orpheline par rapport aux autres docs (`architecture-solid`, `structure-fichiers`, `conventions-nommage`). Renumérotation des sections (de 9 à 8). Anti-pattern explicite « pas de dossier `resources/js/Services/` ». Renommage du titre « Composables et utils ». |
| 1.0 | 24/04/2026 | Micha MEGRET | Rédaction initiale — distinction stricte Composable / Service / Util, patterns canoniques pour chacun, tableau de décision, exemples Floty (useFiscalYear, useToast, useKeyboardShortcuts, clientCacheStore, AnalyticsClient, formatEuro, isValidFrenchPlate, isValidSiren), composition (composable utilisant util/service), organisation et conventions de nommage, tests par concept, anti-patterns repérés en revue senior, checklist par concept. |
