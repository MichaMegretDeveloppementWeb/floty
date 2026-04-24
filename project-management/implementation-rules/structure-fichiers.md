# Structure des fichiers

> **Stack référence** : Laravel 13, Inertia v3, Vue 3.5, TypeScript 6, Spatie Laravel Data 4, PHP 8.5, MySQL 8.
> **Niveau d'exigence** : senior +, code soumis à critique de pairs experts. Aucune erreur de débutant tolérée.
> **Documents liés** : `architecture-solid.md` (couches & arborescence détaillée), `conventions-nommage.md` (noms), `assets-vite.md` (bundling), `gestion-erreurs.md` (exceptions).

---

## Principe directeur

L'arborescence Floty repose sur un seul axiome : **éviter à tout prix les dossiers fourre-tout et les fichiers monolithiques**. Un répertoire ne devrait jamais contenir 50 fichiers, un fichier ne devrait jamais dépasser 400 lignes en règle générale (1000 max en exception justifiée).

Pour atteindre cet objectif, on segmente selon **trois axes orthogonaux** :

1. **Axe Espace** — distingue le contexte d'usage (`Web/` public, `User/` connecté, `Shared/` cross-espaces).
2. **Axe Domaine** — entité métier ou famille fonctionnelle (`Vehicle`, `Company`, `Driver`, `Assignment`, `Unavailability`, `Declaration`, `Planning`, `Fiscal`, `Auth`).
3. **Axe Page / Action** (côté front uniquement) — chaque page Inertia significative a son propre dossier qui contient la vue principale **et** son dossier `Partials/` adjacent. Plus jamais de fichier de 1000 lignes ni de dossier `Partials/` global.

Ces trois axes s'appliquent **partout** où c'est pertinent. Les seules couches non segmentées par espace sont celles qui sont conceptuellement transverses : `Models/` (Eloquent), `Enums/`, `Exceptions/`, `Policies/`, `Providers/`.

> **Pourquoi la segmentation par Espace dès V1**, alors qu'il n'y a qu'un type d'utilisateur ?
>
> 1. La partie publique et la partie connectée n'ont rien à voir (page d'accueil ≠ heatmap fiscale).
> 2. V2 introduira potentiellement des rôles (`User/Admin/`, `User/Operator/`, `User/Reader/`) — la structure actuelle s'étend par addition pure, sans déplacement.
> 3. La navigation IDE devient évidente : on sait immédiatement où chercher selon le contexte.
> 4. Aucun coût supplémentaire significatif (un namespace de plus dans les chemins).
>
> **Règle directrice** : qui peut le plus peut le moins. Mieux vaut une couche de namespace en plus dès V1 que d'avoir à refactorer 100 fichiers le jour où un second espace utilisateur apparaît.

---

## Vue d'ensemble — répartition Backend / Frontend

L'application Floty se découpe en deux moitiés clairement séparées :

```
floty/
├── app/                    ← BACKEND Laravel (PHP)
│   ├── Http/               ← couche présentation (controllers, requests, middleware)
│   ├── Actions/            ← orchestrateurs métier
│   ├── Services/           ← logique métier
│   ├── Repositories/       ← accès BDD complexe
│   ├── Contracts/          ← interfaces (repositories essentiellement)
│   ├── Data/               ← DTO Spatie Data (frontière PHP↔TS)
│   ├── Models/             ← Eloquent
│   ├── Enums/              ← énumérations PHP backed
│   ├── Exceptions/         ← exceptions métier typées
│   ├── Policies/           ← autorisations Laravel
│   └── Providers/          ← service providers
│
├── resources/              ← FRONTEND Inertia + Vue (TypeScript)
│   ├── js/
│   │   ├── Pages/          ← pages Inertia (correspondance 1:1 avec Inertia::render)
│   │   ├── Components/     ← composants réutilisables hors page
│   │   ├── Composables/    ← logique réactive partagée
│   │   ├── Stores/         ← Pinia
│   │   ├── Utils/          ← fonctions pures
│   │   ├── Layouts/        ← layouts Inertia
│   │   ├── types/          ← types TS (générés + manuels)
│   │   └── app.ts          ← entrée Vite/Inertia
│   └── css/
│       └── app.css         ← Tailwind 4 + tokens design system Floty
│
├── routes/                 ← définitions de routes
├── database/               ← migrations, seeders, factories
├── tests/                  ← PHPUnit (back) + Vitest (front, dans resources/)
├── public/                 ← assets statiques publics (images, favicons)
└── storage/                ← stockage applicatif (PDF générés, logs)
```

