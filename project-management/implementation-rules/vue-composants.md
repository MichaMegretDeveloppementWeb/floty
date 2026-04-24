# Composants Vue 3 — règles d'implémentation senior+

> **Stack référence** : Vue 3.5, TypeScript 6, Inertia v3, Tailwind 4, Spatie Laravel Data 4.
> **Niveau d'exigence** : senior +. Composition API stricte, `<script setup lang="ts">` partout. Aucun `any`. Aucune mutation de prop. Aucun usage d'Options API.
> **Documents liés** : `architecture-solid.md`, `typescript-dto.md`, `inertia-navigation.md`, `composables-services-utils.md`, `pinia-stores.md`, `performance-ui.md`, `tests-frontend.md`, `assets-vite.md`, `conventions-nommage.md`.

---

## Principes structurants

Chaque composant Vue Floty respecte les principes suivants, sans exception :

1. **Composition API stricte** avec `<script setup lang="ts">`. L'Options API est interdite (sauf cas exceptionnel documenté).
2. **TypeScript strict** sur tout : props, emits, slots, composables, refs, computed.
3. **Immutabilité des props** : les props sont readonly, on ne les mute jamais. Toute modification passe par un emit vers le parent.
4. **Responsabilité unique** : un composant fait une seule chose. Si un composant dépasse 250-300 lignes, il faut le découper.
5. **Présentation séparée du métier** : un composant ne contient pas de logique fiscale, pas d'appel à un store global pour des choses qui devraient être des props.
6. **Accessibilité (a11y) intégrée** : labels, focus management, ARIA pertinents — pas un nice-to-have, une obligation.
7. **Composants nommés en multi-mot PascalCase** (ex: `VehicleCard.vue`, jamais `card.vue`) — convention W3C pour éviter les collisions HTML.

---

## Squelette d'un composant Floty

### Pattern de référence — page Inertia

```vue
<!-- resources/js/Pages/User/Vehicles/Index/Index.vue -->
<script setup lang="ts">
import type { VehicleListItemData } from '@/types'
import UserLayout from '@/Components/Layouts/UserLayout.vue'
import VehicleListHeader from './Partials/VehicleListHeader.vue'
import VehicleFilters from './Partials/VehicleFilters.vue'
import VehicleTable from './Partials/VehicleTable.vue'
import VehicleEmptyState from './Partials/VehicleEmptyState.vue'

defineOptions({ layout: UserLayout })

const props = defineProps<{
  vehicles: VehicleListItemData[]
  fiscalYear: number
}>()

const hasVehicles = computed(() => props.vehicles.length > 0)
</script>

<template>
  <div class="space-y-6 p-6">
    <VehicleListHeader :fiscal-year="fiscalYear" :vehicle-count="vehicles.length" />
    <VehicleFilters />
    <VehicleTable v-if="hasVehicles" :vehicles="vehicles" />
    <VehicleEmptyState v-else />
  </div>
</template>
```

### Pattern de référence — composant UI Kit

```vue
<!-- resources/js/Components/Ui/Button/Button.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import type { ButtonVariant, ButtonSize } from '@/types/ui'

const props = withDefaults(defineProps<{
  variant?: ButtonVariant
  size?: ButtonSize
  type?: 'button' | 'submit' | 'reset'
  disabled?: boolean
  loading?: boolean
  block?: boolean
}>(), {
  variant: 'primary',
  size: 'md',
  type: 'button',
  disabled: false,
  loading: false,
  block: false,
})

const emit = defineEmits<{
  click: [event: MouseEvent]
}>()

const isDisabled = computed(() => props.disabled || props.loading)

const variantClasses = computed<string>(() => {
  switch (props.variant) {
    case 'primary':   return 'bg-primary-600 hover:bg-primary-700 text-white focus-visible:ring-primary-500'
    case 'secondary': return 'bg-gray-100 hover:bg-gray-200 text-gray-900 focus-visible:ring-gray-400'
    case 'ghost':     return 'bg-transparent hover:bg-gray-100 text-gray-700 focus-visible:ring-gray-400'
    case 'danger':    return 'bg-error hover:bg-error/90 text-white focus-visible:ring-error'
    default: {
      const _exhaustive: never = props.variant
      throw new Error(`Variant non géré : ${_exhaustive}`)
    }
  }
})

const sizeClasses = computed<string>(() => {
  switch (props.size) {
    case 'sm': return 'px-3 py-1.5 text-sm rounded-md'
    case 'md': return 'px-4 py-2 text-base rounded-md'
    case 'lg': return 'px-6 py-3 text-lg rounded-lg'
  }
})

const handleClick = (event: MouseEvent): void => {
  if (isDisabled.value) return
  emit('click', event)
}
</script>

<template>
  <button
    :type="type"
    :disabled="isDisabled"
    :class="[
      'inline-flex items-center justify-center font-medium transition-colors',
      'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2',
      'disabled:opacity-50 disabled:cursor-not-allowed',
      variantClasses,
      sizeClasses,
      block && 'w-full',
    ]"
    @click="handleClick"
  >
    <span v-if="loading" class="mr-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" aria-hidden="true" />
    <slot />
  </button>
</template>
```

