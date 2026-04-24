# Stores Pinia — règles d'usage et de réserve

> **Stack référence** : Pinia 3.0, Vue 3.5, TypeScript 6, Inertia v3.
> **Niveau d'exigence** : senior +. Pinia est un outil **de réserve** : on ne l'utilise que quand les autres mécanismes (props Inertia, composables, `useRemember`, query params) ne suffisent pas.
> **Documents liés** : `composables-services-utils.md`, `inertia-navigation.md`, `vue-composants.md`, `typescript-dto.md`.

---

## Pourquoi cette règle existe

Pinia est l'outil officiel de gestion d'état global Vue 3. Il est puissant, type-safe, performant. Mais c'est aussi **l'outil le plus mal utilisé** dans les projets Vue : les développeurs intermédiaires ont tendance à y mettre toutes les données de l'application « pour plus tard », ce qui produit des stores fourre-tout, du couplage, des bugs de synchronisation avec l'état serveur, et des problèmes de performance.

Sur Floty (SPA Inertia), **la majorité des données vit dans les props Inertia** qui sont déjà partagées et synchronisées avec le serveur. Pinia est nécessaire **dans des cas précis et limités**.

> **Règle directrice Floty** : si tu te demandes « est-ce que je devrais utiliser Pinia pour ça ? », la réponse par défaut est **non**. Tu dois pouvoir justifier explicitement pourquoi les autres mécanismes ne suffisent pas.

---

## La hiérarchie des mécanismes d'état

Pour chaque besoin d'état partagé, la décision suit cette hiérarchie **du plus simple au plus complexe** :

| Niveau | Mécanisme | Quand l'utiliser |
|---|---|---|
| 1 | **State local** (`ref`, `reactive` dans un composant) | État qui ne sort pas du composant |
| 2 | **Props + emits** | État qu'un parent doit partager à un enfant |
| 3 | **`provide` / `inject`** | État partagé entre composants d'un sous-arbre (rare en Floty) |
| 4 | **Props Inertia** (shared props ou page props) | État qui vient du serveur |
| 5 | **`useRemember`** | État UI à persister à travers les navigations (back/forward) |
| 6 | **Query params URL** | État qui mérite d'être partageable par URL (filtres, pagination) |
| 7 | **Composable avec ref hors fonction** | État partagé entre plusieurs composants, pas critique au refresh |
| 8 | **Pinia store** | État cross-page persistant qui ne rentre dans aucune des cases au-dessus |

**On descend la hiérarchie uniquement quand le niveau supérieur ne convient pas.**

---

## Cas d'usage Pinia justifiés en Floty

### 1. État UI cross-pages persistant à l'intérieur d'une session

