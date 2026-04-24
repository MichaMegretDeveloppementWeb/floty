# Navigation Inertia v3 — règles et anti-patterns

> **Stack référence** : Inertia v3.0+, Vue 3.5, Laravel 13, TypeScript 6, Spatie Data 4.
> **Niveau d'exigence** : senior +. Aucune navigation « chaotique », aucune perte d'état au mauvais moment, aucun double-submit silencieux.
> **Documents liés** : `architecture-solid.md`, `vue-composants.md`, `typescript-dto.md`, `composables-services-utils.md`, `gestion-erreurs.md`, `performance-ui.md`.

---

## Pourquoi cette règle existe

Inertia v3 est puissant, mais ses pièges sont nombreux et **silencieux** : navigations concurrentes qui se télescopent, scroll perdu, state partagé qui survit alors qu'il devrait se réinitialiser, formulaires re-soumis par double clic. Ces problèmes sont systématiquement repérés en revue senior et signalent un manque de maîtrise du framework.

Cette règle pose les **patterns canoniques** que Floty applique pour chaque type de navigation, ainsi que les **anti-patterns** à proscrire.

---

## Les 4 mécanismes de navigation Inertia v3

| Mécanisme | Type | Quand l'utiliser |
|---|---|---|
| `<Link>` | Déclaratif | Liens classiques (navigation entre pages) |
| `router.visit/get/post/put/patch/delete` | Impératif | Logique conditionnelle, post-traitement |
| `useForm` | Impératif spécialisé | **Formulaires** (création, édition, suppression confirmée) |
| `router.reload` | Impératif | Recharger les props de la page courante (partial reload) |

Chaque mécanisme a un usage précis. Mélanger sans discernement = source de bugs.

---

## Laravel Wayfinder — routes TypeScript typées (remplace Ziggy)

**Floty utilise Laravel Wayfinder pour toutes les routes**. Wayfinder génère automatiquement des **fonctions TypeScript typées** depuis les controllers Laravel. Aucune chaîne de route n'est plus écrite à la main.

### Pourquoi Wayfinder plutôt que Ziggy

| Aspect | Ziggy (`route()`) | Wayfinder |
|---|---|---|
| Type-safety | Pas de typage sur les paramètres | Types TS stricts générés |
| Refactoring (renommer une route) | Erreur silencieuse à la navigation | **Erreur de compilation TS** |
| Autocomplétion IDE | Limitée (string) | Complète (fonction typée) |
| Détection paramètres manquants | Runtime | **Build-time** |
| Support HTTP methods | Via `route()` simple | `.get()`, `.post()`, `.put()`, `.delete()`, `.form()` générés |
| Dépendance package | `tightenco/ziggy` | `laravel/wayfinder` (officiel) |
| Standard Laravel 13+ | — | **Officiel** depuis les Starter Kits Laravel 12 |

### Setup Wayfinder (repris dans le plan d'implémentation)

```ts
// vite.config.ts
import { wayfinder } from '@laravel/vite-plugin-wayfinder'

export default defineConfig({
  plugins: [
    laravel({ /* ... */ }),
    vue({ /* ... */ }),
    tailwindcss(),
    wayfinder(),    // ← génère les fonctions TS depuis les routes Laravel
  ],
})
```

Génération manuelle : `php artisan wayfinder:generate`. Fichiers produits dans :

- `resources/js/actions/App/Http/Controllers/...` (fonctions par action de controller)
- `resources/js/routes/...` (fonctions par nom de route)
- `resources/js/wayfinder/` (types utilitaires)

### Pattern d'import Wayfinder

```ts
// Import nominatif des actions d'un controller
import VehicleController from '@/actions/App/Http/Controllers/User/VehicleController'

// Usage direct sur une route à paramètre
VehicleController.show({ vehicle: 42 })
// → { url: '/app/vehicles/42', method: 'get' }

// URL brute
VehicleController.show({ vehicle: 42 }).url
// → '/app/vehicles/42'
```

### Pattern avec `<Link>` Inertia

Inertia reconnaît nativement l'objet `{ url, method }` retourné par Wayfinder. On passe la fonction directement :

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import VehicleController from '@/actions/App/Http/Controllers/User/VehicleController'
import type { VehicleListItemData } from '@/types'