---

## Définition des props

### Toujours typées via TypeScript generic

```ts
// ✅ BON — type generic, inference complète, refactoring sûr
defineProps<{
  vehicle: VehicleData
  variant?: 'compact' | 'detailed'
  isSelected?: boolean
}>()

// ❌ MAUVAIS — runtime declaration, types perdus
defineProps({
  vehicle: Object,
  variant: String,
  isSelected: Boolean,
})
```

### Defaults via `withDefaults`

Quand des props ont des valeurs par défaut, utiliser **`withDefaults`** plutôt qu'un objet de validation runtime.

```ts
const props = withDefaults(defineProps<{
  variant?: 'primary' | 'secondary'
  size?: 'sm' | 'md' | 'lg'
  disabled?: boolean
}>(), {
  variant: 'primary',
  size: 'md',
  disabled: false,
})
```

### Props complexes — type alias dédié

Quand l'objet de props devient long, **extraire en type alias** au-dessus du composant pour la lisibilité.

```ts
type VehicleCardProps = {
  vehicle: VehicleData
  variant?: 'compact' | 'detailed' | 'minimal'
  showFiscalBadge?: boolean
  showAttributionsCount?: boolean
  highlightInvalidated?: boolean
}

const props = withDefaults(defineProps<VehicleCardProps>(), {
  variant: 'detailed',
  showFiscalBadge: true,
  showAttributionsCount: false,
  highlightInvalidated: false,
})
```

### Règle stricte d'immutabilité

**Les props sont readonly**. Ne **jamais** muter une prop directement :

```ts
// ❌ INTERDIT — mutation directe d'une prop
const props = defineProps<{ vehicles: VehicleData[] }>()
props.vehicles.push(newVehicle) // Vue va lever un warning, et c'est le bordel garanti

// ✅ BON — émettre vers le parent qui possède l'état
const emit = defineEmits<{ add: [vehicle: VehicleData] }>()
emit('add', newVehicle)

// ✅ BON aussi — état local dérivé
const props = defineProps<{ initialVehicles: VehicleData[] }>()
const vehicles = ref<VehicleData[]>([...props.initialVehicles])
// On peut muter `vehicles.value`, jamais `props.initialVehicles`
```

### `defineModel` (Vue 3.4+) — pour la liaison v-model bidirectionnelle

Quand un composant participe à une liaison `v-model` avec son parent, utiliser `defineModel` (sucre syntaxique pour `props` + `emit('update:modelValue')`).

```vue
<!-- Components/Ui/Input/TextInput.vue -->
<script setup lang="ts">
const modelValue = defineModel<string>({ required: true })

defineProps<{
  label: string
  invalid?: boolean
  placeholder?: string
}>()
</script>

<template>
  <label class="block">
    <span class="text-sm font-medium text-gray-700">{{ label }}</span>
    <input
      v-model="modelValue"
      type="text"
      :placeholder="placeholder"
      :class="[
        'mt-1 block w-full rounded-md border px-3 py-2 shadow-sm focus:outline-none focus:ring-2',
        invalid
          ? 'border-error focus:border-error focus:ring-error'
          : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500',
      ]"
    />
  </label>
</template>
```