> Pour l'**arborescence détaillée complète** (toutes les sous-couches, tous les domaines Floty), voir `architecture-solid.md` — section « Arborescence type ». Le présent document se concentre sur les **principes** et les **patterns récurrents**.

---

## Backend — règles spécifiques d'organisation

### Répartition des couches segmentées vs non segmentées

| Couche | Segmentation | Justification |
|---|---|---|
| `Http/Controllers/` | `{Espace}/{Domaine}/` | Le controller est la couche de présentation, ses préoccupations sont contextuelles |
| `Http/Requests/` | `{Espace}/{Domaine}/` | Validation contextuelle (les règles diffèrent souvent entre espaces) |
| `Actions/` | `{Espace}/{Domaine}/` | Une action exprime une intention utilisateur dans un contexte précis |
| `Services/` | `{Espace}/{Domaine}/` ou `Shared/{Domaine}/` | Logique métier d'un espace, ou transverse (`Shared/Fiscal/`, `Shared/Pdf/`) |
| `Repositories/` | `{Espace}/{Entité}/` ou `Shared/{Entité}/` | Idem services |
| `Contracts/Repositories/` | Miroir strict des Repositories | Contrat = même chemin que l'implémentation |
| `Data/` | `{Espace}/{Domaine}/` | Le DTO est exposé à un contexte UI, donc segmenté comme la présentation |
| `Models/` | **Plat, par entité** | Conceptuellement transverse (un même modèle Eloquent sert tous les espaces) |
| `Enums/` | `{Domaine}/` | Conceptuellement transverse |
| `Exceptions/` | `{Domaine}/` | Conceptuellement transverse |
| `Policies/` | **Plat, par entité** | Une policy par modèle, pas par espace |
| `Providers/` | **Plat** | Service providers globaux |

### L'espace `Shared/` — quand et pourquoi

`Shared/` apparaît uniquement dans `Services/` et `Repositories/`. Il sert à isoler ce qui est utilisé **par plusieurs espaces** ou qui est **conceptuellement transverse à l'application**.

Exemples Floty :

- `Services/Shared/Fiscal/` — le moteur de règles fiscales est utilisé par `User/Declaration/` mais aussi indirectement par `User/Planning/` (compteur LCD temps réel). Il vit dans `Shared/`.
- `Services/Shared/Pdf/` — le rendu PDF est appelable depuis n'importe quel espace.
- `Services/Shared/Storage/` — l'abstraction du stockage filesystem.
- `Services/Shared/Cache/` — la gestion des tags d'invalidation cache.

**Règle stricte** : ne pas créer un `Shared/` par défaut. Un service vit dans son espace tant qu'il n'est utilisé que là. Le promouvoir dans `Shared/` seulement quand un second espace en a besoin réellement (pas par anticipation).

### Routes — fichiers segmentés par espace

```
routes/
├── web.php          ← partie publique : home, mentions légales, login form
├── auth.php         ← actions d'authentification : login POST, logout
└── user.php         ← partie connectée : dashboard, vehicles, planning, declarations…
```

**Convention** : chaque fichier route correspond à un **bloc cohérent de routes** servies par les mêmes middlewares. Tous chargés via `bootstrap/app.php` :

```php
// bootstrap/app.php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    then: function (): void {
        Route::middleware('web')->group(base_path('routes/auth.php'));
        Route::middleware(['web', 'auth'])
            ->prefix('app')
            ->name('user.')
            ->group(base_path('routes/user.php'));
    },
)
```

**Règles** :

- Le préfixe URL `/app` (ou autre) délimite la zone connectée. Convention exacte à acter à l'implémentation.
- Le préfixe de nom `user.` permet d'éviter les collisions et de filtrer aisément les routes par espace dans `route('user.vehicles.show', ...)`.
- Les middlewares (`auth`, `verified` si activé V2, etc.) sont définis au niveau du groupe, pas par route.

### Pattern Controller invocable vs Resource controller

| Cas | Type de controller | Exemple Floty |
|---|---|---|
| Page sans action multiple (affichage seul) | **Invocable** (méthode `__invoke`) | `Web\Home\HomeController`, `Web\MentionsLegales\MentionsLegalesController` |
| Ressource CRUD (Index/Show/Create/Store/Edit/Update/Destroy) | **Resource controller** | `User\Vehicle\VehicleController` |
| Action ponctuelle non-CRUD | **Invocable** | `User\Declaration\GenerateDeclarationPdfController` |
| Plusieurs actions liées non-REST | Controller classique avec méthodes nommées | `User\Planning\WeeklyEntryController@show`, `@store`, `@bulkUpdate` |