defineProps<{ vehicle: VehicleListItemData }>()
</script>

<template>
  <Link :href="VehicleController.show({ vehicle: vehicle.id })" class="text-primary-600 hover:underline">
    {{ vehicle.licensePlate }}
  </Link>
</template>
```

### Pattern avec `router.visit` / `router.post`

```ts
import { router } from '@inertiajs/vue3'
import VehicleController from '@/actions/App/Http/Controllers/User/VehicleController'

const goToVehicle = (vehicleId: number): void => {
  router.visit(VehicleController.show({ vehicle: vehicleId }))
}

const archiveVehicle = (vehicleId: number): void => {
  router.delete(VehicleController.destroy({ vehicle: vehicleId }), {
    preserveScroll: true,
    onSuccess: () => pushToast({ variant: 'success', message: 'Véhicule supprimé.' }),
  })
}
```

### Pattern avec `useForm`

```ts
import { useForm } from '@inertiajs/vue3'
import VehicleController from '@/actions/App/Http/Controllers/User/VehicleController'

const form = useForm<VehicleFormFields>({ /* champs initiaux */ })

// Submit — pointe directement sur l'action Wayfinder
form.submit(VehicleController.store())
```

Alternative : certaines variantes Wayfinder exposent `.form()` qui retourne un objet directement adapté aux `useForm` et aux `<form>` HTML natifs (`VehicleController.store.form()`).

### Anti-patterns Wayfinder

| Anti-pattern | Correction |
|---|---|
| `<Link :href="route('user.vehicles.show', { vehicle: id })">` (Ziggy) | Wayfinder : `VehicleController.show({ vehicle: id })` |
| `const url = route('user.vehicles.index')` | `VehicleController.index().url` |
| String littérale `<Link href="/app/vehicles">` (hardcoded) | Toujours via Wayfinder pour la type-safety |
| Mélange Ziggy + Wayfinder selon les pages | Wayfinder exclusif — Ziggy **n'est pas installé** sur Floty |
| Oubli de régénérer après ajout d'une route Laravel | Le plugin Vite `wayfinder()` régénère automatiquement en dev ; en CI, toujours `php artisan wayfinder:generate` avant le build |

### Cohérence avec les conventions

- Les routes sont **nommées** côté Laravel (`Route::get(...)->name('user.vehicles.show')`). Wayfinder utilise ces noms pour générer les fonctions.
- Aucune collision : la fonction TS générée porte le nom **de l'action de controller** (ex: `show`), pas le nom de la route.
- Les paramètres de route (`{vehicle}`) sont typés automatiquement selon les type-hints PHP et le route model binding.

---

## 1. `<Link>` — navigation déclarative

### Quand utiliser

- Liens de navigation simples entre pages (sidebar, navbar, breadcrumbs).
- Liens dans une liste (`VehicleCard` qui pointe vers `Show`).
- Toute navigation **idempotente** (GET).

### Pattern de référence

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
</script>

<template>
  <Link
    :href="route('user.vehicles.show', { vehicle: vehicle.id })"
    class="text-primary-600 hover:underline"
  >
    {{ vehicle.immatriculation }}
  </Link>
</template>
```

### Options utiles

| Prop | Usage |
|---|---|
| `method` | `'get'` (défaut), `'post'`, `'put'`, `'patch'`, `'delete'` |
| `preserve-scroll` | Garder la position de scroll après navigation |
| `preserve-state` | Garder l'état des composants (rare, voir § plus bas) |
| `replace` | Remplacer dans l'historique (pas de back possible) |
| `only` | Partial reload : ne re-fetch que ces props |
| `as` | Rendre comme `'button'` au lieu de `'a'` (rare, a11y attention) |

### Anti-patterns

| Anti-pattern | Correction |
|---|---|
| `<a :href="route(...)">` | Utiliser `<Link>` (Inertia gère la navigation SPA, pas de full reload) |
| `<button @click="router.visit(...)">` pour de la navigation simple | `<Link>` est plus a11y et plus déclaratif |
| `<Link as="button">` sans `aria-label` | Si on s'éloigne du `<a>`, restaurer la sémantique a11y |

---

## 2. `router.visit/get/post/...` — navigation impérative

### Quand utiliser

