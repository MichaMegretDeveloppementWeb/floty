# Performance UI — règles et anti-patterns

> **Stack référence** : Vue 3.5, Inertia v3, Vite 8, TypeScript 6.
> **Niveau d'exigence** : senior +. La performance se mesure (pas s'invente). Pas d'optimisation prématurée. Pas de skeleton + lazy-loading par réflexe.
> **Documents liés** : `vue-composants.md`, `assets-vite.md`, `inertia-navigation.md`, `composables-services-utils.md`, `pinia-stores.md`.

---

## Pourquoi cette règle existe

La performance UI est un terrain piégé. Les deux excès classiques :

1. **Sur-optimisation prématurée** : virtualisation systématique, skeletons partout, lazy-loading sur tout, `v-memo` sur des composants triviaux. Résultat : code complexe, bugs subtils, performance pas meilleure (parfois pire).
2. **Aucune attention à la perf** : composants qui re-render à chaque keystroke, watch deep sur de gros objets, `computed` qui fait O(n²), liste de 5000 lignes rendue d'un coup. Résultat : UX dégradée, freezes navigateur.

La règle Floty : **mesurer d'abord, optimiser après**. Mais avoir intégré dès l'écriture des **bons réflexes** qui évitent les pires pièges.

---

## Principes structurants

1. **Mesurer d'abord** : Vue DevTools profiler, Performance Chrome, Lighthouse. Pas d'optimisation sans métrique.
2. **Le contenu juste, au bon moment** : pas de skeleton+lazy systématique, mais aussi pas de payload géant en premier.
3. **Réactivité minimale nécessaire** : `shallowRef` au lieu de `ref` quand on remplace l'objet entier ; `markRaw` pour les libs tierces non réactives.
4. **Anti-pattern numéro un : penser que le skeleton améliore l'UX par défaut**. Souvent il l'aggrave (effet papillotement, perception de lenteur).

---

## 1. Profil de performance Floty — où ça se joue vraiment

Floty a quelques zones critiques identifiées (CDC v1.5) :

| Zone | Pourquoi c'est critique | Volume typique |
|---|---|---|
| **Heatmap globale annuelle** (CDC § 3.3) | < 200 ms exigés | 100 véhicules × 52 semaines = 5 200 cellules |
| **Saisie hebdomadaire tableur** (CDC § 3.6) | Saisie clavier rapide, sélection multi-cellules | 100 véhicules × 7 jours, sélection multi |
| **Compteur LCD temps réel** (CDC § 3.4) | Calcul instantané sur survol/édition | Tous les couples (véhicule, entreprise) de l'année |
| **Vue par véhicule** | Timeline 52 semaines colorées par entreprise | 1 ligne × 52 segments |

Tout le reste (CRUD, dashboard, déclarations) est **classique** et n'a pas besoin d'optimisations spécifiques.

> **Stratégie Floty** : **investir l'effort d'optimisation sur ces 4 zones**. Le reste applique les bons réflexes par défaut sans complexité supplémentaire.

---

## 2. Réactivité — choisir le bon outil

### `ref` vs `shallowRef` vs `markRaw`

| API | Réactivité | Usage |
|---|---|---|
| `ref` | Profonde sur l'objet et ses propriétés | Cas par défaut (objets petits/moyens) |
| `shallowRef` | Sur la référence uniquement | **Grosses structures** où on remplace l'array entier (heatmap) |
| `markRaw` | Aucune | Libs tierces non réactives (Chart.js, lecteur PDF, lib calendrier) |
| `triggerRef` | Force le re-render d'un `shallowRef` après mutation interne | À éviter sauf nécessité |

### Pattern Floty — heatmap

```ts
// resources/js/Composables/User/useHeatmap.ts
import { shallowRef, computed } from 'vue'
import type { HeatmapCellData } from '@/types'

export function useHeatmap(initialData: HeatmapCellData[]) {
  // shallowRef car on remplace l'array entier sur changement d'année
  // (5200 éléments — réactivité profonde inutile et coûteuse)
  const cells = shallowRef<HeatmapCellData[]>(initialData)

  const setData = (newCells: HeatmapCellData[]): void => {
    cells.value = newCells // déclenche un re-render unique
  }

  return { cells, setData }
}
```

### Pattern Floty — lib tierce non réactive