### Pattern FormRequest

Une `FormRequest` par couple (verbe, ressource). Conventions :

- `StoreVehicleRequest` — création d'un véhicule.
- `UpdateVehicleRequest` — modification.
- `BulkSaveWeeklyAssignmentsRequest` — mutation batch spécifique.

Une `FormRequest` sépare clairement :

- **Authorization** (`authorize()`) — réservée aux vérifications d'autorisation simples ; les Policies sont préférées dès qu'il y a une logique non triviale.
- **Validation rules** (`rules()`) — règles déclaratives.
- **Messages personnalisés** (`messages()`) — en français (cf. `conventions-nommage.md`).
- **Préparation des données** (`prepareForValidation()`) — pour normaliser avant validation.

---

## Frontend — règles spécifiques d'organisation

### Le pattern « une page = un dossier + Partials »

C'est **la règle structurante** du frontend Floty. Toute page Inertia significative obéit à ce pattern :

```
resources/js/Pages/{Espace}/{Domaine}/{PageName}/
├── {PageName}.vue           ← vue principale qui orchestre les partials
└── Partials/
    ├── {PartialName1}.vue
    ├── {PartialName2}.vue
    └── ...
```

**Exemple concret Floty — page liste véhicules** :

```
resources/js/Pages/User/Vehicles/Index/
├── Index.vue                          ← orchestre, gère les props Inertia, useForm, etc.
└── Partials/
    ├── VehicleListHeader.vue          ← titre + bouton « Ajouter »
    ├── VehicleFilters.vue             ← barre de filtres (type, carburant, statut)
    ├── VehicleTable.vue               ← tableau avec tri + pagination
    └── VehicleEmptyState.vue          ← état vide si aucun véhicule
```

**Exemple Floty — fiche véhicule (page Show)** :

```
resources/js/Pages/User/Vehicles/Show/
├── Show.vue
└── Partials/
    ├── VehicleSummary.vue                            ← carte d'identité du véhicule
    ├── VehicleFiscalCharacteristicsTable.vue         ← table des caractéristiques courantes
    ├── VehicleFiscalHistoryTimeline.vue              ← timeline des versions historisées
    ├── VehicleAssignmentsTimeline.vue               ← timeline des attributions
    └── VehicleUnavailabilitiesList.vue               ← liste des indisponibilités
```

### Pourquoi cette règle est non négociable

| Anti-pattern observé en revue senior | Conséquence | Solution Floty |
|---|---|---|
| Une page Vue de 1200 lignes | Illisible, impossible à modifier sans risque | Découpage en partials de 150-300 lignes |
| Un dossier `resources/js/Partials/` à la racine avec 80 fichiers | Aucun moyen de savoir à quelle page appartient quoi | Partials adjacents à leur page |
| Des partials nommés `Section1.vue`, `Section2.vue` | Aucun sens | Nom descriptif (`VehicleFilters`, `LcdCumulCounter`) |
| Mélange partials + composants partagés dans le même dossier | Confusion sur la portée | Séparation stricte : `Pages/{...}/Partials/` (page-specific) vs `Components/` (réutilisable) |

### Quand un partial devient un composant `Components/Domain/`

Un partial Vue vit dans `Pages/{Espace}/{Domaine}/{PageName}/Partials/` tant qu'il est **spécifique à cette page**. Si le même partial doit être utilisé sur plusieurs pages, il **migre** dans `Components/Domain/{Domaine}/`.

**Critère de promotion** : utilisé dans **au moins 2 pages distinctes** (pas hypothétiquement, réellement).

Exemple :

- `VehicleForm.vue` apparaît dans `Pages/User/Vehicles/Create/Partials/` ET dans `Pages/User/Vehicles/Edit/Partials/` ⇒ candidat à promotion vers `Components/Domain/Vehicle/VehicleForm.vue`.
- `VehicleFilters.vue` n'apparaît que dans `Pages/User/Vehicles/Index/Partials/` ⇒ reste partial.

**Règle stricte** : pas de duplication. Soit le partial vit à un seul endroit (sa page), soit il est promu en composant partagé. Jamais deux fois le même fichier.

### Distinction `Components/Ui/` vs `Components/Domain/` vs `Components/Layouts/`