L'utilisateur change de page mais l'état doit rester (pas effacé par recreation de composant, pas dépendant de l'URL).

**Exemples Floty** :

- **Année fiscale active** : sélectionnée par l'utilisateur, doit persister entre les pages. **Cas typique Pinia.**
- **Pile de toasts** : container global, alimenté depuis n'importe quelle page. **Cas Pinia ou composable à état partagé** (Floty fait composable, cf. `composables-services-utils.md`).
- **Préférences UI utilisateur** (mode compact, thème si V2) : transverses et persistantes.

### 2. État dérivé d'agrégations multi-pages

Si une donnée doit être consultée sur plusieurs pages mais qu'on ne veut pas la re-fetch à chaque navigation.

**Exemples Floty** :

- **Cache court de référentiels** : liste des entreprises utilisatrices (cf. `Components/Domain/Company/CompanySelector.vue`) qui apparaît dans le sidebar, dans le wizard, dans les filtres. Pour éviter de re-fetch à chaque page, on peut la stocker dans Pinia une fois et l'invalider sur mutation.

> **Attention** : ce cache front est une **optimisation** — la source de vérité reste le backend. Si l'invalidation est mal gérée, on affiche des données obsolètes. Mieux vaut **ne pas faire** ce cache que le faire mal.

### 3. État technique transverse

- Statut de connexion réseau (online / offline).
- Drawer global (panneau latéral monté à la racine, ouvrable depuis n'importe quelle page).
- File d'événements analytics avant flush (cas service, voir `composables-services-utils.md`).

---

## Cas d'usage Pinia non-justifiés en Floty

| Cas | Pourquoi pas Pinia | Alternative |
|---|---|---|
| Liste des véhicules de la page Index | Vient via props Inertia | Props |
| Erreurs de validation d'un formulaire | Gérées par `useForm` | `useForm` |
| Utilisateur connecté | Vient via shared props Inertia | `usePage().props.auth.user` |
| Filtres d'une page (recherche, tri) | Doit être partageable par URL | Query params + `useRemember` |
| État d'un drawer local à une page | Local au composant | `ref` |
| Données d'un wizard en cours | Local au composant wizard | `ref` ou `reactive` dans le composant |
| Position du scroll | Gérée par Inertia (`preserveScroll`) | Inertia options |
| Année fiscale **dans l'URL** | Query param + composable | Composable + `usePage()` |

---

## Pattern de référence — store Pinia Floty

### Style : Setup Stores (Composition API)

Pinia 3 supporte deux styles de définition : **Options Stores** (`state`/`getters`/`actions` à la Vuex) et **Setup Stores** (fonction style composition API). **Floty utilise exclusivement les Setup Stores** : cohérence avec la Composition API utilisée partout, type inference plus propre, plus naturel pour TypeScript.

```ts
// resources/js/Stores/User/fiscalYearStore.ts
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'

const CURRENT_YEAR = new Date().getFullYear()

export const useFiscalYearStore = defineStore('fiscalYear', () => {
  // === STATE ===
  const year = ref<number>(CURRENT_YEAR)

  // === GETTERS (computed) ===
  const isCurrent = computed(() => year.value === CURRENT_YEAR)
  const isPast = computed(() => year.value < CURRENT_YEAR)
  const isFuture = computed(() => year.value > CURRENT_YEAR)

  const yearLabel = computed(() => `Exercice ${year.value}`)

  // === ACTIONS ===
  function setYear(newYear: number): void {
    if (newYear < 2024 || newYear > CURRENT_YEAR + 1) {
      throw new Error(`Année invalide : ${newYear}`)
    }
    year.value = newYear
  }

  function setYearAndReload(newYear: number): void {
    setYear(newYear)
    router.reload({
      data: { fiscalYear: newYear },
      preserveScroll: true,
      preserveState: true,
    })
  }

  function previousYear(): void {
    setYearAndReload(year.value - 1)
  }

  function nextYear(): void {
    if (year.value < CURRENT_YEAR + 1) {
      setYearAndReload(year.value + 1)
    }
  }

  function resetToCurrent(): void {
    if (year.value !== CURRENT_YEAR) {
      setYearAndReload(CURRENT_YEAR)
    }
  }

  return {
    // State (exposé en lecture/écriture par défaut)
    year,
    // Getters
    isCurrent,
    isPast,
    isFuture,
    yearLabel,
    // Actions
    setYear,
    setYearAndReload,
    previousYear,
    nextYear,
    resetToCurrent,
  }
})
```

### Usage dans un composant

```vue
<script setup lang="ts">
import { useFiscalYearStore } from '@/Stores/User/fiscalYearStore'
import { storeToRefs } from 'pinia'

const fiscalYearStore = useFiscalYearStore()
const { year, yearLabel, isCurrent } = storeToRefs(fiscalYearStore)

// Les actions ne sont PAS destructurées via storeToRefs (sinon perte du `this`)
// On les garde liées au store :
const { previousYear, nextYear } = fiscalYearStore
</script>

<template>
  <div class="flex items-center gap-2">
    <button @click="previousYear">←</button>
    <span class="font-medium">{{ yearLabel }}</span>
    <button @click="nextYear" :disabled="!isCurrent">→</button>
  </div>
</template>
```

### Règle critique — `storeToRefs` pour la réactivité

Quand on destructure un store Pinia, **les valeurs deviennent statiques** (perte de réactivité). `storeToRefs` convertit l'état et les getters en `Ref` réactives.

```ts
// ❌ MAUVAIS — perte de réactivité
const { year, isCurrent } = useFiscalYearStore()

// ✅ BON — conserve la réactivité
const fiscalYearStore = useFiscalYearStore()
const { year, isCurrent } = storeToRefs(fiscalYearStore)
const { setYear } = fiscalYearStore // actions OK sans storeToRefs
```

---

## Conventions de nommage et d'organisation

### Conventions

| Élément | Format | Exemple |
|---|---|---|
| Fichier store | `xxxStore.ts` (camelCase suffixé `Store`) | `fiscalYearStore.ts` |
| Fonction exportée | `useXxxStore()` (camelCase, préfixe `use`, suffixe `Store`) | `useFiscalYearStore` |
| Identifiant store (1er arg `defineStore`) | camelCase | `'fiscalYear'` |

### Arborescence

```
resources/js/Stores/
├── User/                                ← stores pour l'espace connecté
│   ├── fiscalYearStore.ts
│   └── currentUserStore.ts             ← cache des infos user (alimenté par Inertia)
├── Web/                                 ← stores publics (rare)
│   └── (vide en V1 a priori)
└── Shared/                              ← stores transverses (très rare)
    └── networkStatusStore.ts           ← exemple : statut online/offline
```

### Règle stricte — un store par responsabilité

Un store = **une préoccupation cohérente**. Pas de `useAppStore` qui fait tout.

| Mauvais | Bon |
|---|---|
| `useAppStore` (year, user, toasts, modals, drawer) | 4-5 stores spécialisés |
| `useDataStore` (vehicles, entreprises, declarations) | Pas de store du tout, props Inertia suffisent |

---

## Persistance

### Sans persistance (défaut)

Par défaut, l'état Pinia est en mémoire JS. Au refresh de la page, il est perdu.

C'est **acceptable et même souhaitable** dans la plupart des cas — l'année fiscale est rechargée depuis les props Inertia (le backend la restaure depuis la session ou les query params).

### Avec persistance — `pinia-plugin-persistedstate`

Pour persister l'état dans `localStorage` ou `sessionStorage` (rare en Floty), utiliser le plugin officiel.

```ts
// resources/js/app.ts
import { createPinia } from 'pinia'
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate'

const pinia = createPinia()
pinia.use(piniaPluginPersistedstate)
```

```ts
export const useFiscalYearStore = defineStore('fiscalYear', () => {
  // ...
  return { year, setYear }
}, {
  persist: {
    storage: sessionStorage,
    paths: ['year'], // persister uniquement le champ year
  },
})
```

### Quand utiliser la persistance

| Cas | Persister ? |
|---|---|
| Préférences UI utilisateur (mode compact) | Oui (`localStorage`) |
| Année fiscale | Non (vient des props Inertia / session backend) |
| Pile de toasts | Non (transitoire) |
| User connecté | Non (vient des shared props) |
| Cache de référentiels | À évaluer — souvent non, on préfère re-fetch |

---

## Reset de stores

### Reset manuel

```ts
const store = useFiscalYearStore()
store.$reset() // remet l'état initial
```

### Reset à la déconnexion

Quand l'utilisateur se déconnecte, il faut reset tous les stores (sécurité, confidentialité).

```ts
// resources/js/app.ts (extrait)
import { router } from '@inertiajs/vue3'
import { useFiscalYearStore, useCurrentUserStore } from '@/Stores/User'

router.on('navigate', (event) => {
  // Si on est sur une page Web/Auth après déconnexion → reset
  if (event.detail.page.url === '/login' || event.detail.page.url === '/') {
    useFiscalYearStore().$reset()
    useCurrentUserStore().$reset()
  }
})
```

> Plus simple : utiliser un `pinia.use(({ store }) => { ... })` ou un événement custom.

---

## Composable vs Store — la frontière

C'est **la question récurrente** en projet Vue 3 + Pinia. Quand on a un état partagé, doit-on créer un composable avec ref hors fonction ou un store Pinia ?

### Composable avec état partagé

```ts
// resources/js/Composables/User/useToast.ts
import { ref } from 'vue'

const toasts = ref<Toast[]>([])  // ← partagé entre tous les usages

export function useToast() {
  const pushToast = (toast: Toast) => toasts.value.push(toast)
  return { toasts, pushToast }
}
```

### Store Pinia équivalent

```ts
// resources/js/Stores/User/toastsStore.ts
import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useToastsStore = defineStore('toasts', () => {
  const toasts = ref<Toast[]>([])
  const pushToast = (toast: Toast) => toasts.value.push(toast)
  return { toasts, pushToast }
})
```

### Critères de décision

| Critère | Composable | Pinia |
|---|---|---|
| **Simplicité d'écriture** | ✅ Plus simple | Légèrement plus verbeux |
| **DevTools Vue** | ❌ Non visible | ✅ Apparaît dans Vue DevTools |
| **HMR robuste** | Variable | ✅ Géré nativement |
| **Persistance plugin** | À écrire à la main | ✅ Plugin officiel |
| **SSR (Server-Side Rendering)** | À gérer | ✅ Géré par Pinia |
| **Reset standardisé** | À écrire | ✅ `$reset()` |
| **Subscription aux changements** | À écrire | ✅ `$subscribe()` |
| **Type inference** | Bonne | Excellente |
| **Tests** | Vitest pur | Vitest + helper Pinia |
| **Convention en projet senior** | Pour cas locaux/simples | Pour état applicatif identifié |

### Règle Floty

| Cas | Choix |
|---|---|
| État partagé **simple**, pas de besoin DevTools/plugin | Composable |
| État partagé **identifié comme applicatif** (year, user, drawer global) | Pinia |
| État avec besoin de persistance | Pinia + plugin |
| État avec subscriptions / hooks externes | Pinia |

**Cas spécifique Floty** :

- `useToast` : composable (cas simple, pas besoin de DevTools).
- `useFiscalYearStore` : Pinia (état applicatif central, gain DevTools utile).
- `useCurrentUserStore` : Pinia (état applicatif central, peut être étendu en V2 avec rôles).

> Important : ces choix ne sont pas contradictoires avec `composables-services-utils.md`. Un composable à état partagé reste valide. Pinia est préféré quand le store devient un **artefact applicatif identifié** (apparaît dans Vue DevTools comme « le store Y »), pas pour chaque état partagé trivial.

---

## Subscriptions et patches

Pinia expose des API avancées rarement utiles en Floty mais bonnes à connaître.

### `$subscribe` — réagir aux changements de state

```ts
const store = useFiscalYearStore()
const unsubscribe = store.$subscribe((mutation, state) => {
  console.log(`Year changé : ${state.year}`)
  // Ex: synchroniser avec localStorage manuellement
})
```

### `$onAction` — hook avant/après chaque action

```ts
const store = useFiscalYearStore()
store.$onAction(({ name, args, after, onError }) => {
  console.log(`Action ${name} appelée avec`, args)
  after((result) => console.log('Succès', result))
  onError((error) => console.error('Erreur', error))
})
```

### `$patch` — modifier l'état en batch

```ts
const store = useFiscalYearStore()
store.$patch({ year: 2025 }) // équivalent à store.year = 2025

// Ou en fonction
store.$patch((state) => {
  state.year = 2025
  // ... d'autres mutations
})
```

> Pour Floty V1, ces API sont **rares**. Préférer les actions classiques.

---

## Tests de stores

### Setup avec `createTestingPinia`

```ts
// resources/js/Stores/User/fiscalYearStore.spec.ts
import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useFiscalYearStore } from './fiscalYearStore'

describe('useFiscalYearStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('initialise avec l\'année courante', () => {
    const store = useFiscalYearStore()
    expect(store.year).toBe(new Date().getFullYear())
    expect(store.isCurrent).toBe(true)
  })

  it('setYear modifie l\'année et recalcule isCurrent', () => {
    const store = useFiscalYearStore()
    store.setYear(2024)
    expect(store.year).toBe(2024)
    expect(store.isCurrent).toBe(false)
    expect(store.isPast).toBe(true)
  })

  it('setYear refuse les années invalides', () => {
    const store = useFiscalYearStore()
    expect(() => store.setYear(1999)).toThrow()
    expect(() => store.setYear(3000)).toThrow()
  })
})
```

### Mock dans un test de composant

```ts
import { mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import VehicleListHeader from './VehicleListHeader.vue'

it('affiche l\'année du store', () => {
  const wrapper = mount(VehicleListHeader, {
    global: {
      plugins: [createTestingPinia({
        initialState: {
          fiscalYear: { year: 2024 },
        },
      })],
    },
  })

  expect(wrapper.text()).toContain('Exercice 2024')
})
```

> Détails sur les tests : voir `tests-frontend.md`.

---

## Anti-patterns Pinia (repérés en revue senior)

### Conception

| Anti-pattern | Correction |
|---|---|
| `useAppStore` qui contient tout (year, user, toasts, modals, drawer, settings) | Diviser en 5+ stores spécialisés |
| Store qui réplique des données déjà présentes dans les props Inertia | Ne pas créer de store ; lire `usePage().props.xxx` |
| Store pour les filtres d'une page (recherche, tri, pagination) | Query params URL + `useRemember` |
| Store pour des données qui ne sont consommées qu'à un seul endroit | `ref` local au composant |
| Store pour gérer les erreurs validation d'un formulaire | `useForm` Inertia |
| Création d'un store « pour plus tard » sans usage actuel | YAGNI — créer quand le besoin réel apparaît |

### Implémentation

| Anti-pattern | Correction |
|---|---|
| Style Options Stores (`state`/`getters`/`actions`) | Setup Stores stricts en Floty |
| Destructure du store sans `storeToRefs` (perte de réactivité) | `const { year } = storeToRefs(store)` pour l'état/getters |
| Mutation directe sans action (depuis le composant : `store.year = 2024`) | Pinia tolère mais préférer une action `setYear()` pour traçabilité (DevTools, hooks) |
| Action qui retourne un truc qui devrait être un getter | Getter (computed) |
| Action `async` qui ne lève pas ses erreurs (avale silencieusement) | Toujours laisser remonter ou exposer l'erreur via state |
| Store qui utilise `axios` directement (et pas Inertia) | Inertia couvre 95% des cas ; sinon composable de service |
| Store qui contient du code Vue (refs, computed) injecté dans Pinia incorrectement | Setup Store : utiliser `ref()`, `computed()` normalement |

### Typage

| Anti-pattern | Correction |
|---|---|
| `state` non typé (laissé à l'inference) sur un store complexe | Typer explicitement : `const year = ref<number>(2024)` |
| Action `async` avec `Promise<any>` | Toujours `Promise<XxxData>` ou `Promise<void>` |
| Store qui expose tout son state public sans réflexion | Ne retourner que ce qui est nécessaire (encapsulation) |

### Performance

| Anti-pattern | Correction |
|---|---|
| Store contenant 10 000 entités sans pagination | Repenser : Pinia n'est pas une BDD client. Utiliser props Inertia + pagination serveur |
| Watcher cross-stores qui crée des cycles infinis | Architecture revisitée |
| `$subscribe` qui re-déclenche une action du même store | Cycle infini garanti |

---

## Inventaire prévisionnel des stores Floty V1

À l'implémentation, voici les stores **probables** et leur justification. À valider/ajuster selon les besoins réels.

| Store | Justification | Priorité V1 |
|---|---|---|
| `useFiscalYearStore` | Année fiscale active, transverse à toutes les pages User | **Oui — V1** |
| `useCurrentUserStore` | Cache des infos user, extensible V2 (rôles) | **Oui — V1** (peut commencer comme composable, migrer en Pinia si besoin) |
| `useCompanysReferentielStore` | Cache léger de la liste des entreprises pour les sélecteurs | **À évaluer** — props Inertia peut suffire |
| `useNetworkStatusStore` | Statut online/offline si on ajoute du support offline | **V2+** |
| `useGlobalDrawerStore` | Drawer monté à la racine, ouvrable de partout | **À évaluer** — souvent gérable en local |

> **Discipline** : on **ne crée pas** un store par anticipation. On le crée quand on a un besoin concret qui ne tient pas dans les autres mécanismes.

---

## Checklist — avant de créer un store Pinia

- [ ] J'ai vérifié que les **props Inertia** ne suffisent pas.
- [ ] J'ai vérifié qu'un **composable** ne suffit pas (état hors fonction).
- [ ] J'ai vérifié que **`useRemember`** ou les **query params** ne suffisent pas.
- [ ] L'état est **vraiment cross-page** et persistant à l'intérieur de la session.
- [ ] Le store a une **responsabilité unique** (pas de fourre-tout).
- [ ] J'utilise le style **Setup Store** (composition API).
- [ ] Le **type de retour** est explicite (au moins pour les Refs typées).
- [ ] J'utilise **`storeToRefs`** dans les composants pour préserver la réactivité.
- [ ] J'ai défini un **action** plutôt qu'une mutation directe pour les changements d'état (DevTools).
- [ ] Le store est dans `resources/js/Stores/{Espace}/`.
- [ ] Le nom suit la convention `xxxStore.ts` exportant `useXxxStore`.
- [ ] Test `.spec.ts` adjacent.
- [ ] **Reset à la déconnexion** prévu si l'état contient de l'info utilisateur sensible.
- [ ] Persistance **uniquement** si justifiée (préférences UI durables).

---

## Cohérence avec les autres règles

- **Composables, services, utils** (différence avec composable à état partagé) : voir `composables-services-utils.md`.
- **Navigation Inertia** (`useRemember`, query params, shared props) : voir `inertia-navigation.md`.
- **Composants Vue** (consommation des stores via `storeToRefs`) : voir `vue-composants.md`.
- **TypeScript et DTO** (typage des stores, ré-exports) : voir `typescript-dto.md`.
- **Tests frontend** (`createTestingPinia`, mock de stores) : voir `tests-frontend.md`.
- **Architecture en couches** (Pinia côté front uniquement, jamais de logique métier serveur) : voir `architecture-solid.md`.

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 1.0 | 24/04/2026 | Micha MEGRET | Rédaction initiale — Pinia comme outil de réserve, hiérarchie des 8 mécanismes d'état (du local au global), cas justifiés vs non-justifiés en Floty, Setup Stores stricts, exemple `useFiscalYearStore`, règle critique `storeToRefs`, persistance optionnelle, reset à la déconnexion, frontière composable vs store avec critères de décision, tests avec `createTestingPinia`, anti-patterns repérés en revue senior (conception, implémentation, typage, performance), inventaire prévisionnel Floty V1, checklist. |