```ts
// resources/js/Components/Domain/Planning/ChartContainer.vue (exemple Chart.js si utilisé)
import { onMounted, ref, markRaw } from 'vue'
import { Chart } from 'chart.js/auto'

const chartRef = ref<HTMLCanvasElement | null>(null)
let chartInstance: Chart | null = null

onMounted(() => {
  if (!chartRef.value) return
  // markRaw : Chart.js gère son propre état, pas de réactivité Vue
  chartInstance = markRaw(new Chart(chartRef.value, { /* config */ }))
})

onBeforeUnmount(() => {
  chartInstance?.destroy()
})
```

---

## 3. Computed — règles de performance

### Performance d'un computed

Un `computed` :

- Est **mémoïsé** : ne recalcule que si une de ses dépendances réactives change.
- Est **lazy** : ne s'exécute pas tant que personne n'y accède.
- A un coût négligeable s'il fait peu de choses.

### Pièges classiques

#### Computed qui dépend de tout

```ts
// ❌ MAUVAIS — dépendance sur l'objet entier, recalcul à chaque modif d'un champ
const formattedVehicle = computed(() => {
  return `${vehicle.value.marque} ${vehicle.value.modele} ${vehicle.value.immatriculation}`
})

// ✅ BON — getters précis pour les composants enfants, dépendances minimales
const fullName = computed(() => `${vehicle.value.marque} ${vehicle.value.modele}`)
```

#### Computed avec calcul lourd O(n²)

```ts
// ❌ MAUVAIS — recalcul à chaque ajout d'attribution, O(n²)
const overlaps = computed(() => {
  const result: Overlap[] = []
  for (let i = 0; i < attributions.value.length; i++) {
    for (let j = i + 1; j < attributions.value.length; j++) {
      // ... détection de chevauchement
    }
  }
  return result
})
```

```ts
// ✅ BON — déléguer au backend qui a les index BDD
// Le backend renvoie les overlaps pré-calculés via la prop Inertia.
defineProps<{ attributions: AssignmentData[]; overlaps: Overlap[] }>()
```

#### Computed qui crée un nouvel objet à chaque appel

```ts
// ⚠ ATTENTION — crée un nouvel objet, peut casser la mémoisation des composants enfants
const filterOptions = computed(() => ({
  page: 1,
  search: query.value,
  sort: 'name',
}))

// Si filterOptions est passé en prop à un composant enfant qui watch deep,
// chaque accès à .value retourne une nouvelle référence → re-renders inutiles
```

> Usage : c'est OK si l'objet est consommé une seule fois (ex: passé directement à `router.get(..., filterOptions.value)`). C'est problématique s'il est passé en prop à plusieurs enfants. Dans ce cas, décomposer en plusieurs computed atomiques.

---

## 4. Watchers — règles de performance

### `watch` vs `watchEffect`

| API | Quand utiliser | Coût |
|---|---|---|
| `watch(source, callback)` | On veut réagir à un changement précis avec accès à l'ancienne valeur | Faible |
| `watchEffect(fn)` | Effet automatique qui dépend de plusieurs sources | Faible mais opaque |
| `watch(deep: true)` | **À éviter** sauf cas justifié | Élevé sur grosses structures |

### Pattern — toujours watcher des getters précis

```ts
// ❌ MAUVAIS — deep watch sur tout l'objet
watch(props.vehicle, () => { /* ... */ }, { deep: true })

// ✅ BON — watcher du seul champ qui nous intéresse
watch(() => props.vehicle.id, (newId) => { /* ... */ })

// ✅ BON — watcher de multiples sources ciblées
watch([() => props.vehicle.id, () => props.year], ([newId, newYear]) => { /* ... */ })
```

### Cleanup obligatoire

Tout effet créant une ressource (timer, listener, abonnement) **doit** la nettoyer.

```ts
const stopWatch = watch(year, () => {
  const id = setInterval(refresh, 1000)
  // ❌ Fuite : pas de cleanup
})

watch(year, (_, __, onCleanup) => {
  const id = setInterval(refresh, 1000)
  onCleanup(() => clearInterval(id)) // ✅ Cleanup
})
```

---

## 5. Rendu de listes — `v-for`, `key`, virtualisation

### Toujours `key` stable