| Dossier | Nature | Exemple Floty |
|---|---|---|
| `Components/Ui/` | **UI Kit Floty custom** : composants génériques, sans logique métier (boutons, inputs, modals, badges, cards). Construit depuis le design system. | `Button.vue`, `TextInput.vue`, `ConfirmModal.vue`, `Drawer.vue` |
| `Components/Domain/` | Composants **métier réutilisables** entre plusieurs pages d'un même espace. | `VehicleCard.vue`, `CompanyBadge.vue`, `LcdCumulCounter.vue`, `DeclarationStatusBadge.vue` |
| `Components/Layouts/` | **Squelettes de page** (sidebar, top bar) appliqués via `defineLayout` Inertia. | `WebLayout.vue`, `UserLayout.vue` + leurs partials internes |

**Règle stricte** :

- `Ui/` ne dépend **jamais** de modèles métier Floty. Un `Button.vue` ne sait pas ce qu'est un véhicule.
- `Domain/` peut dépendre de `Ui/` (un `VehicleCard.vue` utilise un `Button.vue`), mais **jamais l'inverse**.
- `Layouts/` peut dépendre de `Ui/` et `Domain/`. C'est la couche la plus haute du graph de dépendances frontaux.

### Composables, Stores, Utils — segmentation

| Type | Segmentation | Exemples Floty |
|---|---|---|
| `Composables/{Espace}/` | Par espace | `Composables/User/useFiscalYear.ts`, `Composables/User/useLcdCumul.ts`, `Composables/Web/useContactForm.ts` |
| `Composables/Shared/` | Cross-espaces ou techniques | `Composables/Shared/useDebouncedRef.ts`, `Composables/Shared/useKeyboardShortcuts.ts` |
| `Stores/{Espace}/` | Par espace, **uniquement état cross-page** | `Stores/User/fiscalYearStore.ts`, `Stores/User/currentUserStore.ts` |
| `Utils/{Famille}/` | Par famille fonctionnelle | `Utils/format/formatEuro.ts`, `Utils/validation/frenchPlate.ts`, `Utils/fiscal/computeProrata.ts` |

> Le détail des règles d'usage Composables / Stores / Utils sera couvert dans `composables-services-utils.md` (à créer en étape 5.4).

### Anti-patterns frontaux à proscrire

| Anti-pattern | Correction |
|---|---|
| `resources/js/Partials/` à la racine | Pas de dossier `Partials/` global. Partials toujours adjacents à leur page. |
| Composant Vue dans `resources/js/Pages/` qui n'est pas une page Inertia | Toute page Vue dans `Pages/` doit correspondre à un `Inertia::render('...')` côté Laravel. Sinon c'est un composant ⇒ `Components/`. |
| Composant Vue dans `Components/` qui dépend d'une route Inertia | Un composant réutilisable ne sait pas dans quelle page il vit. Si dépendance route, soit c'est un partial spécifique à une page, soit on injecte la route via une prop. |
| Store Pinia qui contient toutes les données de l'app | Stores Pinia uniquement pour l'état réellement cross-pages. Le reste passe par les props Inertia. |
| Un fichier de 800 lignes dans `Pages/.../Index.vue` qui fait tout | Découpage en partials, contractuel et systémique. |

---

## Layouts Inertia

### Définition

Un layout Inertia est un composant Vue qui sert de **squelette** à un ensemble de pages. Il fournit la sidebar, la top bar, les éléments globaux (toasts, modals partagées), et un `<slot />` central où Inertia injecte la page courante.

### Règle Floty — deux layouts en V1

| Layout | Pages concernées | Composition |
|---|---|---|
| `WebLayout.vue` | Pages publiques (`Web/Home`, `Web/MentionsLegales`, `Web/Auth/Login`) | Header public minimal + footer mentions légales + slot |
| `UserLayout.vue` | Pages connectées (`User/*`) | Sidebar + top bar avec sélecteur d'année + slot + zone toasts globaux |

### Application via `defineOptions` Inertia

```vue
<!-- resources/js/Pages/User/Vehicles/Index/Index.vue -->
<script setup lang="ts">
import UserLayout from '@/Components/Layouts/UserLayout.vue'

defineOptions({ layout: UserLayout })
// ... reste du composant
</script>
```

**Alternative** : assignation par défaut côté `app.ts` pour toutes les pages d'un espace :

```ts
// resources/js/app.ts
createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob<DefineComponent>('./Pages/**/*.vue', { eager: true })
    const page = pages[`./Pages/${name}.vue`]
    page.default.layout ??= name.startsWith('User/') ? UserLayout : WebLayout
    return page
  },
  // ...
})
```

