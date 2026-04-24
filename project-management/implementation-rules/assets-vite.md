# Gestion des assets avec Vite

> **Stack référence** : Vite 8 (Rolldown), Inertia v3, Vue 3.5, TypeScript 6, Tailwind CSS 4.2, PHP 8.5, Laravel 13.
> **Hébergement** : Hostinger Business mutualisé — **pas de Node.js en SSH**, build local + push assets compilés via Git.
> **Niveau d'exigence** : senior +.
> **Documents liés** : `architecture-solid.md`, `structure-fichiers.md`, `conventions-nommage.md`, `gestion-erreurs.md`.

---

## Principe directeur

Le frontend Floty est une **SPA Inertia** servie par Laravel. L'architecture des assets repose sur **deux points d'entrée principaux** :

- **JavaScript** : `resources/js/app.ts` (entrée unique, Vite gère ensuite le code splitting **automatique par page Inertia** via `import.meta.glob`).
- **CSS** : `resources/css/app.css` (entrée principale qui porte les tokens du design system, la base globale, et les composants globaux).

**Au-delà de ces deux entrées principales**, le projet adopte un **modèle hybride pragmatique** pour le CSS : Tailwind utility-first par défaut, fichiers CSS dédiés segmentés quand le besoin réel le justifie, `<style scoped>` pour les cas vraiment locaux. Le détail dans la section « Les trois mécanismes CSS et leur usage » plus bas.

C'est la nature d'Inertia + Vite qui dicte le modèle JS :

- **Inertia v3** charge dynamiquement les composants de page via `import.meta.glob` (chargement paresseux par page natif).
- **Vite + Rolldown (v8)** produit des chunks optimisés pour ce pattern, avec hash de cache, splitting des dépendances tierces (Vue, Pinia, etc.) en chunks séparés.
- **Tailwind CSS 4** purge automatiquement les classes inutilisées au build à partir du scan de tous les fichiers `.vue`/`.ts`/`.css`.

> **Différence majeure avec l'ancien stack Blade + Livewire + Alpine** : il n'y a **plus** de bundles JS séparés par zone (`web.js`, `auth.js`, `app.js`, `ui-kit.js`). Un seul bundle JS principal, et Vite/Inertia s'occupent du splitting. Côté CSS en revanche, la segmentation par fichiers dédiés reste **autorisée et utile** quand elle apporte de la maintenabilité — voir le modèle hybride détaillé plus bas.

> **Principe directeur** : les règles ont une raison d'exister — rendre les choses plus maintenables, plus évolutives, plus performantes. Pas de dogmatisme. Si un fichier CSS dédié rend un composant plus clair ou des styles plus partageables, on le crée. Si Tailwind utility suffit, on n'invente pas un fichier inutile.

---

## Architecture des entrées

### Entrée JavaScript unique — `resources/js/app.ts`

```ts
// resources/js/app.ts
import { createInertiaApp } from '@inertiajs/vue3'
import { createApp, h, type DefineComponent } from 'vue'
import { createPinia } from 'pinia'
import UserLayout from '@/Components/Layouts/UserLayout.vue'
import WebLayout from '@/Components/Layouts/WebLayout.vue'
import '../css/app.css'

const appName = import.meta.env.VITE_APP_NAME ?? 'Floty'

createInertiaApp({
  title: (title) => `${title} — ${appName}`,

  resolve: (name) => {
    const pages = import.meta.glob<DefineComponent>('./Pages/**/*.vue')
    const page = resolvePageComponent(name, pages)

    // Layout par défaut selon l'espace
    page.then((module) => {
      module.default.layout ??= name.startsWith('User/') ? UserLayout : WebLayout
    })

    return page
  },

  setup: ({ el, App, props, plugin }) => {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(createPinia())
      .mount(el)
  },

  progress: {
    color: '#…', // couleur primaire du design system Floty
    showSpinner: false,
  },
})
```

### Entrée CSS unique — `resources/css/app.css`