- Logique conditionnelle avant navigation (`if (isValid) router.visit(...)`).
- Post-traitement (callbacks `onSuccess`, `onError`, `onFinish`).
- Actions déclenchées par un événement non-clic (timer, watcher, raccourci clavier).

### Pattern de référence

```ts
import { router } from '@inertiajs/vue3'

const handleArchive = (vehicleId: number): void => {
  router.delete(route('user.vehicles.destroy', { vehicle: vehicleId }), {
    preserveScroll: true,
    onBefore: () => confirm('Confirmer la suppression ?'),
    onSuccess: () => pushToast({ variant: 'success', message: 'Véhicule supprimé.' }),
    onError: (errors) => console.error(errors),
    onFinish: () => { /* nettoyage UI éventuel */ },
  })
}
```

### Callbacks — cycle de vie

| Callback | Quand |
|---|---|
| `onBefore` | Avant la requête. **Retourner `false` annule** la navigation. |
| `onCancelToken` | Reçoit un token d'annulation (utile pour annuler depuis un autre événement). |
| `onStart` | Quand la requête commence. |
| `onProgress` | Pour les uploads (FormData) — barre de progression. |
| `onSuccess` | Réponse 200 reçue. |
| `onError` | Réponse 422 (validation) ou autre erreur. |
| `onFinish` | Toujours appelé en dernier (succès ou erreur). |

### Annulation d'une navigation en cours

```ts
import { router } from '@inertiajs/vue3'

let activeRequest: { cancel: () => void } | null = null

const search = (query: string): void => {
  activeRequest?.cancel()

  router.get(route('user.vehicles.index'), { search: query }, {
    preserveScroll: true,
    preserveState: true,
    only: ['vehicles'],
    onCancelToken: (token) => {
      activeRequest = token
    },
    onFinish: () => {
      activeRequest = null
    },
  })
}
```

### `router.cancelAll()` (Inertia v3)

Annule **toutes** les requêtes en cours (sync, async, prefetch). Utile pour les transitions critiques.

```ts
router.cancelAll() // équivalent v2 : router.cancel()
```

> Le rename `cancel` → `cancelAll` fait partie des breaking changes Inertia v3 (cf. doc audit versions).

---

## 3. `useForm` — formulaires

### Quand utiliser

**Tout formulaire qui modifie des données serveur** : création, édition, action confirmée (suppression avec confirmation modale).

### Pattern de référence

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import type { VehicleFormData } from '@/types'
import TextInput from '@/Components/Ui/Input/TextInput.vue'
import Button from '@/Components/Ui/Button/Button.vue'
import InputError from '@/Components/Ui/Input/InputError.vue'

type VehicleFormFields = Omit<VehicleFormData, 'id'>

const props = defineProps<{
  vehicle?: VehicleFormData
}>()

const isEditing = computed(() => props.vehicle !== undefined)

const form = useForm<VehicleFormFields>({
  immatriculation: props.vehicle?.immatriculation ?? '',
  marque: props.vehicle?.marque ?? '',
  modele: props.vehicle?.modele ?? '',
  // ... tous les champs initialisés
})

const submit = (): void => {
  if (isEditing.value) {
    form.put(route('user.vehicles.update', { vehicle: props.vehicle!.id }), {
      preserveScroll: true,
    })
  } else {
    form.post(route('user.vehicles.store'), {
      preserveScroll: true,
      onSuccess: () => form.reset(),
    })
  }
}
</script>

<template>
  <form @submit.prevent="submit" class="space-y-4">
    <div>
      <TextInput
        v-model="form.immatriculation"
        label="Immatriculation"
        :invalid="!!form.errors.immatriculation"
      />
      <InputError :message="form.errors.immatriculation" />
    </div>

    <div>
      <TextInput v-model="form.marque" label="Marque" :invalid="!!form.errors.marque" />
      <InputError :message="form.errors.marque" />
    </div>

    <div>
      <TextInput v-model="form.modele" label="Modèle" :invalid="!!form.errors.modele" />
      <InputError :message="form.errors.modele" />
    </div>

    <div class="flex justify-end gap-3">
      <Button variant="ghost" type="button" @click="form.reset()">Annuler</Button>
      <Button type="submit" :loading="form.processing" :disabled="form.processing">
        {{ isEditing ? 'Mettre à jour' : 'Créer' }}
      </Button>
    </div>
  </form>