> Le détail des stratégies Inertia (persistent layouts, nested layouts, transitions) sera couvert dans `inertia-navigation.md` (étape 5.4).

---

## Tests — organisation parallèle

Les tests miroitent l'arborescence du code testé.

### Backend (PHPUnit)

```
tests/
├── Feature/                    ← tests d'intégration (controllers, routes, BDD)
│   ├── User/
│   │   ├── Vehicle/
│   │   │   ├── VehicleControllerTest.php
│   │   │   └── CreateVehicleActionTest.php
│   │   └── Declaration/
│   │       └── GenerateDeclarationPdfControllerTest.php
│   └── Web/
│       └── Auth/
│           └── LoginControllerTest.php
├── Unit/                       ← tests unitaires (services, utils, calculs purs)
│   ├── User/
│   │   ├── Vehicle/
│   │   │   └── VehicleFiscalCharacteristicsServiceTest.php
│   │   └── Assignment/
│   │       └── LcdCumulCalculationServiceTest.php
│   └── Shared/
│       └── Fiscal/
│           └── FiscalRulePipelineTest.php
└── TestCase.php
```

### Frontend (Vitest)

Les tests Vitest vivent **à côté** du code testé (convention idiomatique 2026), pas dans un dossier `__tests__/` séparé.

```
resources/js/
├── Composables/
│   └── User/
│       ├── useFiscalYear.ts
│       └── useFiscalYear.spec.ts
├── Components/
│   └── Domain/
│       └── Vehicle/
│           ├── VehicleCard.vue
│           └── VehicleCard.spec.ts
└── Utils/
    └── format/
        ├── formatEuro.ts
        └── formatEuro.spec.ts
```

**Convention** : suffixe `.spec.ts` pour tous les tests front (pas `.test.ts`).

> Détails des stratégies de tests, fixtures typées, etc. : voir `tests-frontend.md` (étape 5.4).

---

## Stockage applicatif (`storage/`)

```
storage/
├── app/
│   ├── declarations/                       ← PDF générés (cf. ADR-0003)
│   │   └── {fiscal_year}/
│   │       └── {declaration_id}/
│   │           └── v{n}-{timestamp}.pdf
│   └── public/                             ← assets accessibles via lien symbolique storage:link
├── framework/                              ← cache Laravel, sessions, vues compilées
└── logs/                                   ← logs applicatifs (cf. gestion-erreurs.md)
```

> Le pattern de stockage des PDF est documenté dans `02-schema-fiscal.md` du dossier `modele-de-donnees/`.

---

## Public (`public/`)

```
public/
├── index.php                               ← entrée Laravel (ne pas toucher)
├── build/                                  ← assets compilés par Vite (auto-généré)
├── images/                                 ← images statiques publiques
│   ├── logo.svg
│   ├── favicons/
│   ├── web/                                ← images partie publique
│   │   └── home/
│   │       └── hero-bg.webp
│   └── user/                               ← images partie connectée (rare)
└── storage/                                ← lien symbolique vers storage/app/public
```

### Convention images statiques

```
public/images/{espace}/{contexte}/{nom}.{ext}
```

Exemples :

- `public/images/web/home/hero-illustration.webp`
- `public/images/web/auth/login-bg.webp`
- `public/images/logo-floty.svg` (transverse, à la racine)

**Format préféré** : WebP (compression supérieure, supporté universellement). SVG pour les logos et icônes vectorielles.

---

## Migrations et seeders

### Migrations

```
database/migrations/
├── 2026_04_24_000001_create_users_table.php
├── 2026_04_24_000002_create_vehicles_table.php
├── 2026_04_24_000003_create_vehicle_fiscal_characteristics_table.php
├── 2026_04_24_000004_create_entreprises_utilisatrices_table.php
├── 2026_04_24_000005_create_conducteurs_table.php
├── 2026_04_24_000006_create_attributions_table.php
├── 2026_04_24_000007_create_indisponibilites_table.php
├── 2026_04_24_000008_create_fiscal_rules_table.php
├── 2026_04_24_000009_create_declarations_table.php
└── 2026_04_24_000010_create_declaration_pdfs_table.php
```

**Conventions** :

- Nommage descriptif : `create_{table}_table`, `add_{column}_to_{table}_table`, `remove_{column}_from_{table}_table`.
- Une migration par changement de schéma (pas de migration fourre-tout).
- Migrations **idempotentes** quand possible (`Schema::hasColumn` avant `addColumn`).
- Triggers et procédures stockées dans des migrations dédiées (cf. invariants MySQL — exclusion constraints applicatives).