```css
/* resources/css/app.css */

/* Tailwind 4 — config CSS-first */
@import 'tailwindcss';

/* Tokens du design system Floty (à traduire depuis project-management/design-system/) */
@theme {
  --color-primary: …;
  --color-secondary: …;
  --color-success: …;
  --color-warning: …;
  --color-error: …;

  --font-sans: 'Inter', system-ui, sans-serif;
  --font-mono: 'JetBrains Mono', monospace;

  --radius-sm: …;
  --radius-md: …;
  --radius-lg: …;

  /* etc. — tous les tokens issus du design system */
}

/* Styles globaux applicatifs (très restreints — éviter au max) */
@layer base {
  /* reset, typo de base, focus rings accessibles, etc. */
}

/* Surcharges de composants tiers (Inertia progress bar, etc.) */
@layer components {
  /* … */
}
```

> **Règle stricte** : **pas de fichier CSS par page**. Le design Floty est cohérent entre toutes les pages, géré par Tailwind 4 + tokens du design system. Les écarts éventuels (page d'erreur, login) restent dans `app.css` via `@layer` ou utilisent des classes Tailwind directement dans le template Vue.

---

## Configuration Vite 8 — `vite.config.ts`

```ts
// vite.config.ts
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'
import path from 'node:path'

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.ts'],
      ssr: 'resources/js/ssr.ts', // optionnel, cf. § SSR
      refresh: true,
    }),
    vue({
      template: {
        transformAssetUrls: {
          base: null,
          includeAbsolute: false,
        },
      },
    }),
    tailwindcss(),
  ],

  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'resources/js'),
      '@css': path.resolve(__dirname, 'resources/css'),
    },
  },

  build: {
    // Vite 8 utilise Rolldown par défaut — bundler Rust intégré
    target: 'es2022',
    sourcemap: false, // pas de sourcemap en production
    rollupOptions: {
      output: {
        manualChunks: {
          // Bundle des dépendances tierces stables séparé pour cache long
          vendor: ['vue', 'pinia', '@inertiajs/vue3'],
        },
      },
    },
  },

  server: {
    hmr: {
      host: 'localhost',
    },
  },
})
```

### Points clés de la config

| Option | Pourquoi |
|---|---|
| `input: ['css/app.css', 'js/app.ts']` | Deux entrées top-level, point. Plus de listing exhaustif des pages — Vite découvre via `import.meta.glob` côté Inertia |
| `@vitejs/plugin-vue` | Plugin officiel Vue 3 pour les SFC (`.vue`) |
| `@tailwindcss/vite` | Plugin Tailwind 4 (CSS-first, sans `postcss.config.js`) |
| `laravel-vite-plugin` | Intégration Laravel : `@vite()` Blade, hot reload, manifest |
| `manualChunks: { vendor: [...] }` | Sépare les dépendances stables (Vue, Pinia, Inertia) en un chunk cacheable longuement |
| `target: 'es2022'` | Cible navigateurs modernes (Floty est B2B, pas de support legacy à prévoir) |
| `sourcemap: false` | En production, pas de sourcemap (taille + sécurité). Optionnel pour debug staging. |

---

## Code splitting — comportement automatique

Avec `import.meta.glob` côté Inertia + Vite, **chaque page devient un chunk séparé** chargé uniquement quand l'utilisateur navigue dessus.

Exemple — l'utilisateur va sur `/vehicles` :

1. Vite a buildé `Pages/User/Vehicles/Index/Index.vue` en `assets/Index-{hash}.js`.
2. Inertia importe ce chunk dynamiquement.
3. Les partials (`Pages/User/Vehicles/Index/Partials/*.vue`) sont **inlinés dans le même chunk** par défaut (ils sont importés statiquement par la page).
4. Les composants `Components/Domain/Vehicle/VehicleCard.vue`, `Components/Ui/Button.vue` apparaissent dans des chunks partagés (`vendor` ou `common`).

Le résultat : **chargement initial léger**, navigation rapide après le premier chargement (chunks mis en cache navigateur).

### Quand NE PAS faire de lazy loading manuel

> **Anti-pattern fréquent** repéré en revue senior : ajouter `defineAsyncComponent` ou `() => import(...)` partout pour tenter de « gagner en performance ».

**Règle Floty** :

- **Ne jamais** utiliser `defineAsyncComponent` pour des composants utilisés à l'intérieur d'une page Inertia. Ces composants font partie du chunk de la page, ils sont déjà chargés au moment de l'affichage.
- **Pas de skeleton + lazy-loading** sur les composants. Le skeleton est une UX qui ne sert que si le composant met > 200 ms à s'afficher (rare en SPA après chunk loadé). Avant Floty V2, **ne pas en mettre**.
- **Pas de `<Suspense>` enveloppant chaque section**. C'est une couche de complexité et de bug potentiel pour zéro bénéfice perceptible sur notre profil d'app.

> **Pourquoi cette règle stricte** : les pièges classiques du skeleton + lazy loading sont :
>
> - Le skeleton flash très brièvement avant que le contenu n'apparaisse → effet de papillotement perçu négativement par l'utilisateur.
> - Les composants lazy-loadés introduisent des transitions désynchronisées (un partial apparaît avant un autre).
> - Les chunks supplémentaires multiplient les requêtes HTTP et la complexité du graph de dépendances.
> - Le SEO (non pertinent pour Floty B2B derrière login) ou le crawling deviennent imprévisibles.
> - Le coût de maintenance des `Suspense`/`fallback`/`error` est élevé pour zéro bénéfice mesurable.

> Le détail des règles de performance UI sera couvert dans `performance-ui.md` (étape 5.4).

---

## Tailwind CSS 4 — modèle CSS-first

Tailwind 4 abandonne `tailwind.config.js` au profit d'une déclaration **CSS-first** dans `app.css` via la directive `@theme`. Le design system Floty est intégré directement comme tokens CSS custom properties.

### Structure des tokens du design system

```css
/* resources/css/app.css */
@import 'tailwindcss';

@theme {
  /* Couleurs */
  --color-primary-50:  …;
  --color-primary-100: …;
  --color-primary-500: …;
  --color-primary-900: …;

  --color-secondary-…: …;

  --color-success: …;
  --color-warning: …;
  --color-error: …;
  --color-info: …;

  /* Typographie */
  --font-sans: 'Inter', system-ui, sans-serif;
  --font-mono: 'JetBrains Mono', 'Fira Code', monospace;

  --text-xs:  0.75rem;
  --text-sm:  0.875rem;
  --text-base: 1rem;
  /* … */

  /* Espacements (Tailwind par défaut suffit dans 90% des cas) */

  /* Rayons */
  --radius-sm: 0.25rem;
  --radius-md: 0.5rem;
  --radius-lg: 1rem;
  --radius-full: 9999px;

  /* Ombres */
  --shadow-sm: …;
  --shadow-md: …;
  --shadow-lg: …;

  /* Transitions */
  --duration-fast: 150ms;
  --duration-normal: 250ms;
  --duration-slow: 400ms;

  /* Tokens spécifiques Floty */
  --color-vp:  …;     /* badge VP / véhicule particulier */
  --color-vu:  …;     /* badge VU / véhicule utilitaire */
  --color-density-0: …;
  --color-density-1: …;
  --color-density-2: …;
  --color-density-3: …;
  --color-density-4: …;
}
```

> **Travail à faire au démarrage** : traduire les tokens du design system Floty (`project-management/design-system/`) en `@theme` Tailwind 4. C'est un travail substantiel de fondation et critique pour la cohérence visuelle.

### Composants UI Kit Floty — usage des tokens

Les composants `Components/Ui/*.vue` utilisent les classes Tailwind générées depuis les tokens. Pas de classes magiques, pas de `@apply` inline dans des fichiers `.css` séparés (sauf cas très spécifiques documentés).

```vue
<!-- resources/js/Components/Ui/Button/Button.vue -->
<script setup lang="ts">
type Variant = 'primary' | 'secondary' | 'ghost' | 'danger'
type Size = 'sm' | 'md' | 'lg'

const props = withDefaults(defineProps<{
  variant?: Variant
  size?: Size
  disabled?: boolean
}>(), {
  variant: 'primary',
  size: 'md',
  disabled: false,
})

const variantClasses: Record<Variant, string> = {
  primary: 'bg-primary-600 hover:bg-primary-700 text-white',
  secondary: 'bg-gray-100 hover:bg-gray-200 text-gray-900',
  ghost: 'bg-transparent hover:bg-gray-100 text-gray-700',
  danger: 'bg-error hover:bg-error/90 text-white',
}

const sizeClasses: Record<Size, string> = {
  sm: 'px-3 py-1.5 text-sm rounded-md',
  md: 'px-4 py-2 text-base rounded-md',
  lg: 'px-6 py-3 text-lg rounded-lg',
}
</script>

<template>
  <button
    type="button"
    :disabled="disabled"
    :class="[
      'inline-flex items-center justify-center font-medium transition-colors',
      'focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2',
      'disabled:opacity-50 disabled:cursor-not-allowed',
      variantClasses[variant],
      sizeClasses[size],
    ]"
  >
    <slot />
  </button>
</template>
```

---

## Les trois mécanismes CSS et leur usage — modèle hybride pragmatique

Floty adopte **trois mécanismes CSS coexistants**, chacun avec son périmètre d'application optimal. Le développeur choisit le mécanisme le plus adapté **cas par cas**, en s'appuyant sur les critères ci-dessous.

### Mécanisme 1 — Classes Tailwind utility dans le template (par défaut)

**Quand l'utiliser** : 90 % des cas. C'est le réflexe par défaut.

```vue
<template>
  <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:shadow-md transition-shadow">
    <h3 class="text-lg font-semibold text-gray-900">{{ vehicle.modele }}</h3>
    <p class="mt-1 text-sm text-gray-500">{{ vehicle.immatriculation }}</p>
  </article>
</template>
```

**Avantages** :

- Pas de duplication CSS → le bundle reste minimal (Tailwind purge ce qui n'est pas utilisé).
- Co-localisation : on voit le style à côté du markup.
- Pas de gestion de noms de classes, pas de risque de collision.
- Refactoring trivial (renommer un composant n'invalide aucune feuille de style).

**Limites** :

- Verbose pour les composants à beaucoup d'états visuels (privilégier alors un composant Vue dédié qui encapsule les variantes via props).
- Pas adapté aux animations complexes ni aux surcharges de composants tiers.

### Mécanisme 2 — Fichier `.css` dédié, segmenté par espace/domaine/composant

**Quand l'utiliser** :

- **Animations complexes** : keyframes, transitions multi-étapes (ex: animation du compteur LCD qui s'incrémente).
- **Palettes spécifiques** : variables CSS dynamiques propres à un domaine (ex: les 5 niveaux de densité de la heatmap : `--color-density-0` à `--color-density-4`).
- **Surcharges de bibliothèques tierces** : date picker, charting (Chart.js si utilisé), surcharge de classes générées par une lib externe.
- **Styles d'impression PDF** : `@media print` spécifique à la page de prévisualisation déclaration.
- **Styles partagés non triviaux** entre plusieurs composants d'un même domaine.
- **Surcharge fine d'un composant Tailwind** quand `@apply` ou bindings de classes deviendraient illisibles.

**Emplacement** :

```
resources/css/
├── app.css                                          ← entrée principale (tokens, base, composants globaux)
├── User/
│   ├── Planning/
│   │   ├── heatmap.css                              ← variables densités, animations grille
│   │   └── weekly-entry.css                         ← styles de sélection multi-cellules
│   └── Declarations/
│       └── pdf-preview.css                          ← @media print pour aperçu PDF
├── Web/
│   └── Home/
│       └── hero-animation.css                       ← keyframes spécifiques hero
└── Shared/
    └── overrides/
        └── chart-tippy.css                          ← surcharge librairie tooltip si utilisée
```

**Import** : deux options selon le besoin.

```ts
// Option A — import dans le composant qui en a besoin (chunk lié au composant)
// resources/js/Pages/User/Planning/Heatmap/Heatmap.vue
<script setup lang="ts">
import '@css/User/Planning/heatmap.css'
// ...
</script>
```

```css
/* Option B — import dans app.css quand le style est globalement utile */
/* resources/css/app.css */
@import 'tailwindcss';
@import './Shared/overrides/chart-tippy.css';
@theme { /* ... */ }
```

**Découverte automatique via glob** (alternative pour les projets qui veulent un splitting CSS ultra-précis) : non utilisée par défaut en Floty V1. À évaluer si on a un volume significatif de CSS dédié (probablement V1.x ou V2). Référence pour mémoire :

```ts
// vite.config.ts (extrait — non activé par défaut en V1)
import { glob } from 'glob'
laravel({
  input: [
    'resources/css/app.css',
    'resources/js/app.ts',
    ...glob.sync('resources/css/{User,Web,Shared}/**/*.css'),
  ],
  refresh: true,
}),
```

**Avantages** :

- Séparation claire markup / style pour les zones complexes.
- Réutilisable par plusieurs composants sans duplication.
- Designer peut intervenir sans toucher au code Vue.
- Cohérent avec la philosophie de segmentation Floty (`{Espace}/{Domaine}/`).

**Limites** :

- Discipline requise pour éviter la dispersion incontrôlée (cf. anti-patterns plus bas).
- Spécificité CSS à surveiller (privilégier sélecteurs plats).

### Mécanisme 3 — `<style scoped>` dans le composant Vue

**Quand l'utiliser** :

- Composant **vraiment isolé** dont le style ne sera jamais réutilisé ailleurs.
- Composants **UI Kit** (`Components/Ui/`) qui ont une logique de variantes complexe à exprimer en CSS (animations internes, transitions de hover sophistiquées).
- **Encapsulation forte** : on ne veut absolument pas qu'un style fuie vers d'autres composants.

```vue
<!-- Components/Ui/Drawer/Drawer.vue -->
<script setup lang="ts">
import { ref } from 'vue'
const isOpen = ref(false)
</script>

<template>
  <div class="drawer" :class="{ 'drawer--open': isOpen }">
    <slot />
  </div>
</template>

<style scoped>
.drawer {
  transform: translateX(100%);
  transition: transform 250ms cubic-bezier(0.4, 0, 0.2, 1);
}

.drawer--open {
  transform: translateX(0);
}

.drawer:has(:deep(.drawer-content--scrolled)) {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}
</style>
```

**Comment Vite traite `<style scoped>`** :

- Vue ajoute un attribut `data-v-{hash}` aux éléments + au sélecteur CSS pour isoler à l'instance.
- Vite extrait les styles dans un module CSS bundlé avec le chunk JS du composant (`cssCodeSplit: true` par défaut).
- Si le composant n'est pas chargé, son CSS n'est pas chargé.
- Hash unique : modifier un `<style scoped>` n'invalide qu'un seul chunk côté cache navigateur.

**Limites** :

- Si la même règle CSS apparaît scoped dans 5 composants, elle est **dupliquée 5 fois** (chaque scope est unique).
- Sélecteurs deep (`:deep()`) un peu fragiles avec composants tiers.
- Pas de partage explicite : ce n'est pas la bonne réponse pour un style réutilisé.

### Critères de décision

| Situation | Mécanisme préféré |
|---|---|
| Style simple ou composant peu complexe | **1** — Tailwind utility dans le template |
| Variantes répétées sur plusieurs instances | **1** — Composant Vue dédié avec props + classes Tailwind dynamiques |
| Animation complexe spécifique à un domaine | **2** — fichier `.css` dédié (`User/Planning/heatmap.css`) |
| Variables CSS dynamiques (densités, palettes métier) | **2** — fichier `.css` dédié |
| Surcharge de bibliothèque tierce | **2** — fichier `.css` dans `Shared/overrides/` |
| Styles d'impression `@media print` | **2** — fichier `.css` dédié |
| Style très spécifique à un composant UI Kit isolé | **3** — `<style scoped>` |
| Composant complexe avec interactions visuelles internes (drawer, modal animé) | **3** — `<style scoped>` |

### Règles de discipline (s'appliquent aux 3 mécanismes)

| Règle | Pourquoi |
|---|---|
| **Tokens du design system uniquement dans `app.css`** (`@theme`) | Source de vérité unique pour les couleurs, tailles, etc. Pas de redéfinition ailleurs. |
| **Pas d'`@apply` répétée à l'identique dans plusieurs fichiers** | Si une combinaison de classes apparaît 3 fois, refactor en composant Vue. |
| **Pas de spécificité accumulée** (`.page .section .item .child`) | Préférer sélecteurs plats. Tailwind utility a toujours spécificité de 1. |
| **CSS partagé clairement identifié** | Si un style sert à plusieurs pages, il vit dans `Shared/` ou dans `app.css`, pas caché dans une page. |
| **Surcharges de libs externes mutualisées** | Une lib externe + sa surcharge = même fichier (`Shared/overrides/{lib}.css`), importé partout où on charge la lib. |
| **Pas de noms génériques** (`.title`, `.button`, `.row`) hors UI Kit | Risque de collision globale. Préfixer par le contexte (`.heatmap-cell`, `.weekly-row`). |

> **Synthèse** : le bon mécanisme est **celui qui rend le code le plus clair pour le prochain développeur**. Les règles existent pour servir la maintenabilité, l'évolutivité et la performance — pas pour leur propre satisfaction.

---

## Build local et déploiement

### Contrainte Hostinger : pas de Node en SSH

Confirmé le 24/04/2026 par test SSH (`node -v` → `command not found`). Le build assets se fait **localement** sur le poste développeur, et les artefacts compilés (`public/build/`) sont **versionnés et poussés** via Git.

### Workflow de déploiement

```bash
# 1. Build local (poste développeur, Node 24 LTS via Herd ou local)
npm run build
# → produit public/build/manifest.json + public/build/assets/{name}-{hash}.js
#   et public/build/assets/{name}-{hash}.css

# 2. Commit et push des assets compilés
git add public/build/
git commit -m "build: assets {description}"
git push

# 3. Côté Hostinger (via SSH ou pipeline CI/CD)
git pull
php composer2 install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan typescript:transform   # régénère resources/js/types/generated.d.ts (no-op si pas de changement)
```

### `.gitignore` — exception sur `public/build/`

```gitignore
# Par défaut Laravel ignore public/build/
# On le force à être versionné car Hostinger ne peut pas le builder.
!public/build/
```

### Pipeline CI/CD — recommandation

Mettre en place un workflow GitHub Actions qui :

1. À chaque push sur `main`, build les assets dans un job CI.
2. Crée un commit auto avec les assets compilés (sur une branche `deploy/` ou directement sur `main` selon la stratégie).
3. SSH vers Hostinger pour `git pull` + `php artisan` commands.

> Le détail du workflow CI/CD sera couvert dans un document dédié (`ci-cd.md`, étape ultérieure ou directement à l'implémentation).

---

## Server-Side Rendering (SSR) — désactivé en V1

Inertia v3 supporte le SSR out-of-the-box via Node.js. **En V1 Floty, on désactive le SSR** :

- Hostinger Business sans Node ne peut pas exécuter le serveur SSR.
- Floty est une app B2B derrière login : aucun bénéfice SEO.
- Surcoût d'architecture non justifié.

```ts
// resources/js/app.ts — pas de bloc SSR en V1

// vite.config.ts — option ssr commentée ou retirée
laravel({
  input: ['resources/css/app.css', 'resources/js/app.ts'],
  // ssr: 'resources/js/ssr.ts',  // désactivé V1
  refresh: true,
}),
```

**Évolution V3+** : si Floty migre sur VPS, le SSR pourra être activé pour gagner en perception de performance au premier chargement (utile pour les heatmaps lourdes).

---

## Images statiques

### Organisation

```
public/images/
├── logo-floty.svg                         ← logo principal (transverse)
├── favicons/
│   ├── favicon.ico
│   ├── apple-touch-icon.png
│   └── manifest.json
├── web/                                   ← images partie publique
│   ├── home/
│   │   ├── hero-illustration.webp
│   │   └── value-prop-1.webp
│   └── auth/
│       └── login-bg.webp
└── user/                                  ← images partie connectée (rare)
    └── empty-states/
        └── no-vehicles.svg
```

### Convention de nommage

`{contexte}-{description}.{ext}` en kebab-case :

- `hero-illustration.webp`
- `vehicle-empty-state.svg`
- `lcd-counter-icon.svg`

### Format

| Usage | Format préféré |
|---|---|
| Photo / image complexe | **WebP** (qualité 85%, supporté universellement) |
| Logo / icône vectorielle | **SVG** (scalable, currentColor pour théming) |
| Favicon | **ICO** + **PNG** multi-tailles |
| Animation simple | **WebM** (vidéo) ou animation CSS |

### Convention placeholder + prompt AI (héritage utile à conserver)

Quand une page nécessite une image qui n'a pas encore été produite, on utilise un **placeholder provisoire** + un **prompt AI détaillé** en commentaire au-dessus du composant Vue :

```vue
<!--
  IMAGE: Background de la page de connexion
  Emplacement cible : public/images/web/auth/login-bg.webp
  Format : WebP, qualité 85%, 1920x1080px
  Prompt AI :
    Photo professionnelle d'une flotte de véhicules utilitaires modernes
    garés en cercle autour d'un bâtiment d'entreprise contemporain au coucher
    de soleil. Lumière dorée chaude, ambiance corporate sereine, palette de
    bleus profonds et oranges chauds, composition aérienne en grand-angle.
-->
<!-- <img src="/images/web/auth/login-bg.webp" alt="Flotte de véhicules d'entreprise au coucher de soleil" class="absolute inset-0 h-full w-full object-cover" /> -->

<!-- Placeholder provisoire -->
<div class="absolute inset-0 bg-gradient-to-br from-primary-900 to-primary-700" />
```

**Règles** :

1. Le commentaire avec le prompt est **au-dessus** de la balise `<img>` commentée.
2. La balise `<img>` est **prête à l'emploi** : il suffit de la décommenter.
3. Le placeholder reproduit les dimensions et le positionnement.
4. Quand l'image est intégrée, on **supprime le commentaire prompt** et le placeholder.

---

## Variables d'environnement Vite

Les variables exposées au front sont préfixées `VITE_` (convention Vite obligatoire).

```env
# .env
VITE_APP_NAME="Floty"
VITE_APP_VERSION="1.0.0"
```

```ts
// Usage TypeScript
const appName = import.meta.env.VITE_APP_NAME ?? 'Floty'
```

```ts
// resources/js/types/env.d.ts
/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_APP_NAME: string
  readonly VITE_APP_VERSION: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
```

**Règle stricte** : **jamais de secret dans une variable `VITE_`**. Tout ce qui est préfixé `VITE_` est embarqué dans le bundle JS et donc public. Les secrets restent dans `.env` côté backend uniquement.

---

## Anti-patterns Vite + Inertia + Tailwind

> Ces anti-patterns sont des **vrais problèmes de discipline ou d'architecture**, pas des interdictions arbitraires. La structure (fichiers CSS dédiés segmentés) n'est pas un anti-pattern — la dispersion incontrôlée et la duplication le sont.

### Anti-patterns CSS

| Anti-pattern | Pourquoi c'est un problème | Correction |
|---|---|---|
| Même règle Tailwind `@apply` dupliquée dans 5 fichiers `.css` (ex: `.btn-primary` redéfinie partout) | 5 sources de vérité, modification = 5 fichiers à toucher | Refactor en composant Vue (`<Button variant="primary" />`) qui centralise |
| Spécificité accumulée (`.page .section .table .row .cell`) | Bloque la réutilisabilité, conflits inattendus | Sélecteurs plats + Tailwind utility (spécificité 1) |
| Style global caché dans un fichier de page (ex: `.global-modal` défini dans `User/Vehicles/Index/index.css`) | Supprimer la page casse 12 modals ailleurs | Style partagé → `app.css` ou `Shared/{...}.css` explicite |
| Surcharge de lib tierce dispersée (lib importée à 3 endroits, surcharge à un seul) | Le rendu varie selon la page | Lib + surcharge mutualisées dans un fichier (`Shared/overrides/{lib}.css`) importé partout où la lib est chargée |
| Conflit de noms (`.title`, `.button`, `.row` redéfinis dans plusieurs pages) | Composant partagé rend différemment selon la page | Préfixer par le contexte (`.heatmap-cell`, `.weekly-row`) ou utiliser `<style scoped>` |
| Tokens design system redéfinis hors `app.css` | Plusieurs sources de vérité pour les couleurs | Tokens **uniquement** dans `app.css` via `@theme` |
| Configuration Tailwind via `tailwind.config.js` | Modèle Tailwind 3 obsolète | Tailwind 4 = config CSS-first via `@theme` dans `app.css` |
| `postcss.config.js` séparé | Plugin Vite Tailwind l'intègre | Plus besoin de `postcss.config.js` |

### Anti-patterns JS / Vite

| Anti-pattern | Correction |
|---|---|
| Plusieurs entrées JS par zone (`web.js`, `app.js`) | Single entry `app.ts` + code splitting auto Inertia |
| `defineAsyncComponent` sur des composants statiques | Code splitting auto déjà géré par Vite + Inertia. N'en ajouter que pour des cas exceptionnels documentés. |
| Skeleton + lazy-loading systématique | Anti-pattern explicite (cf. `performance-ui.md`) |
| Build sur Hostinger via SSH | Confirmé impossible (pas de Node) — build local + push assets compilés |
| Sourcemaps en production | Désactivés (taille + sécurité) |
| Bundle géant non splitté | `manualChunks: { vendor: [...] }` dans `vite.config.ts` |
| Variables `.env` non préfixées exposées au front | Préfixe `VITE_` obligatoire pour le client, **jamais de secret** dans `VITE_*` |

---

## Cohérence avec les autres règles

- **Architecture en couches** (où vivent les composants Vue, composables, stores, utils) : voir `architecture-solid.md`.
- **Conventions de nommage** (Pages, Components, fichiers `.vue`, `.ts`, `.css`) : voir `conventions-nommage.md`.
- **Structure des fichiers** (arborescence détaillée Pages avec Partials, Components Ui/Domain/Layouts) : voir `structure-fichiers.md`.
- **Performance UI** (memoization, virtualisation heatmap, anti-patterns skeleton/lazy) : voir `performance-ui.md` (étape 5.4).

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 2.1 | 24/04/2026 | Micha MEGRET | Révision après dialogue : passage du modèle « single-entry CSS exclusif » au **modèle hybride pragmatique** (modèle C). Ajout de la grosse section « Les trois mécanismes CSS et leur usage » qui pose les 3 mécanismes coexistants (Tailwind utility / fichier `.css` dédié segmenté `{Espace}/{Domaine}/` / `<style scoped>` Vue) avec leurs critères de décision et leurs règles de discipline. Refonte des anti-patterns CSS pour qu'ils ciblent les vrais problèmes (duplication, spécificité, conflits de noms, tokens redéfinis) plutôt que la structure (qui n'est pas un anti-pattern en soi). Reconnaissance explicite que la segmentation CSS par espace/domaine reste cohérente avec la philosophie Floty. Glob automatique mentionné comme option future si volume CSS dédié significatif. |
| 2.0 | 24/04/2026 | Micha MEGRET | **Refonte complète** pour stack Floty (Vite 8 Rolldown + Inertia v3 + Vue 3 + TypeScript 6 + Tailwind 4 CSS-first). Suppression du modèle Alpine standalone par zone, suppression des bundles CSS séparés (web.css, ui-kit.css, app.css). Single-entry JS (`app.ts`) + single-entry CSS (`app.css`). Code splitting **automatique** par page Inertia via `import.meta.glob`. Configuration Vite 8 complète. Tailwind 4 modèle CSS-first avec `@theme` (à intégrer depuis le design system Floty). Workflow build local + push assets compilés (Hostinger Business sans Node). SSR désactivé V1. Anti-pattern explicite skeleton + lazy-loading. Convention placeholder + prompt AI préservée. |
| 1.0 | mars 2026 | Micha MEGRET | Version initiale, contexte ancien projet Livewire + Alpine + Blade. |