```vue
<!-- Usage parent -->
<TextInput v-model="form.immatriculation" label="Immatriculation" :invalid="!!form.errors.immatriculation" />
```

> Pour les v-model multiples (`v-model:foo`, `v-model:bar`), `defineModel('foo')` et `defineModel('bar')`.

---

## Définition des emits

### Toujours typés et explicites

```ts
// ✅ BON — emits typés, payload typé
const emit = defineEmits<{
  click: [event: MouseEvent]
  selected: [vehicleId: number]
  rangeChanged: [start: Date, end: Date]
  saved: [vehicle: VehicleData]
  cancelled: []
}>()

// ❌ MAUVAIS — emits non typés
const emit = defineEmits(['click', 'selected'])
```

### Convention de nommage des emits

Les noms d'emits suivent la convention **camelCase** et sont au passé ou à l'impératif selon la sémantique :

| Pattern | Sens | Exemple |
|---|---|---|
| Verbe au passé (`saved`, `cancelled`, `selected`) | Notification d'un événement déjà arrivé | `<VehicleForm @saved="onVehicleSaved" />` |
| `update:modelValue` ou `update:xxx` | Compatible avec `v-model` | Géré par `defineModel` |
| Action impérative (`open`, `close`, `submit`) | Demande au parent de réagir | `<Modal @close="isOpen = false" />` |

### Règle anti-pattern — pas de logique métier dans l'emit handler

Le composant émet, le parent décide. Le composant ne **calcule pas** ce que le parent doit faire.

```vue
<!-- ❌ MAUVAIS — VehicleCard "sait" qu'on doit naviguer vers la fiche -->
<script setup lang="ts">
import { router } from '@inertiajs/vue3'
const handleClick = (): void => {
  router.visit(route('user.vehicles.show', { vehicle: props.vehicle.id }))
}
</script>

<!-- ✅ BON — VehicleCard émet un événement, le parent décide -->
<script setup lang="ts">
const emit = defineEmits<{ click: [vehicleId: number] }>()
const handleClick = (): void => emit('click', props.vehicle.id)
</script>
```

```vue
<!-- Parent -->
<VehicleCard
  v-for="v in vehicles"
  :key="v.id"
  :vehicle="v"
  @click="(id) => router.visit(route('user.vehicles.show', { vehicle: id }))"
/>
```

> Cette discipline rend `VehicleCard` réutilisable dans des contextes différents (modal de sélection, liste, dashboard, etc.) sans modification.

---

## Slots typés

### Slot par défaut

```vue
<!-- Components/Ui/Card/Card.vue -->
<script setup lang="ts">
defineProps<{
  title?: string
}>()

defineSlots<{
  default(props: {}): any
  header(props: { title?: string }): any
  footer(props: {}): any
}>()
</script>

<template>
  <article class="rounded-lg border border-gray-200 bg-white shadow-sm">
    <header v-if="$slots.header || title" class="border-b border-gray-200 px-4 py-3">
      <slot name="header" :title="title">
        <h3 v-if="title" class="font-semibold text-gray-900">{{ title }}</h3>
      </slot>
    </header>

    <div class="p-4">
      <slot />
    </div>

    <footer v-if="$slots.footer" class="border-t border-gray-200 px-4 py-3">
      <slot name="footer" />
    </footer>
  </article>
</template>
```

### Slots scoped (avec props passées au consommateur)

```vue
<!-- Components/Domain/Vehicle/VehicleListLayout.vue -->
<script setup lang="ts">
import type { VehicleListItemData } from '@/types'

defineProps<{ vehicles: VehicleListItemData[] }>()

defineSlots<{
  item(props: { vehicle: VehicleListItemData; index: number }): any
}>()
</script>

<template>
  <ul class="divide-y divide-gray-200">
    <li v-for="(vehicle, index) in vehicles" :key="vehicle.id">
      <slot name="item" :vehicle="vehicle" :index="index" />
    </li>
  </ul>
</template>
```

```vue
<!-- Usage avec scoped slot -->
<VehicleListLayout :vehicles="vehicles">
  <template #item="{ vehicle, index }">
    <VehicleCard :vehicle="vehicle" :data-index="index" />
  </template>
</VehicleListLayout>
```

---

## `defineExpose` — exposer des méthodes publiques