</template>
```

### Propriétés utiles de `useForm`

| Propriété | Usage |
|---|---|
| `form.data()` | Récupère l'état actuel |
| `form.errors` | Erreurs validation 422 (alimenté automatiquement) |
| `form.processing` | `true` pendant l'envoi |
| `form.recentlySuccessful` | `true` 2 secondes après un succès |
| `form.isDirty` | `true` si les données diffèrent de l'état initial |
| `form.hasErrors` | `true` s'il y a au moins une erreur |
| `form.reset(...keys)` | Reset à l'état initial (tous les champs ou ceux passés) |
| `form.clearErrors(...keys)` | Efface les erreurs |
| `form.setError(key, message)` | Définit une erreur manuellement |
| `form.transform(fn)` | Transformer les données avant envoi |
| `form.cancel()` | Annule la requête en cours |

### `form.transform` — usage typique

Quand le format envoyé au serveur diffère légèrement du format affiché (date formatée, valeur dérivée).

```ts
form.transform((data) => ({
  ...data,
  acquisitionDate: format(data.acquisitionDate, 'yyyy-MM-dd'),
}))
```

### Anti-patterns `useForm`

| Anti-pattern | Correction |
|---|---|
| Soumettre via `router.post(route, form.data())` | Utiliser `form.post(route)` directement (gère erreurs, processing, recentlySuccessful) |
| Pas de `preserveScroll: true` sur les submit qui restent sur la même page | Préserver le scroll par défaut sur les éditions |
| Pas de gestion `form.processing` (pas de bouton désactivé) | Toujours `:disabled="form.processing"` + `:loading="form.processing"` sur le bouton submit |
| Reset manuel des champs au succès | `onSuccess: () => form.reset()` |
| `form.errors.field` sans `!!form.errors.field` pour les booléens | Toujours coercer pour les `:invalid` ou `v-if` |
| Mutation directe de `form.data()` | `form.data()` retourne une **copie**. Pour modifier, accéder via `form.field = value`. |

---

## 4. Partial reloads — `only` et `except`

### Principe

Inertia permet de re-fetch **seulement certaines props** d'une page sans tout recharger. Bénéfices : moins de données transférées, état des composants préservé, perceptiblement plus rapide.

### Pattern Floty — recharger les filtres sans recharger les véhicules

```ts
router.get(route('user.vehicles.index'), { search: query }, {
  preserveState: true,
  preserveScroll: true,
  only: ['vehicles'], // re-fetch UNIQUEMENT cette prop
})
```

### Combinaison avec `useForm` (méthode `transform`)

```ts
form.get(route('user.vehicles.index'), {
  preserveState: true,
  only: ['vehicles', 'filters'],
})
```

### `except` — l'inverse

```ts
router.reload({
  except: ['heavyData'], // re-fetch tout sauf cette prop
})
```

### Anti-patterns

| Anti-pattern | Correction |
|---|---|
| `router.visit(...)` complet quand seule une portion change | `only: ['xxx']` + `preserveState: true` |
| `only` sans `preserveState: true` | L'état des composants se réinitialise → effet visuel cassé |
| `only: ['vehicles']` mais on s'attend à voir `fiscalYear` rafraîchi | `only` est exhaustif : seules les props listées sont mises à jour |

---

## 5. Deferred props (Inertia v3) — chargement différé

Inertia v3 introduit les **deferred props** qui retardent le chargement de certaines données après le rendu initial de la page. Utile pour les sections lourdes qui ne doivent pas bloquer l'affichage initial.

### Côté Laravel

```php
return Inertia::render('User/Vehicles/Show/Show', [
    'vehicle' => VehicleData::from($vehicle),
    'attributions' => Inertia::defer(fn () => $attributionService->forVehicle($vehicle)),
    'fiscalHistory' => Inertia::defer(fn () => $fiscalService->history($vehicle)),
]);
```

### Côté Vue

```vue
<script setup lang="ts">
import { Deferred } from '@inertiajs/vue3'
import type { AssignmentData, VehicleFiscalCharacteristicsData } from '@/types'

defineProps<{
  vehicle: VehicleData
  attributions?: AssignmentData[]      // optionnel — peut être undefined initialement
  fiscalHistory?: VehicleFiscalCharacteristicsData[]
}>()
</script>