```vue
<!-- ❌ MAUVAIS — key = index, instable lors du tri/filter -->
<VehicleCard v-for="(v, i) in vehicles" :key="i" :vehicle="v" />

<!-- ✅ BON — key = id stable -->
<VehicleCard v-for="v in vehicles" :key="v.id" :vehicle="v" />
```

> Une `key` instable force Vue à recréer les composants au lieu de les ré-utiliser, perdant l'état local et le bénéfice de l'algorithme de diff.

### `v-for` + `v-if` sur le même élément

```vue
<!-- ❌ MAUVAIS — itère puis filtre à chaque render -->
<VehicleCard
  v-for="v in vehicles"
  v-if="v.isActive"
  :key="v.id"
  :vehicle="v"
/>

<!-- ✅ BON — filtre via computed -->
<VehicleCard
  v-for="v in activeVehicles"
  :key="v.id"
  :vehicle="v"
/>

<script setup lang="ts">
const activeVehicles = computed(() => vehicles.value.filter(v => v.isActive))
</script>
```

### Virtualisation — quand l'utiliser

La virtualisation (rendu uniquement des éléments visibles dans le viewport) est utile quand :

- Liste de **> 200 éléments DOM lourds** (chaque ligne contient un sous-arbre dense).
- Liste de **> 1000 éléments** quels qu'ils soient.
- Tableau avec scroll vertical et lignes complexes.

**Pour Floty V1**, les cas qui justifient potentiellement la virtualisation :

- **Heatmap** : 5 200 cellules — à profiler. Si rendu < 200 ms sans virtualisation, ne pas virtualiser. Sinon, envisager `vue-virtual-scroller` ou équivalent.
- **Liste véhicules** : ~100 max → pas de virtualisation nécessaire.
- **Liste attributions historiques** : potentiellement gros (5 ans × 365 jours × N véhicules). À évaluer.

### Quand NE PAS virtualiser

- Liste de < 200 éléments simples : la virtualisation ajoute complexité (height fixe, scroll geometry, focus management) pour zéro gain.
- Tableau qui doit être imprimable / exportable PDF en l'état : la virtualisation casse le rendu print.
- Quand on peut paginer côté serveur à la place (souvent meilleure UX).

---

## 6. `v-memo` — quand l'utiliser

`v-memo` (Vue 3.2+) permet de **memoizer** un sous-arbre du template : il n'est re-rendu que si une dépendance listée change.

```vue
<VehicleCard
  v-for="v in vehicles"
  :key="v.id"
  v-memo="[v.id, v.isActive, v.invalidated]"
  :vehicle="v"
/>
```

### Quand l'utiliser

- Liste de centaines d'éléments dont peu changent à chaque modif.
- Élément stable dont le sous-arbre est complexe et dépend de peu de choses.

### Quand NE PAS l'utiliser

- Liste de < 50 éléments : overhead de memoisation > gain.
- Élément qui change fréquemment.
- Premier réflexe « pour optimiser » : c'est souvent inutile.

> **Règle Floty** : ne pas mettre `v-memo` par défaut. Profiler d'abord. Si Vue DevTools profiler montre des re-renders évitables, alors envisager.

---

## 7. Anti-pattern N°1 — skeleton + lazy loading systématique

### Le piège

Beaucoup de devs ajoutent par réflexe :

```vue
<!-- ❌ ANTI-PATTERN systématique -->
<Suspense>
  <template #default>
    <AsyncVehicleCard :vehicle="vehicle" />
  </template>
  <template #fallback>
    <VehicleCardSkeleton />
  </template>
</Suspense>
```

Avec un composant lazy-loaded :

```ts
const AsyncVehicleCard = defineAsyncComponent(() => import('./VehicleCard.vue'))
```

### Pourquoi c'est mauvais par défaut

1. **Effet papillotement** : le skeleton flash très brièvement avant que le contenu n'apparaisse → perçu négativement, sensation de lenteur paradoxalement.
2. **Désynchronisation visuelle** : un partial apparaît avant un autre, layout shift.
3. **Multiplication des requêtes** : chaque chunk lazy = un round-trip HTTP supplémentaire.
4. **Complexité de maintenance** : `<Suspense>` + `fallback` + `error` = 3 cas à gérer pour zéro bénéfice mesurable sur SPA Inertia.
5. **Le code splitting est déjà géré par Inertia** au niveau page : un composant interne à une page est de toute façon dans le chunk de la page.