Par défaut, un composant Vue 3 expose **rien** au parent via template ref. Pour exposer explicitement une API, utiliser `defineExpose`.

```vue
<!-- Components/Ui/Modal/Modal.vue -->
<script setup lang="ts">
import { ref } from 'vue'

const isOpen = ref(false)

const open = (): void => {
  isOpen.value = true
}

const close = (): void => {
  isOpen.value = false
}

defineExpose({ open, close, isOpen })
</script>
```

```vue
<!-- Parent -->
<script setup lang="ts">
import { useTemplateRef } from 'vue'

const modalRef = useTemplateRef<{ open: () => void; close: () => void; isOpen: boolean }>('modalRef')

const showConfirmation = (): void => {
  modalRef.value?.open()
}
</script>

<template>
  <Modal ref="modalRef">…</Modal>
  <Button @click="showConfirmation">Ouvrir</Button>
</template>
```

> **Règle stricte** : `defineExpose` est réservé aux composants UI Kit qui doivent exposer un contrôle imperatif (modal, drawer, toast container). Pour 95 % des cas, **préférer le state hoisting** (laisser le parent gérer l'état d'ouverture via `v-model:open` ou prop + emit).

---

## `<script setup>` — règles d'organisation

L'ordre d'écriture dans un `<script setup>` reste cohérent à travers tous les composants Floty. Cela facilite la lecture rapide.

```vue
<script setup lang="ts">
// 1. Imports — types d'abord, puis composables, puis composants enfants
import type { VehicleData, VehicleListItemData } from '@/types'
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import { useFiscalYear } from '@/Composables/User/useFiscalYear'
import { useToast } from '@/Composables/User/useToast'
import VehicleCard from '@/Components/Domain/Vehicle/VehicleCard.vue'
import Button from '@/Components/Ui/Button/Button.vue'

// 2. defineOptions — layout, etc.
defineOptions({ layout: UserLayout })

// 3. Props
const props = defineProps<{
  vehicle: VehicleData
}>()

// 4. Emits
const emit = defineEmits<{
  saved: [vehicle: VehicleData]
  cancelled: []
}>()

// 5. defineModel (le cas échéant)
const isOpen = defineModel<boolean>('open', { default: false })

// 6. Composables externes
const { year } = useFiscalYear()
const { pushToast } = useToast()
const page = usePage()

// 7. État local (refs, reactive)
const isEditing = ref(false)
const draftNote = ref('')

// 8. Computed
const fullDisplayName = computed(() => `${props.vehicle.marque} ${props.vehicle.modele}`)

// 9. Watch
watch(year, (newYear, oldYear) => {
  console.log(`Année passée de ${oldYear} à ${newYear}`)
})

// 10. Méthodes
const startEditing = (): void => {
  isEditing.value = true
  draftNote.value = props.vehicle.notes ?? ''
}

const handleSave = (): void => {
  emit('saved', { ...props.vehicle, notes: draftNote.value })
  isEditing.value = false
}

// 11. Lifecycle hooks
onMounted(() => { /* ... */ })
onBeforeUnmount(() => { /* ... */ })
</script>
```

> Cet ordre n'est pas dogmatique au point près, mais il offre une lecture **prédictive**. Un reviewer sait quoi chercher où.

---

## Réactivité — `ref`, `reactive`, `shallowRef`, `readonly`

### `ref` — réactivité standard

```ts
const count = ref<number>(0)
count.value++ // accès via .value
```

### `reactive` — pour les objets uniquement

```ts
const state = reactive({ count: 0, items: [] })
state.count++ // accès direct, pas de .value
```

> **Règle Floty** : préférer `ref` partout (même pour les objets). Cohérence d'API, pas de surprise sur les destructurations qui cassent la réactivité.

### `shallowRef` — pour les grosses structures

Quand un objet est très volumineux et qu'on n'a besoin de réactivité **que sur la référence** (pas sur les propriétés internes), utiliser `shallowRef`.

```ts
// État avec 5000 attributions — on remplace l'array entier, jamais ses éléments
const attributions = shallowRef<AssignmentData[]>([])

// Mise à jour → réactive
attributions.value = newArray

// Mutation interne → non réactive (on ne le fait pas, c'est le but)
attributions.value.push(item) // n'émet pas de re-render
```