<template>
  <div>
    <VehicleSummary :vehicle="vehicle" />

    <Deferred data="attributions">
      <template #fallback>
        <div class="animate-pulse h-32 bg-gray-100 rounded" />
      </template>
      <AssignmentsTimeline :attributions="attributions!" />
    </Deferred>
  </div>
</template>
```

### Quand utiliser

- Section secondaire d'une page qui demande un calcul lourd (ex: timeline d'attributions sur 5 ans).
- Données rarement consultées par défaut (ex: historique des PDF générés).
- Section qui dépendrait d'une jointure SQL coûteuse non bloquante pour le rendu principal.

### Quand NE PAS utiliser

- Données critiques au rendu initial.
- Données légères (le coût du round-trip supplémentaire dépasse le gain).
- Pour faire « comme si on faisait du SSR progressif » sur tout — c'est un anti-pattern de complexité (cf. `performance-ui.md`).

---

## 6. `usePage` et shared props

### Accès aux props partagées

```ts
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const currentUser = computed(() => page.props.auth.user) // typé via inertia.d.ts
const flashSuccess = computed(() => page.props.flash.success)
```

### Réactivité

`usePage()` retourne un objet réactif. Toute modification des shared props (par une nouvelle navigation) déclenche les re-renders.

### Anti-patterns

| Anti-pattern | Correction |
|---|---|
| `const page = usePage()` puis destructure `const { auth } = page.props` | Perte de réactivité — toujours via `computed` ou accès `page.props.xxx` |
| `usePage()` appelé hors composant Vue (dans un module pur) | `usePage` doit être appelé dans `setup` ou un composable composé dans `setup` |
| Modifier `page.props` directement | Les shared props sont **read-only** côté Vue. Modifier côté backend uniquement. |

---

## 7. Layouts persistants — `defineOptions`

### Pattern Floty

Cf. `architecture-solid.md` et `structure-fichiers.md` : on assigne le layout par défaut selon l'espace via `app.ts`, ou explicitement via `defineOptions`.

```vue
<script setup lang="ts">
import UserLayout from '@/Components/Layouts/UserLayout.vue'

defineOptions({ layout: UserLayout })
</script>
```

### Layouts imbriqués (rare)

```ts
defineOptions({
  layout: [WebLayout, AuthSubLayout], // imbrication : WebLayout > AuthSubLayout > Page
})
```

### Layout persistant (état préservé entre navigations)

Quand le layout est le même entre deux pages (typique de la navigation sidebar), Inertia préserve son état (Vue ne le détruit pas) → le sidebar reste ouvert, les listeners restent en place.

**Pas d'action particulière à faire** côté code : c'est le comportement par défaut tant que le composant Layout est référentiellement identique (donc l'import statique fonctionne).

---

## 8. Préservation d'état — `preserveScroll`, `preserveState`

| Option | Effet | Cas Floty |
|---|---|---|
| `preserveScroll: true` | Garde la position de scroll de la page après navigation | Édition d'un véhicule sans perdre la position dans la liste |
| `preserveState: true` | Préserve l'état des composants Vue (refs, computed, etc.) | Filtre/recherche sans réinitialiser le composant |
| `preserveScroll: false` (défaut) | Scroll en haut après navigation | Navigation entre pages distinctes (vehicles → declarations) |
| `preserveState: false` (défaut) | Composants détruits et recréés | Navigation entre pages distinctes |

### Patterns combinés Floty

| Cas Floty | Configuration |
|---|---|
| Soumission de formulaire édition (rester sur la même page) | `preserveScroll: true` |
| Recherche/filtrage temps réel (rester sur la même page, état UI préservé) | `preserveScroll: true`, `preserveState: true`, `only: [...]` |
| Navigation depuis sidebar (changer de page) | Aucune option (défaut OK) |
| Pagination (rester sur la même page, scroll en haut) | `preserveState: true`, scroll par défaut |
| Tri d'une colonne (rester sur la même page, garder filtres en mémoire) | `preserveScroll: true`, `preserveState: true`, `only: ['vehicles']` |

---

## 9. `useRemember` — état persistant en navigation

Inertia offre `useRemember` pour **persister l'état d'un composant** à travers les navigations (back/forward navigateur).

### Pattern Floty

```ts
import { useRemember } from '@inertiajs/vue3'