### Seeders

```
database/seeders/
├── DatabaseSeeder.php                      ← orchestrateur
├── Users/
│   └── DemoUserSeeder.php
└── Fiscal/
    ├── Rules2024Seeder.php                 ← 24 règles R-2024-001 à R-2024-024
    ├── Rules2025Seeder.php                 ← (V1.x quand 2025 sera traité)
    └── Rules2026Seeder.php
```

**Convention Floty** : les règles fiscales sont seedées par année (cf. ADR-0002, ADR-0006). Un seeder = une année fiscale.

---

## Résumé des règles d'arborescence

### Backend

| Règle | Application |
|---|---|
| Espace en premier (`Web/`/`User/`/`Shared/`) | `Http/Controllers/`, `Http/Requests/`, `Actions/`, `Services/`, `Repositories/`, `Contracts/Repositories/`, `Data/` |
| Domaine en second | Toutes les couches segmentées par espace |
| Plat (par entité ou domaine pur) | `Models/`, `Enums/{Domaine}/`, `Exceptions/{Domaine}/`, `Policies/`, `Providers/` |
| Miroir Repository ↔ Interface | `Repositories/{Espace}/{Entité}/X.php` ↔ `Contracts/Repositories/{Espace}/{Entité}/XInterface.php` |
| Routes par bloc cohérent | `routes/web.php`, `routes/auth.php`, `routes/user.php` |

### Frontend

| Règle | Application |
|---|---|
| **Une page = un dossier + Partials** | Toutes les pages Inertia significatives |
| Espace en premier dans `Pages/{Espace}/` | `Web/`, `User/` (et `User/{Role}/` en V2) |
| Composants partagés dans `Components/Ui/`, `Components/Domain/`, `Components/Layouts/` | Selon nature (générique / métier / squelette) |
| Composables et stores segmentés par espace | `Composables/{Espace}/`, `Stores/{Espace}/` |
| Utils segmentés par famille | `Utils/format/`, `Utils/validation/`, etc. |
| Tests `.spec.ts` adjacents au code | `Composable.ts` + `Composable.spec.ts` côte à côte |

### Anti-patterns universels

| Interdit | Toujours faire |
|---|---|
| Dossier `Partials/` à la racine `resources/js/` | Partials adjacents à leur page |
| Fichier > 1000 lignes | Découper en partials / sous-composants |
| Dossier > 50 fichiers | Sous-segmenter par sous-domaine ou famille |
| Duplication d'un même composant à 2 endroits | Promouvoir en `Components/Domain/` |
| Couplage `Ui/` → `Domain/` ou `Domain/` → page | Respecter le sens des dépendances : `Ui` < `Domain` < `Layouts` < `Pages` |
| Routes mélangées dans `web.php` | Découpage en `web.php` / `auth.php` / `user.php` |
| Modèle Eloquent dans un namespace par espace | `Models/` reste plat (transverse) |

---

## Cohérence avec les autres règles

- **Architecture en couches** (Action / Service / Repository / Resource / Composant Vue) avec **arborescence détaillée complète Floty V1** : voir `architecture-solid.md`.
- **Conventions de nommage** (PHP, TypeScript, Vue, BDD, routes) : voir `conventions-nommage.md`.
- **Bundling Vite et structure des assets** (entry, code splitting, Tailwind 4) : voir `assets-vite.md`.
- **Gestion des exceptions et propagation des erreurs** : voir `gestion-erreurs.md`.

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 2.0 | 24/04/2026 | Micha MEGRET | **Refonte complète** pour stack Floty (Laravel 13 + Inertia v3 + Vue 3 + TypeScript 6 + Spatie Data + PHP 8.5). Suppression des sections Livewire/Blade. Nouveau principe directeur (3 axes : Espace + Domaine + Page). Pattern « une page = un dossier + Partials » documenté en règle non négociable. Distinction `Components/Ui/` / `Components/Domain/` / `Components/Layouts/` formalisée. Routes segmentées (`web.php`, `auth.php`, `user.php`). Layouts Inertia avec `defineOptions`. Tests `.spec.ts` adjacents (Vitest). Migrations et seeders Floty (règles fiscales par année). Référence vers `architecture-solid.md` pour l'arborescence détaillée plutôt que dupliquer. |
| 1.0 | mars 2026 | Micha MEGRET | Version initiale, contexte ancien projet Livewire + Alpine + Blade. |