**Cas d'usage Floty** : la heatmap (100 véhicules × 52 semaines = 5 200 cellules), la saisie hebdomadaire en mode tableur.

### `readonly` — pour passer un état en lecture seule

```ts
const internalState = ref({ count: 0 })
const publicState = readonly(internalState)

defineExpose({ state: publicState })
```

### `triggerRef` — forcer la réactivité d'un `shallowRef` après mutation interne

```ts
assignments.value[0].company = newCompany // mutation interne, non réactive
triggerRef(attributions) // force le re-render
```

À utiliser avec parcimonie — généralement signe qu'il vaut mieux remplacer l'array entier.

---

## Watchers — `watch`, `watchEffect`, `watchPostEffect`

### Règles d'usage

| Cas | API |
|---|---|
| Réagir à un changement de valeur précis avec accès à l'ancienne valeur | `watch(source, (new, old) => ...)` |
| Effet automatique qui dépend de plusieurs sources réactives | `watchEffect(() => ...)` |
| Effet qui doit s'exécuter **après** la mise à jour DOM (rare) | `watchPostEffect` ou `watch(..., { flush: 'post' })` |

### Bonnes pratiques

```ts
// ✅ BON — sources explicites, type checking
watch(year, (newYear, oldYear) => { /* ... */ })

// ✅ BON — multiples sources
watch([year, fiscalRule], ([newYear, newRule]) => { /* ... */ })

// ✅ BON — getter pour réactivité fine
watch(() => props.vehicle.id, (newId) => { /* ... */ })

// ❌ MAUVAIS — on watch un objet entier alors qu'on n'a besoin que d'un champ
watch(props.vehicle, () => { /* ... */ }, { deep: true })

// ❌ MAUVAIS — pas de cleanup pour un setInterval
watch(year, () => {
  setInterval(() => { /* ... */ }, 1000) // fuite de timer
})
```

### Cleanup obligatoire

Tout effet qui crée une ressource (timer, listener, requête, abonnement) **doit** la nettoyer :

```ts
watch(year, (newYear, oldYear, onCleanup) => {
  const intervalId = setInterval(refresh, 1000)
  onCleanup(() => clearInterval(intervalId))
})

// Ou via lifecycle
let intervalId: ReturnType<typeof setInterval>
onMounted(() => {
  intervalId = setInterval(refresh, 1000)
})
onBeforeUnmount(() => {
  clearInterval(intervalId)
})
```

---

## Computed — règles d'usage

### Pure et idempotent

Un `computed` doit être **pur** : pas de side effect, pas d'appel API, pas de mutation. Il calcule à partir de ses dépendances réactives.

```ts
// ✅ BON — pur
const formattedTotal = computed(() => formatEuro(props.declaration.totalTaxeAll))

// ❌ MAUVAIS — side effect dans un computed
const taxesTotal = computed(() => {
  console.log('Calcul...') // OK pour debug, sinon NON
  axios.post('/log', { ... }) // INTERDIT
  return props.declaration.totalTaxeAll
})
```

### Computed writable (rare)

```ts
const fullName = computed<string>({
  get: () => `${firstName.value} ${lastName.value}`,
  set: (value) => {
    const [first, ...rest] = value.split(' ')
    firstName.value = first
    lastName.value = rest.join(' ')
  },
})
```

> **Règle Floty** : computed writable réservé aux cas où c'est vraiment plus clair que deux fonctions. La plupart du temps, préférer `(get, set)` explicites.

### Performance — éviter les computed lourds qui se recalculent à chaque dépendance

Un computed qui dépend d'une grosse structure et fait un calcul O(n²) sera recalculé à chaque modification d'un élément. Solutions :

- Réduire les dépendances (ne pas dépendre de l'array entier si on a besoin d'un seul champ).
- Utiliser `shallowRef` quand c'est pertinent.
- Mémoizer manuellement avec un Map si le calcul est très lourd.

> Le détail des optimisations sera couvert dans `performance-ui.md`.

---

## Lifecycle hooks — usage