// Filtres persistants — l'utilisateur navigue vers une fiche, revient via back, les filtres sont restaurés
const filters = useRemember({
  search: '',
  vehicleUserType: null as string | null,
  status: 'active' as 'active' | 'inactive' | 'all',
}, 'vehicles-index-filters') // clé unique pour scoper
```

### Quand utiliser

- Filtres qu'on veut restaurer si l'utilisateur fait back.
- Sélection multi-cellules (`WeeklyEntry`) qu'on veut conserver après une navigation collatérale.
- Onglet actif d'un composant à onglets.

### Anti-patterns

| Anti-pattern | Correction |
|---|---|
| `useRemember` sans clé unique sur des composants instantiés plusieurs fois | Toujours clé unique scopée par contexte (`vehicles-${userId}-filters`) |
| `useRemember` pour de l'état serveur (devrait être props Inertia) | Utiliser les props Inertia + URL query params (cf. § ci-dessous) |
| `useRemember` qui contient une grosse structure | Réservé aux états légers (préfèrent les query params si volume) |

---

## 10. URL et query parameters

### Principe

Pour les états qui méritent d'être **partageables par URL** (filtres, pagination, tri), on les stocke dans les query params de la route.

### Pattern Floty

```ts
import { router } from '@inertiajs/vue3'

const updateFilters = (newFilters: { search?: string; type?: string }): void => {
  router.get(route('user.vehicles.index'), newFilters, {
    preserveState: true,
    preserveScroll: true,
    only: ['vehicles', 'pagination'],
    replace: true, // remplace dans l'historique au lieu de pousser
  })
}
```

### `replace: true` vs `push` (défaut)

- `push` (défaut) : ajoute une entrée à l'historique navigateur. Le bouton « back » revient à l'état précédent.
- `replace: true` : remplace l'entrée actuelle. Le bouton « back » saute par-dessus.

**Règle Floty** : utiliser `replace: true` pour les filtres temps réel (taper dans une recherche ne doit pas créer 30 entrées d'historique). Utiliser `push` (défaut) pour les changements significatifs (passer de page 1 à page 2 d'une liste).

---

## 11. Les pièges classiques de la navigation Inertia (à éviter)

### Piège 1 — Navigations concurrentes

```ts
// ❌ MAUVAIS — chaque keystroke déclenche une nav, elles s'empilent
const onSearch = (query: string): void => {
  router.get(route('vehicles.index'), { search: query }, {
    only: ['vehicles'],
  })
}
```

```ts
// ✅ BON — debounce + cancellation
import { useDebounceFn } from '@vueuse/core'

const onSearch = useDebounceFn((query: string): void => {
  router.get(route('vehicles.index'), { search: query }, {
    only: ['vehicles'],
    preserveState: true,
    preserveScroll: true,
  })
}, 250)
```

> Inertia gère automatiquement la cancellation des navigations concurrentes au même endpoint (la dernière gagne), mais le debounce reste obligatoire pour ne pas surcharger le serveur.

### Piège 2 — Soumission de formulaire double-cliqué

```ts
// ❌ MAUVAIS — le bouton n'est pas désactivé pendant la requête, double-clic possible
<button type="submit">Enregistrer</button>
```

```ts
// ✅ BON — bouton désactivé pendant le processing
<Button type="submit" :loading="form.processing" :disabled="form.processing">
  Enregistrer
</Button>
```

### Piège 3 — État perdu après une mutation

```ts
// ❌ MAUVAIS — après submit, le composant est recréé, le scroll est perdu
form.post(route('user.vehicles.store'))
```

```ts
// ✅ BON — préserver scroll
form.post(route('user.vehicles.store'), {
  preserveScroll: true,
  onSuccess: () => form.reset(),
})
```

### Piège 4 — Naviguer pendant un cycle de vie

```ts
// ❌ MAUVAIS — navigation déclenchée pendant onMounted, race conditions
onMounted(() => {
  router.visit(route('user.dashboard'))
})
```

```ts
// ✅ BON — déléguer la décision au backend (redirect serveur)
// Si la décision dépend du contexte, c'est au controller de rediriger
```

### Piège 5 — Lien externe via `<Link>`

```vue
<!-- ❌ MAUVAIS — Link Inertia sur URL externe → erreur runtime -->
<Link href="https://github.com">GitHub</Link>