### Quand le skeleton+lazy a du sens

- Vraiment grosse section non bloquante (ex: timeline d'attributions sur 5 ans qui demande 500 ms à calculer côté serveur). Et encore : utiliser **deferred props Inertia** plutôt que skeleton client (cf. `inertia-navigation.md`).
- Composant énorme rarement affiché (ex: éditeur riche, lecteur PDF embarqué).

### Règle Floty

> **Pas de `defineAsyncComponent` ni de `<Suspense>` par défaut.** Si on en met un, c'est documenté avec sa justification (bénéfice mesuré).

### Alternative recommandée — deferred props Inertia

Plutôt que de gérer côté front avec skeleton/lazy, **déférer côté serveur** :

```php
// Controller
return Inertia::render('User/Vehicles/Show/Show', [
    'vehicle' => VehicleData::from($vehicle),
    'attributionsTimeline' => Inertia::defer(fn () => $service->buildTimeline($vehicle)),
]);
```

```vue
<!-- Vue -->
<Deferred data="attributionsTimeline">
  <template #fallback>
    <div class="animate-pulse h-32 bg-gray-100 rounded" />
  </template>
  <AssignmentsTimeline :data="attributionsTimeline!" />
</Deferred>
```

> La page se rend immédiatement avec ses données critiques. La timeline arrive en second round-trip transparent. Pas de skeleton client à maintenir.

---

## 8. Optimisations spécifiques aux zones critiques Floty

### Heatmap globale (5 200 cellules)

**Stratégies en cumul si nécessaire** (du plus simple au plus complexe) :

1. **Forme passive de cellule** : `<div>` simple avec classe couleur Tailwind (pas de composant Vue par cellule). 5 200 div ≠ 5 200 instances Vue.
2. **`shallowRef`** sur l'array de cellules : modification = remplacement de l'array entier.
3. **`v-memo`** sur la cellule (memoizer par couleur + statut).
4. **CSS Grid** plutôt que table HTML pour le layout (plus performant).
5. **Virtualisation horizontale** uniquement si > 200 véhicules (pas le cas en V1).

```vue
<!-- Components/Domain/Planning/HeatmapGrid.vue (esquisse) -->
<script setup lang="ts">
import { shallowRef } from 'vue'
import type { HeatmapCellData } from '@/types'

const props = defineProps<{ cells: HeatmapCellData[] }>()
</script>

<template>
  <div class="grid grid-cols-[200px_repeat(52,minmax(0,1fr))] gap-px bg-gray-200">
    <template v-for="cell in cells" :key="`${cell.vehicleId}-${cell.weekIndex}`">
      <div
        v-memo="[cell.densityLevel]"
        :class="[
          'h-6 cursor-pointer hover:ring-2 hover:ring-primary-500',
          densityClasses[cell.densityLevel],
        ]"
        :title="cell.label"
        @click="$emit('cellClick', cell)"
      />
    </template>
  </div>
</template>
```

### Saisie hebdomadaire (sélection multi-cellules + clavier)

**Stratégies** :

1. **Pas de re-render de toute la grille** sur chaque sélection : utiliser un **Set d'IDs sélectionnés** dans un `ref`, et calculer le state visuel via classes computed par cellule.
2. **`shallowRef`** pour le store des cellules.
3. **Composable dédié** (`useWeeklyEntrySelection`) qui encapsule la logique clavier.
4. **Délégation d'événements** : un seul `@click` au niveau de la grille, pas un par cellule (réduction des listeners).

### Compteur LCD temps réel

**Stratégies** :

1. **Calcul côté backend** au moment de la mutation (cf. `04-strategie-cache.md`).
2. **Inertia partial reload** sur la prop `lcdCumuls` après chaque ajout d'attribution.
3. **Composable `useLcdCumul`** qui formatte pour l'UI sans recalculer la valeur (vient du backend).
4. **Animation CSS légère** (transform, opacity) au changement de valeur, jamais animation JS.

### Vue par véhicule (timeline 52 semaines)

**Stratégies** :

1. SVG ou CSS Grid pour la timeline (52 segments colorés).
2. Pas de virtualisation (52 éléments seulement).
3. Tooltips lazy au survol (un seul tooltip réutilisé qui se déplace).

---

## 9. Images — performance

### Format et tailles

| Format | Cas | Optimisation |
|---|---|---|
| WebP | Photos, illustrations | `width`/`height` HTML obligatoires (évite layout shift) |
| SVG | Icônes, logos | Inline si < 5 KB, externe sinon |
| Avif | Si support, encore mieux que WebP | Ajouter en `<picture>` avec fallback WebP |

### Loading attributes

```vue
<!-- Image at-the-fold (visible au chargement) -->
<img src="/images/web/home/hero.webp" alt="..." loading="eager" decoding="async" />

<!-- Image below-the-fold (scroll requis) -->
<img src="/images/web/home/feature.webp" alt="..." loading="lazy" decoding="async" />
```

### Dimensions explicites

```vue
<!-- ❌ MAUVAIS — pas de dimensions, layout shift garanti -->
<img src="..." alt="..." />

<!-- ✅ BON — dimensions intrinsèques + classes CSS -->
<img
  src="..."
  alt="..."
  width="1920"
  height="1080"
  class="w-full h-auto"
/>
```

---

## 10. Bundle JS — taille et splitting

### Vérification de la taille

```bash
npm run build
# Vite affiche un récap des chunks et de leur taille
```

### Cibles Floty V1

| Chunk | Taille gzipped cible |
|---|---|
| Vendor (Vue, Pinia, Inertia, lib tierces stables) | < 100 KB |
| App principal (`app.ts` + composants partagés) | < 80 KB |
| Page Inertia typique | < 30 KB |
| Total premier chargement | < 250 KB |

### Comment investiguer

```bash
# Avec rollup-plugin-visualizer (à ajouter pour analyse ponctuelle)
npm i -D rollup-plugin-visualizer
```

```ts
// vite.config.ts (extrait, pour audit)
import { visualizer } from 'rollup-plugin-visualizer'
plugins: [
  // ...
  visualizer({ open: true, filename: 'dist/stats.html' }),
],
```

→ Génère un treemap interactif des chunks pour voir ce qui pèse.

### Anti-patterns bundle

| Anti-pattern | Correction |
|---|---|
| Importer toute une lib pour une fonction (`import _ from 'lodash'`) | Tree-shakeable : `import debounce from 'lodash/debounce'` ou utiliser `@vueuse/core` |
| Lib JS lourde pour une feature mineure | Évaluer le coût/bénéfice ; souvent vanilla JS suffit |
| Chargement eager de Chart.js sur toutes les pages | Lazy via `defineAsyncComponent` justifié si utilisé sur une seule vue |
| Polyfills pour navigateurs antiques | Cible `target: 'es2022'` Vite, pas de polyfills inutiles |

---

## 11. Outils de profiling

### Vue DevTools — onglet « Performance »

1. Ouvrir Vue DevTools (extension navigateur).
2. Onglet « Performance » → « Start recording ».
3. Effectuer l'interaction lente.
4. Stop → analyser les composants qui re-render et leur durée.

### Chrome DevTools Performance

1. Onglet « Performance ».
2. Record → interaction → stop.
3. Analyser le flame chart (scripting, rendering, painting).

### Lighthouse

```bash
npx lighthouse http://localhost:8000/dashboard --view
```

Donne un score global + recommandations.

### Métriques cibles Floty

| Métrique | Cible |
|---|---|
| **First Contentful Paint** (FCP) | < 1.5 s |
| **Largest Contentful Paint** (LCP) | < 2.5 s |
| **Time to Interactive** (TTI) | < 3 s |
| **Total Blocking Time** (TBT) | < 200 ms |
| **Cumulative Layout Shift** (CLS) | < 0.1 |

---

## 12. Tests de performance

Floty n'introduit **pas** de tests de perf automatisés en V1 (overhead, valeur limitée). En revanche :

- **Audit Lighthouse** manuel à chaque livraison majeure.
- **Profiling Vue DevTools** sur les zones critiques (heatmap, saisie tableau) avant validation.
- **Test de charge** côté backend si la liste des véhicules dépasse 200 (V2+).

---

## 13. Anti-patterns récapitulatif

### Réactivité

| Anti-pattern | Correction |
|---|---|
| `ref` sur une grosse structure remplacée entièrement | `shallowRef` |
| Lib tierce wrappée en `ref` | `markRaw` |
| `watch(deep: true)` sur un objet complexe | `watch(() => obj.specificField, ...)` |
| `setInterval` sans cleanup | `onBeforeUnmount` ou `onCleanup` |
| Computed avec O(n²) qui s'exécute à chaque modif | Pré-calculer côté backend ou refactor algorithme |

### Rendu

| Anti-pattern | Correction |
|---|---|
| `key="index"` sur v-for | `key="item.id"` |
| `v-for` + `v-if` sur même élément | Filtrer via computed |
| `v-memo` partout par défaut | Profiler d'abord, n'ajouter qu'avec justification mesurée |
| Composant Vue par cellule pour 5 000 cellules | `<div>` passive avec classes Tailwind |

### Skeleton + lazy

| Anti-pattern | Correction |
|---|---|
| `defineAsyncComponent` partout | Code splitting auto Inertia/Vite suffit |
| `<Suspense>` + `<Skeleton>` sur chaque section | Réservé aux gros chargements justifiés |
| Skeleton fallback systématique | Préférer rendu direct + deferred props Inertia si vraiment lourd |

### Bundle

| Anti-pattern | Correction |
|---|---|
| `import _ from 'lodash'` | Import nominatif ou utiliser `@vueuse/core` |
| Sourcemaps en production | Désactivés (`sourcemap: false`) |
| Polyfills pour navigateurs antiques | Cibler ES2022 |
| Image sans dimensions | `width`/`height` HTML obligatoires |
| Image au-dessus de la pliure en `loading="lazy"` | `loading="eager"` pour LCP |

### Navigation

| Anti-pattern | Correction |
|---|---|
| Recherche live sans debounce | `useDebounceFn` (300 ms typique) |
| Pas d'annulation des requêtes obsolètes | Inertia gère, mais ajouter debounce client |
| `window.location.reload()` | `router.reload()` ou partial `only` |
| Préchargement agressif de toutes les pages | Inertia v3 prefetch sélectif si justifié |

---

## 14. Checklist — performance d'une nouvelle feature

Avant de marquer une feature « complète » sur les zones critiques :

- [ ] La page rend en < 1.5 s en local (mesure Lighthouse ou DevTools).
- [ ] Pas de layout shift visible (mesure CLS).
- [ ] Aucun composant ne re-render plus que nécessaire (Vue DevTools profiler).
- [ ] Watchers nettoyés (`onBeforeUnmount` / `onCleanup`).
- [ ] Pas de `v-for` + `v-if` sur le même élément.
- [ ] `key` stable sur tous les `v-for`.
- [ ] Recherche/filtre temps réel **débouncé**.
- [ ] Pas de `defineAsyncComponent` ni `<Suspense>` injustifié.
- [ ] Si zone critique (heatmap, saisie tableau) : `shallowRef` quand pertinent, optimisations documentées.
- [ ] Bundle de la page < 30 KB gzipped.
- [ ] Images dimensionnées + `loading` adapté.

---

## Cohérence avec les autres règles

- **Composants Vue** (taille, découpage, réactivité) : voir `vue-composants.md`.
- **Bundling Vite** (single entry, code splitting auto, anti-pattern skeleton/lazy) : voir `assets-vite.md`.
- **Navigation Inertia** (deferred props, partial reloads, debounce) : voir `inertia-navigation.md`.
- **Composables, services, utils** (composables d'optimisation comme `useDebouncedRef`) : voir `composables-services-utils.md`.
- **Stores Pinia** (cache léger, performance des subscriptions) : voir `pinia-stores.md`.
- **TypeScript et DTO** (variantes de DTO pour réduire le payload) : voir `typescript-dto.md`.

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 1.0 | 24/04/2026 | Micha MEGRET | Rédaction initiale — principes structurants (mesurer d'abord, pas de skeleton+lazy systématique), zones critiques Floty (heatmap, saisie tableau, compteur LCD, vue véhicule), réactivité performante (`shallowRef`, `markRaw`, `triggerRef`), pièges classiques `computed` et `watch`, virtualisation (quand/quand pas), `v-memo` (quand/quand pas), anti-pattern N°1 skeleton+lazy détaillé avec alternative deferred props, optimisations spécifiques par zone Floty, images (formats, loading, dimensions), bundle JS (cibles, anti-patterns), outils de profiling, métriques cibles Lighthouse, anti-patterns récapitulatif, checklist par feature. |