| Hook | Usage Floty typique |
|---|---|
| `onBeforeMount` | Très rare. Préfèrer `setup` direct. |
| `onMounted` | Initialiser un effet qui dépend du DOM (focus auto, libs DOM tierces, mesures). |
| `onBeforeUpdate` | Capturer l'état pré-update (rare). |
| `onUpdated` | Réagir après mise à jour DOM (rare). |
| `onBeforeUnmount` | **Cleanup obligatoire** : timers, listeners, abonnements. |
| `onUnmounted` | Pareil que above mais **après** détachement du DOM. Préfèrer `onBeforeUnmount`. |
| `onActivated` / `onDeactivated` | Pour `<KeepAlive>` (rare en Floty). |
| `onErrorCaptured` | Capturer une erreur d'un composant enfant (à utiliser dans un Error Boundary local si besoin). |

---

## Composants — taille et découpage

### Règles de seuil

| Lignes | Statut |
|---|---|
| < 100 | Idéal |
| 100-200 | Confortable |
| 200-300 | Acceptable, surveiller |
| 300-400 | À découper sauf justification |
| > 400 | **Découpe obligatoire** en partials ou sous-composants |

### Critères de découpage

Un composant doit être découpé quand :

- Il fait visuellement **plusieurs choses** (header + table + sidebar + footer dans le même fichier).
- Plusieurs **états indépendants** cohabitent (ex: état d'édition + état de filtres + état de pagination).
- Plusieurs **responsabilités** se mélangent (ex: rendu d'une carte + logique de drag&drop + animation d'apparition).

### Stratégie de découpage Floty

1. **Partials** dans `Pages/{Espace}/{Domaine}/{PageName}/Partials/` quand le partial sert **uniquement** cette page (cf. `structure-fichiers.md`).
2. **Components/Domain/{Domaine}/** quand le partial devient réutilisable sur plusieurs pages.
3. **Components/Ui/** quand le composant est générique (pas de logique métier).
4. **Composables** quand on a de la logique réactive partagée (cf. `composables-services-utils.md`).

---

## Accessibilité (a11y) — exigences senior+

L'accessibilité n'est **pas optionnelle** sur un projet senior. Elle s'intègre dès le composant.

### Règles minimales pour tout composant interactif

| Élément | Exigence |
|---|---|
| Bouton | `<button>` (pas `<div @click>`), `type` explicite (`button`, `submit`, `reset`) |
| Lien de navigation | `<Link>` Inertia ou `<a>` avec `href` (pas `<button @click="router.visit">`) |
| Input formulaire | `<label>` associé via `for` ou en wrappant l'input |
| Image décorative | `alt=""` (chaîne vide) |
| Image porteuse de sens | `alt="description en français"` |
| Modal / drawer | Focus trap, escape pour fermer, restauration du focus à la fermeture |
| Liste | `<ul>` / `<ol>` + `<li>` (pas `<div>` empilés) |
| Élément interactif custom | `role`, `aria-*`, `tabindex="0"` |
| Disabled visuel mais accessible | `disabled` HTML (pas seulement classe CSS) |
| États dynamiques (loading, error) | `aria-live="polite"` ou `role="status"` |

### Pattern Floty — formulaire accessible

```vue
<script setup lang="ts">
const id = `email-${useId()}`
defineProps<{ label: string; error?: string }>()
const modelValue = defineModel<string>({ required: true })
</script>

<template>
  <div>
    <label :for="id" class="block text-sm font-medium text-gray-700">{{ label }}</label>
    <input
      :id="id"
      v-model="modelValue"
      type="email"
      :aria-invalid="!!error"
      :aria-describedby="error ? `${id}-error` : undefined"
      class="mt-1 block w-full rounded-md border-gray-300 focus:border-primary-500 focus:ring-primary-500"
    />
    <p v-if="error" :id="`${id}-error`" class="mt-1.5 text-sm text-error" role="alert">
      {{ error }}
    </p>
  </div>
</template>
```

### Pattern Floty — modal accessible

```vue
<script setup lang="ts">
import { ref, watch, nextTick } from 'vue'
import { onClickOutside, useFocusTrap } from '@vueuse/core' // ou implémentation maison

const isOpen = defineModel<boolean>({ required: true })
const dialogRef = ref<HTMLElement | null>(null)
const previouslyFocusedElement = ref<HTMLElement | null>(null)

watch(isOpen, async (open) => {
  if (open) {
    previouslyFocusedElement.value = document.activeElement as HTMLElement
    await nextTick()
    dialogRef.value?.focus()
  } else {
    previouslyFocusedElement.value?.focus()
  }
})

const handleEscape = (event: KeyboardEvent): void => {
  if (event.key === 'Escape' && isOpen.value) {
    isOpen.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="isOpen"
        ref="dialogRef"
        role="dialog"
        aria-modal="true"
        tabindex="-1"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        @keydown="handleEscape"
        @click.self="isOpen = false"
      >
        <div class="rounded-lg bg-white p-6 shadow-xl max-w-lg w-full">
          <slot />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
```

---

## Anti-patterns à proscrire (repérés en revue senior)

### Sur la déclaration

| Anti-pattern | Correction |
|---|---|
| Options API (`export default { data, methods, computed }`) | Composition API stricte avec `<script setup>` |
| `<script>` sans `lang="ts"` | Toujours `<script setup lang="ts">` |
| Props non typées (`defineProps(['vehicles'])`) | Generics TS : `defineProps<{ vehicles: VehicleData[] }>()` |
| Emits non typés (`defineEmits(['click'])`) | `defineEmits<{ click: [event: MouseEvent] }>()` |
| Composant mono-mot (`<button />` créé par dev) | PascalCase multi-mot (`<UiButton />`) |
| Variables internes au-dessus du `defineProps` | Ordre fixe : imports, defineOptions, defineProps, defineEmits, defineModel, composables, état, computed, watch, methods, lifecycle |

### Sur la réactivité

| Anti-pattern | Correction |
|---|---|
| Mutation directe d'une prop | Émettre un event au parent |
| Destructurer un `reactive` (perte de réactivité) | `toRefs(state)` ou utiliser `ref` |
| `watch(deep: true)` sur une grosse structure | Watch sur un getter précis : `watch(() => props.vehicle.id, ...)` |
| `setInterval` sans cleanup | Toujours `onBeforeUnmount` ou `onCleanup` |
| `addEventListener('window', ...)` sans removal | Idem, cleanup obligatoire |
| Computed avec side effect | Computed toujours pur |
| `ref` pour une lib tierce non réactive (Chart.js, etc.) | `shallowRef` ou `markRaw` |

### Sur le rendu

| Anti-pattern | Correction |
|---|---|
| `v-for` + `v-if` sur le même élément | Filter via computed avant le `v-for` |
| `key="index"` sur un v-for | `key="item.id"` toujours, `index` est instable |
| `v-html` sans sanitisation | **Interdit** sauf cas vérifié explicitement (XSS garanti sinon) |
| `inline-style` au lieu de classes Tailwind | Classes Tailwind, sauf valeurs vraiment dynamiques |
| `<div @click>` au lieu de `<button>` | `<button>` pour tout ce qui est interactif (a11y) |
| `<a href="javascript:void(0)" @click>` | `<button>` ou `<Link>` Inertia |

### Sur l'organisation

| Anti-pattern | Correction |
|---|---|
| Logique métier dans un composant Vue (calcul fiscal, validation business) | Délégué au composable, service ou backend |
| Appel `axios.get` direct dans un composant | Inertia (`router.get`) ou composable dédié |
| Composant > 400 lignes | Découper en partials ou sous-composants |
| Mélange Setup + Options API | Composition API stricte uniquement |
| `import 'jquery'` ou autre lib Vue 2-era | Composition API native ou VueUse |
| State global pour un état purement local | Refs ou composable local |

---

## Cas particulier — Composant page Inertia

Une page Inertia (`Pages/.../{PageName}/{PageName}.vue`) a quelques spécificités :

1. **Reçoit des props** depuis le controller via `Inertia::render`. Ces props sont **typées via Spatie Data**.
2. **Définit son layout** via `defineOptions({ layout: ... })`.
3. **Ne déclare pas d'emits** (rien ne l'écoute — c'est une page).
4. **Orchestre les partials** via `import` et composition dans le `<template>`.
5. **Peut utiliser `useForm`**, `usePage`, `useRemember` de Inertia.

> Détails sur la navigation, `useForm`, `useRemember`, partial reloads : voir `inertia-navigation.md`.

---

## Cas particulier — Composant UI Kit

Les composants `Components/Ui/*.vue` constituent l'**UI Kit Floty** custom. Règles spécifiques :

1. **Aucune dépendance métier** (pas d'`import` de Vehicle, Company, etc.).
2. **Aucune dépendance Inertia** sauf composants explicitement dédiés (ex: `<Link>` réexporté avec style Floty).
3. **Aucune dépendance store global** (pas de Pinia).
4. **API publique stable** : props, emits, slots documentés (au minimum via TS).
5. **Variantes via props typées** (variant, size, etc.).
6. **Accessibilité maximale** (focus visible, aria, keyboard).
7. **Tests** systématiques (cf. `tests-frontend.md`).

---

## Cas particulier — Composant Domain réutilisable

Les composants `Components/Domain/{Domaine}/*.vue` :

1. **Connaissent les types métier** (`VehicleData`, etc.) via Spatie Data.
2. **Réutilisables sur plusieurs pages** (s'ils sont sur une seule, ils restent en partial).
3. **Pas de side effects globaux** (pas de mutation Pinia, pas de navigation Inertia directe — émettre vers le parent qui décide).
4. **Composables locaux possibles** mais pas indispensables.

---

## Checklist — avant de considérer un composant comme « terminé »

- [ ] `<script setup lang="ts">` utilisé.
- [ ] Imports ordonnés (types, composables, composants enfants).
- [ ] Props typées via generic TypeScript, defaults via `withDefaults` si besoin.
- [ ] Emits typés explicitement.
- [ ] Slots typés via `defineSlots` si pertinent.
- [ ] Aucune mutation de prop (vérification visuelle).
- [ ] Computed purs (pas de side effects).
- [ ] Watchers avec cleanup approprié.
- [ ] Lifecycle hooks utilisés à bon escient.
- [ ] Composant sous 400 lignes (découpage si dépassement).
- [ ] Accessibilité respectée (`<button>`, `<label>`, `aria-*`, focus management).
- [ ] Pas de logique métier (calcul fiscal, validation business) — délégué.
- [ ] Pas d'`any` ni de cast forcé.
- [ ] Imports de types via `@/types` (ré-export Spatie Data).
- [ ] Si UI Kit : aucune dépendance métier ni Inertia ni store.
- [ ] Si Domain : aucun side effect global.
- [ ] Test `.spec.ts` adjacent (cf. `tests-frontend.md`).

---

## Cohérence avec les autres règles

- **Architecture en couches** (composant Vue = couche présentation client) : voir `architecture-solid.md`.
- **TypeScript et DTO** (typage des props, ré-exports) : voir `typescript-dto.md`.
- **Navigation Inertia** (routes, useForm, partial reloads) : voir `inertia-navigation.md`.
- **Composables, services, utils** (logique réactive partagée) : voir `composables-services-utils.md`.
- **Stores Pinia** (état cross-page, quand l'utiliser) : voir `pinia-stores.md`.
- **Performance UI** (memoization, virtualisation, anti-patterns) : voir `performance-ui.md`.
- **Tests frontend** (`.spec.ts` adjacents, fixtures typées) : voir `tests-frontend.md`.
- **Conventions de nommage** (PascalCase.vue, camelCase props/emits) : voir `conventions-nommage.md`.
- **Structure des fichiers** (Pages avec Partials, Components Ui/Domain/Layouts) : voir `structure-fichiers.md`.
- **Bundling Vite** (3 mécanismes CSS, `<style scoped>` quand) : voir `assets-vite.md`.

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 1.0 | 24/04/2026 | Micha MEGRET | Rédaction initiale — Composition API stricte, props/emits/slots typés, defineModel, defineExpose, ordre `<script setup>`, réactivité (ref/reactive/shallowRef/readonly), watchers avec cleanup, computed purs, lifecycle hooks, accessibilité (formulaires, modals), seuils de taille (400 lignes), anti-patterns repérés en revue senior (déclaration, réactivité, rendu, organisation), spécificités page Inertia / UI Kit / Domain, checklist, exemples Floty (Button, TextInput, Modal). |