<!-- ✅ BON — <a> classique pour les externes -->
<a href="https://github.com" target="_blank" rel="noopener noreferrer">GitHub</a>
```

### Piège 6 — `preserveState` activé alors qu'on attend des refs réinitialisées

```ts
// ❌ MAUVAIS — l'utilisateur change de page, mais le composant est préservé → refs anciennes restent
<Link :href="route('vehicles.index', { page: 2 })" preserve-state>
```

```ts
// ✅ BON — preserveState seulement quand l'état UI doit explicitement persister
<Link :href="route('vehicles.index', { page: 2 })">
```

### Piège 7 — `usePage` destructuré

```ts
// ❌ MAUVAIS — perte de réactivité
const { props } = usePage()
const user = props.auth.user // valeur figée

// ✅ BON — accès via getter ou computed
const page = usePage()
const user = computed(() => page.props.auth.user)
```

### Piège 8 — Refresh complet via `window.location.reload`

```ts
// ❌ MAUVAIS — casse la SPA, perd l'état Inertia
window.location.reload()

// ✅ BON — Inertia API
router.reload()
// Ou avec partial : router.reload({ only: ['vehicles'] })
```

---

## 12. Gestion des erreurs de navigation

### Erreur 422 (validation)

`form.errors` est alimenté automatiquement par `useForm`. Le composant les affiche via slots.

```ts
form.post(route('user.vehicles.store'), {
  preserveScroll: true,
  onError: (errors) => {
    // errors est { field: 'message' }
    // Pas besoin de gérer manuellement — useForm le fait
  },
})
```

### Erreur 419 (CSRF expiré)

Géré côté handler Laravel (cf. `gestion-erreurs.md`) : `back()->with('toast-warning', 'Session expirée')`.

### Erreur réseau (offline, timeout)

Inertia v3 émet l'événement `error` sur le router :

```ts
import { router } from '@inertiajs/vue3'

router.on('error', (event) => {
  pushToast({
    variant: 'error',
    message: 'Erreur réseau. Vérifiez votre connexion.',
  })
})
```

À enregistrer dans `app.ts` au démarrage.

> Détails complets : voir `gestion-erreurs.md`.

---

## 13. Événements router globaux

Inertia v3 expose des événements router pour les hooks globaux (analytics, loaders, etc.).

```ts
// resources/js/app.ts
import { router } from '@inertiajs/vue3'

router.on('start', () => {
  // Une navigation a démarré — afficher un loader si besoin
})

router.on('finish', () => {
  // Toute navigation terminée
})

router.on('navigate', (event) => {
  // Successfully navigated to a new page
})

router.on('exception', (event) => {
  // Erreur non gérée
})
```

**Règle Floty** : ne pas pousser de toast sur chaque `start`/`finish` (effet visuel parasite). Inertia affiche déjà sa propre progress bar (configurable).

---

## 14. Cas particulier — wizard multi-étapes

Pour les wizards (création multi-étapes), deux stratégies :

### Stratégie A — Une seule page Vue, état local

L'état du wizard vit dans le composant. Soumission finale uniquement à l'étape finale.

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const step = ref<1 | 2 | 3>(1)
const form = useForm({ /* tous les champs des 3 étapes */ })

const next = (): void => { step.value = (step.value + 1) as 1 | 2 | 3 }
const previous = (): void => { step.value = (step.value - 1) as 1 | 2 | 3 }

const submit = (): void => {
  form.post(route('user.assignments.store'))
}
</script>
```

### Stratégie B — Pages Inertia distinctes

Chaque étape est sa propre page, soumission progressive vers le serveur. Plus complexe mais permet la reprise (ex: revenir au wizard après navigation).

> Pour Floty V1, **stratégie A** est préférée pour le wizard d'attribution rapide (CDC § 3.8). Plus simple, plus rapide.

---

## 15. Anti-patterns Inertia — récapitulatif

| Anti-pattern | Correction |
|---|---|
| `<a :href="route(...)">` au lieu de `<Link>` | `<Link>` Inertia (SPA, pas de full reload) |
| Recherche temps réel sans debounce | `useDebounceFn` + Inertia gère la cancellation |
| Soumission sans `:disabled="form.processing"` | Toujours désactiver pendant `processing` |
| `preserveState` mal utilisé (ré-init voulue) | N'activer que si on veut explicitement préserver |
| Navigation pendant `onMounted` | Déléguer au backend (redirect serveur) |
| `<Link>` sur URL externe | `<a target="_blank" rel="noopener noreferrer">` |
| `usePage` destructuré | Toujours via getter ou computed |
| `window.location.reload()` | `router.reload()` |
| `axios.get()` direct au lieu de Inertia | Inertia ou composable dédié si vraiment hors flux Inertia |
| Multiple `router.visit` concurrents au même endpoint | Inertia cancel le précédent automatiquement, mais débouncer côté client |
| `useRemember` sans clé unique | Toujours clé scopée par contexte |
| Naviguer avec method `'post'` via `<Link>` sans `confirm` | UX risquée — préférer `useForm.delete` avec confirmation |
| Mutation directe d'une shared prop | Read-only côté Vue, modifier backend |
| Tout passer en `Inertia::defer` | Réservé aux sections vraiment lourdes non bloquantes |

---

## Checklist — avant de considérer une navigation comme « terminée »

- [ ] `<Link>` Inertia utilisé pour les navigations déclaratives.
- [ ] `router.visit/post/...` utilisé uniquement pour la logique impérative.
- [ ] `useForm` utilisé pour tous les formulaires modifiant des données.
- [ ] Bouton submit toujours `:disabled="form.processing"` + `:loading="form.processing"`.
- [ ] `preserveScroll: true` sur les éditions qui restent sur la même page.
- [ ] `preserveState: true` pour la recherche / pagination (UI préservée).
- [ ] `only: [...]` pour les partial reloads.
- [ ] Recherche temps réel **débouncée**.
- [ ] `useRemember` avec clé unique scopée si état persistant nécessaire.
- [ ] `replace: true` pour les query params filtres (pas d'historique pollué).
- [ ] Accès `usePage()` via `computed` ou getter (jamais destructuré).
- [ ] Liens externes via `<a target="_blank" rel="noopener noreferrer">`.
- [ ] Erreurs 419 gérées via handler Laravel global.
- [ ] Erreurs 422 gérées via `form.errors`.
- [ ] Pas de `window.location.*`, toujours API Inertia.

---

## Cohérence avec les autres règles

- **Architecture en couches** (controllers Inertia + Resource Spatie Data) : voir `architecture-solid.md`.
- **TypeScript et DTO** (typage `useForm`, props page) : voir `typescript-dto.md`.
- **Composants Vue** (props, emits, state hoisting, slots) : voir `vue-composants.md`.
- **Composables, services, utils** (composables de navigation, debounce) : voir `composables-services-utils.md`.
- **Stores Pinia** (état cross-page vs `useRemember` vs query params) : voir `pinia-stores.md`.
- **Performance UI** (deferred props, lazy loading, anti-patterns skeleton) : voir `performance-ui.md`.
- **Gestion des erreurs** (419 CSRF, validation 422, toasts via flash) : voir `gestion-erreurs.md`.
- **Conventions de nommage** (routes, controllers, requests) : voir `conventions-nommage.md`.

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 1.1 | 24/04/2026 | Micha MEGRET | Ajout de la section **Laravel Wayfinder** (étape 5.8) : patterns Wayfinder pour `<Link>`, `router.visit`, `useForm`, tableau comparatif Wayfinder vs Ziggy, anti-patterns Wayfinder, setup Vite. Wayfinder devient le standard Floty pour les routes typées (remplace Ziggy qui n'est pas installé). |
| 1.0 | 24/04/2026 | Micha MEGRET | Rédaction initiale — 4 mécanismes de navigation Inertia v3 (`<Link>`, `router`, `useForm`, `router.reload`), patterns de référence Floty pour chacun, deferred props v3, partial reloads (`only`/`except`), `useRemember`, query params + `replace`, gestion `usePage`, layouts persistants, événements router globaux, 8 pièges classiques détaillés, wizards multi-étapes, anti-patterns repérés en revue senior, checklist. Spécifique à Inertia v3 (rename `cancel` → `cancelAll`, `Inertia::defer`, `Deferred` component). |
