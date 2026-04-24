# Conventions de nommage

> **Stack référence** : Laravel 13, Inertia v3, Vue 3.5, TypeScript 6, Spatie Laravel Data 4, PHP 8.5, MySQL 8.
> **Niveau d'exigence** : senior +, code soumis à critique de pairs experts. Aucune erreur de débutant tolérée.
> **Documents liés** : `architecture-solid.md` (couches), `structure-fichiers.md` (organisation), `assets-vite.md` (bundling), `gestion-erreurs.md` (exceptions).

---

## Principe fondamental

Tout ce qui est **interne au code** et à la base de données est en **anglais** : noms de classes, méthodes, variables, propriétés, tables, colonnes, routes, fichiers PHP/Vue/TS/CSS, messages de commit, commentaires techniques.

Tout ce qui est **affiché à l'utilisateur** (labels, messages, textes d'interface, emails) est en **français** (langue de Floty, cf. `CLAUDE.md`).

**Objectif** : des noms suffisamment explicites pour qu'un développeur comprenne l'intention sans lire l'implémentation, mais assez courts pour rester lisibles et manipulables.

---

## Règle d'équilibre — explicite sans excès

Un bon nom répond à la question « qu'est-ce que c'est ? » ou « qu'est-ce que ça fait ? » en un coup d'œil.

| Trop court | Correct | Trop long |
|---|---|---|
| `$v` | `$vehicle` | `$currentlyAttributedVehicle` |
| `$dt` | `$effectiveFrom` | `$fiscalCharacteristicsEffectiveFromDate` |
| `calc()` | `calculateDeclaration()` | `calculateDeclarationCo2AndPolluantsTaxesForFiscalYear()` |
| `$res` | `$declaration` | `$generatedFiscalDeclarationResult` |
| `chk()` | `isInvalidated()` | `checkIfDeclarationHasBeenInvalidatedRecently()` |
| `$tmp` | `$pendingAttributions` | `$listOfAttributionsThatAreCurrentlyPending` |

### Règles transverses

1. **Pas d'abréviations** sauf celles universellement connues : `id`, `url`, `pdf`, `api`, `http`, `db`, `dto`, `ui`, `vue`, `ts`, `siv`, `crm`. Pour les sigles métier propres à Floty (LCD, WLTP, NEDC, PA, CO2), utiliser tels quels — ils sont familiers du domaine.
2. **Pas de redondance avec le contexte** — dans `VehicleService`, une méthode `create()` suffit, pas besoin de `createVehicle()`.
3. **Le type/rôle du conteneur dispense de le répéter** — `$vehicles` dans un `VehicleListReadRepository` n'a pas besoin de s'appeler `$vehicleCollection`.
4. **Préférer le spécifique au générique** — `$activeVehicles` plutôt que `$data`, `$invalidatedDeclarations` plutôt que `$list`.
5. **Un nom de méthode commence par un verbe** — `send()`, `calculate()`, `findById()`, jamais un nom seul.
6. **Pas de noms en français dans le code** — `$vehicule` est interdit, c'est `$vehicle`. La langue française est strictement réservée à l'UI utilisateur.

---

## Classes PHP

Toutes les classes suivent le format **PascalCase**.

| Type | Pattern | Exemples Floty |
|---|---|---|
| Modèle | Singulier, nom de l'entité | `Vehicle`, `Company`, `Assignment`, `DeclarationPdf` |
| Controller | `{Entité}Controller` ou `{Verbe}{Entité}Controller` (invocable) | `VehicleController`, `GenerateDeclarationPdfController` |
| Action | `{Verbe}{Entité}Action` | `CreateVehicleAction`, `BulkSaveWeeklyAssignmentsAction` |
| Service | `{Entité}{Responsabilité}Service` | `VehicleFiscalCharacteristicsService`, `LcdCumulCalculationService` |
| Repository | `{Entité}{Responsabilité}Repository` | `LcdCumulReadRepository`, `DeclarationPdfWriteRepository` |
| Interface | `{Classe}Interface` | `LcdCumulReadRepositoryInterface` |
| Data (DTO Spatie) | `{Entité}{Usage?}Data` | `VehicleData`, `VehicleListItemData`, `WeeklyAssignmentData` |
| Exception | `{Entité}{Contexte}Exception` | `VehicleCreationException`, `AssignmentConflictException` |
| Enum | Singulier, PascalCase | `SourceEnergie`, `MethodeHomologation`, `DeclarationStatus` |
| FormRequest | `{Verbe}{Entité}Request` | `StoreVehicleRequest`, `BulkSaveWeeklyAssignmentsRequest` |
| Middleware | Descriptif de l'action | `EnsureUserIsAuthenticated`, `RecordLastActivity` |
| Mail | Descriptif du contenu | `DeclarationGeneratedNotification` (V2+) |
| Event | Passé composé | `DeclarationInvalidated`, `VehicleAttributed` |
| Listener | Descriptif de la réaction | `InvalidateDependentDeclarations`, `RecalculateLcdCumul` |
| Job | Impératif | `RecalculateAllLcdCumulsForYear` |
| Policy | `{Entité}Policy` | `VehiclePolicy`, `DeclarationPolicy` |
| Provider | `{Sujet}ServiceProvider` | `RepositoryServiceProvider`, `FiscalRulesServiceProvider` |

→ Pour le détail des patterns par couche : voir `architecture-solid.md`.
→ Pour l'organisation en fichiers et dossiers : voir `structure-fichiers.md`.

---

## Méthodes et fonctions PHP

Format **camelCase**. Commencent toujours par un verbe.

### Verbes standards

| Verbe | Usage | Exemples Floty |
|---|---|---|
| `find` | Chercher un élément (lève une exception si introuvable) | `findById()`, `findCurrentForVehicle()` |
| `get` | Récupérer une valeur dérivée (retourne null si absente) | `getEffectiveFrom()`, `getCurrentVersion()` |
| `list` | Retourner une collection | `listActive()`, `listByCompany()` |
| `count` | Retourner un nombre | `countDaysForCoupleInYear()` |
| `create` | Créer une nouvelle entité | `create()`, `createInitialVersion()` |
| `update` | Modifier une entité existante | `update()`, `updateStatus()` |
| `delete` | Supprimer une entité (soft ou hard selon contexte) | `softDelete()`, `hardDelete()` |
| `close` | Fermer une période ouverte | `closeVersion()`, `closeUnavailability()` |
| `open` | Ouvrir / rouvrir | `openNewVersion()` |
| `assign` | Attribuer | `assignToCompany()` |
| `replace` | Remplacer | `replaceDriver()` |
| `send` | Envoyer (email, notification…) | `send()` |
| `calculate` | Effectuer un calcul (préfèrer aux verbes courts comme `compute`) | `calculateDeclaration()`, `calculateLcdCumul()` |
| `validate` | Vérifier une règle métier (lève une exception si invalide) | `validateInvariants()` |
| `assert` | Affirmer une précondition (lève si fausse) | `assertCrossFieldInvariants()` |
| `format` | Transformer un format pour l'affichage | `formatEuro()`, `formatImmatriculation()` |
| `parse` | Analyser une donnée brute | `parseImportRow()` (V3+) |
| `sync` | Synchroniser un état | `syncFiscalRules()` |
| `render` | Produire un livrable (PDF, HTML…) | `render()` |
| `persist` | Sauvegarder explicitement (Repository) | `persist()` |
| `mark` | Marquer un état | `markAsInvalidated()`, `markAsSent()` |
| `detect` | Détecter automatiquement | `detectInvalidation()` |

### Booléens

Les méthodes et propriétés booléennes utilisent un préfixe interrogatif :

| Préfixe | Usage | Exemples Floty |
|---|---|---|
| `is` | État de l'entité | `isActive()`, `isInvalidated()`, `$isCurrent` |
| `has` | Possession / présence | `hasFiscalImpact()`, `hasPdfGenerated()`, `$hasFiscalImpact` |
| `can` | Capacité / autorisation | `canEdit()`, `canBeHardDeleted()` |
| `should` | Décision logique | `shouldInvalidate()`, `shouldRetry()` |

**Règle** : jamais de `getIsActive()` — utiliser directement `isActive()`.

---

## Variables et propriétés PHP

Format **camelCase**.

### Règles

1. **Nommer d'après ce que la variable contient**, pas d'après comment elle est obtenue.
2. **Collections au pluriel**, éléments singuliers : `$vehicles` (collection), `$vehicle` (un seul).
3. **Pas de préfixe de type** : `$vehicleName` et non `$strVehicleName`.
4. **Pas de `$temp`, `$data`, `$result`, `$item`** sauf dans un scope très local (closure d'une ligne, callback).
5. **Compteurs et itérateurs** courts acceptables dans les boucles : `$i`, `$key`, `$value`.
6. **Variables `readonly` privilégiées** dès que possible (PHP 8.1+) : `public readonly int $vehicleId`.

| Mauvais | Correct | Pourquoi |
|---|---|---|
| `$data` | `$attributionDetails` | Spécifique |
| `$arr` | `$companies` | Explicite |
| `$flag` | `$isInvalidated` | Sémantique |
| `$vehicle2` | `$replacementVehicle` | Le rôle, pas le numéro |
| `$getAllVehicles` | `$activeVehicles` | Contenu, pas méthode d'obtention |

---

## TypeScript

### Types et interfaces

Format **PascalCase**. Préférer `type` à `interface` sauf besoin d'extension/déclaration ouverte (rare).

| Pattern | Exemples Floty |
|---|---|
| Type généré depuis Spatie Data | `VehicleData`, `VehicleListItemData`, `DeclarationData` (auto-généré dans `types/generated.d.ts`) |
| Type local | `HeatmapCellState`, `WeeklyEntrySelection` |
| Type union | `ToastVariant = 'success' \| 'error' \| 'warning' \| 'info'` |
| Type discriminé | `FiscalRuleResult` avec champ `type` |
| Type utilitaire local | `Nullable<T>`, `DeepReadonly<T>` |

**Règle** : ne **jamais** dupliquer manuellement un type qui existe déjà côté PHP (Spatie Data). Le générateur `php artisan typescript:transform` produit `resources/js/types/generated.d.ts` qui est la **source de vérité**. Importer depuis là.

### Variables, fonctions, props

Format **camelCase**. Mêmes règles que PHP.

```ts
const activeVehicles: VehicleListItemData[] = ...
const formatEuro = (amount: number): string => ...
```

### Constantes

Format **SCREAMING_SNAKE_CASE** uniquement pour les vraies constantes de configuration (niveau module). Les valeurs immutables locales restent en `camelCase` avec `const`.

```ts
export const MAX_BULK_ATTRIBUTION_SIZE = 200
const todayDate = new Date()
```

### Énumérations

Préférer **les types union de strings** (TypeScript idiomatique 2026) plutôt que les `enum` natifs (qui ont des problèmes connus de tree-shaking, runtime overhead, semantics étranges).

```ts
// Préféré
type DeclarationStatus = 'brouillon' | 'verifiee' | 'generee' | 'envoyee'

// Évité (sauf cas justifiés)
enum DeclarationStatus { ... }
```

Les enums PHP côté backend sont auto-traduits par Spatie TS Transformer en union de strings. Aucune duplication.

### Génériques

Format `T`, `U`, `V` pour les types simples. Format `TXxx` pour les types nommés.

```ts
type ApiResult<T> = { data: T } | { error: string }
type Composable<TState, TActions> = ...
```

---

## Composants Vue

### Nommage des fichiers

Format **PascalCase.vue** (multi-mot pour éviter la collision avec les éléments HTML natifs).

| Type | Pattern | Exemples Floty |
|---|---|---|
| Page Inertia (vue principale) | Nommée d'après l'action ou la liste | `Index.vue`, `Show.vue`, `Create.vue`, `Edit.vue`, `WeeklyEntry.vue`, `Heatmap.vue` |
| Partial de page | Descriptif du contenu | `VehicleFilters.vue`, `HeatmapGrid.vue`, `LcdCumulCounter.vue` |
| Composant UI Kit | Nom court du composant | `Button.vue`, `Modal.vue`, `TextInput.vue`, `ConfirmModal.vue` |
| Composant Domain (réutilisable cross-pages) | `{Entité}{Rôle}` | `VehicleCard.vue`, `CompanyBadge.vue`, `DeclarationStatusBadge.vue` |
| Composant Layout | `{Espace}Layout.vue` ou descriptif | `WebLayout.vue`, `UserLayout.vue`, `Sidebar.vue`, `TopBar.vue` |

### Nommage interne (composants)

| Élément | Format | Exemple |
|---|---|---|
| Props (typés) | camelCase | `defineProps<{ vehicles: VehicleListItemData[] }>()` |
| Emits | camelCase, verbe au passé ou impératif | `defineEmits<{ click: [id: number]; submit: [data: VehicleFormData] }>()` |
| Refs locales | camelCase | `const isOpen = ref(false)` |
| Computed | camelCase, descriptif | `const visibleVehicles = computed(() => ...)` |
| Watch handlers | `onXxxChanged` ou nom descriptif | `watch(year, onYearChanged)` |

### Anti-patterns à proscrire

| Mauvais | Correct |
|---|---|
| `<myButton />` (mono-mot, collision HTML) | `<UiButton />` ou `<Button />` (PascalCase + multi-mot ou import) |
| `<button @click="click">` (générique) | `<Button @click="goToVehicle(vehicle.id)">` |
| Props non typées : `defineProps(['vehicles'])` | Props typées : `defineProps<{ vehicles: VehicleListItemData[] }>()` |
| Mutation directe de prop : `props.vehicles.push(...)` | Émettre un event ; les props sont readonly |

---

## Composables, stores Pinia, utilitaires

| Type | Format fichier | Pattern interne | Exemples Floty |
|---|---|---|---|
| Composable | `useXxx.ts` (camelCase préfixé `use`) | Fonction nommée `useXxx` | `useFiscalYear.ts` exporte `useFiscalYear()` |
| Store Pinia | `xxxStore.ts` (camelCase suffixé `Store`) | Fonction nommée `useXxxStore` | `fiscalYearStore.ts` exporte `useFiscalYearStore()` |
| Utilitaire pur | `verbeNoun.ts` (camelCase) | Fonctions nommées export | `formatEuro.ts` exporte `formatEuro()` |

### Règles

- Un composable = **un fichier = un export principal**. Petits exports utilitaires associés tolérés.
- Un store Pinia = **un fichier = un store**. Pas de stores fourre-tout.
- Un utilitaire = **fonctions pures sans état, sans réactivité Vue**. Si le code utilise `ref`/`reactive`, ce n'est plus un util, c'est un composable.

```ts
// resources/js/Composables/User/useFiscalYear.ts
export function useFiscalYear() {
  const year = ref(new Date().getFullYear())
  // ...
  return { year, setYear }
}

// resources/js/Stores/User/fiscalYearStore.ts
import { defineStore } from 'pinia'

export const useFiscalYearStore = defineStore('fiscalYear', {
  // ...
})

// resources/js/Utils/format/formatEuro.ts
export function formatEuro(amount: number): string {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(amount)
}
```

---

## Base de données

### Tables

- **snake_case**, **pluriel** : `vehicles`, `assignments`, `declaration_pdfs`, `vehicle_fiscal_characteristics`.
- Le nom décrit le contenu : une table de véhicules s'appelle `vehicles`, pas `tbl_vehicles` ni `vehicle_data`.
- **Pas de préfixe** (`tbl_`, `t_`, `app_`).

### Colonnes

- **snake_case**, **singulier** : `first_name`, `co2_wltp`, `created_at`.
- **Booléens** avec préfixe `is_`, `has_`, `can_` : `is_active`, `has_fiscal_impact`, `can_be_invalidated`.
- **Dates** suffixées par `_at` ou `_date` ou `_from`/`_to` : `published_at`, `effective_from`, `effective_to`, `acquisition_date`.
- **Clés étrangères** : `{table_singulier}_id` : `vehicle_id`, `company_id`.
- **Pas de redondance** avec le nom de la table : dans `vehicles`, utiliser `brand` et non `vehicle_marque`.

### Tables pivot

- Noms des deux tables au singulier, ordre alphabétique, séparés par `_` : `category_post`, `role_user`. (Pas en V1 Floty mais convention V2+.)

### Index et contraintes

- Laravel génère des noms automatiques pour les indexes — les laisser sauf besoin spécifique.
- Index nommés explicitement uniquement quand un nom porte du sens métier : `vehicles_immatriculation_active_unique`.
- Index partiels MySQL : nommer explicitement la condition (ex: `vehicles_imm_when_not_deleted_unique`).

### Migrations

- Nommer les fichiers de migration de façon descriptive : `create_vehicles_table`, `add_status_to_declarations_table`, `remove_legacy_columns_from_attributions_table`.

---

## Enums (PHP backed enum)

**Convention E1 — anglais strict** : tout ce qui est interne au code (nom de l'enum, cases, valeurs stockées en BDD) est en **anglais**. Seul l'affichage utilisateur (label) est en français, géré par une fonction de mapping dédiée.

Cas en **PascalCase** anglais. Les valeurs stockées en base sont en **snake_case anglais**.

```php
enum DeclarationStatus: string
{
    case Draft = 'draft';
    case Verified = 'verified';
    case Generated = 'generated';
    case Sent = 'sent';
}
```

```php
enum EnergySource: string
{
    case Gasoline = 'gasoline';
    case Diesel = 'diesel';
    case Electric = 'electric';
    case Hydrogen = 'hydrogen';
    case PluginHybrid = 'plugin_hybrid';
    case NonPluginHybrid = 'non_plugin_hybrid';
    case Lpg = 'lpg';
    case Cng = 'cng';
    case E85 = 'e85';
    case ElectricHydrogen = 'electric_hydrogen';
}
```

**Règles** :

- Le nom de l'enum est **singulier** et en **anglais** (`DeclarationStatus`, `EnergySource`, `HomologationMethod`, `PollutantCategory`, `EuroStandard`, `BodyType`, `VehicleUserType`, `UnavailabilityType`, `FiscalCharacteristicsChangeReason`, `InvalidationReason`, `RuleType`, `TaxType`).
- Les **cases** sont en **PascalCase anglais** (`Draft`, `Gasoline`, `PluginHybrid`).
- Les **valeurs stockées** sont en **snake_case anglais** (`'draft'`, `'gasoline'`, `'plugin_hybrid'`).
- **Exception unique** : codes administratifs officiels universels (acronymes universels comme `WLTP`, `NEDC`, `Euro1`-`Euro6dIscFcm`, et codes français de la rubrique J.2 du certificat d'immatriculation : `VP`, `VU`, `CI`, `BB`, `CTTE`, `BE`, `HB`) — conservés tels quels comme **valeurs** car ce sont des codes externes, pas des noms français.
- L'**affichage utilisateur** se fait via une fonction de mapping dédiée (ex: `statusLabel($status)` retourne `'Brouillon'`, `'Vérifiée'`, etc.) ou via Inertia/Vue avec un objet de labels. Jamais en concaténation directe avec la valeur enum.
- Les enums Floty sont **automatiquement traduits en types TS** par Spatie TS Transformer dès qu'ils apparaissent dans une `Data` annotée `#[TypeScript]`.

**Exemple cas particulier — codes administratifs FR conservés** :

```php
enum VehicleUserType: string
{
    case PassengerCar = 'VP';     // Voiture Particulière (code admin FR)
    case CommercialVehicle = 'VU'; // Véhicule Utilitaire (code admin FR)
}

enum BodyType: string
{
    case InteriorDriving = 'CI';   // Conduite Intérieure (rubrique J.2)
    case MiniVan = 'BB';           // Camionnette (rubrique J.2)
    case Truck = 'CTTE';           // Camionnette (rubrique J.2)
    case Pickup = 'BE';            // Pick-up (rubrique J.2)
    case Handicap = 'HB';          // Handicap (rubrique J.2)
}
```

---

## Routes Laravel

### URLs

- **kebab-case** pour les segments : `/vehicles/fiscal-characteristics`, `/declarations/{declaration}/pdf`.
- **Pluriel** pour les ressources : `/vehicles`, `/companies`, `/declarations`.
- **Pas de verbes dans l'URL** pour les ressources REST : `/vehicles` (pas `/get-vehicles` ni `/create-vehicle`).
- Les actions non-CRUD utilisent un sous-segment descriptif : `/declarations/{declaration}/generate-pdf`, `/drivers/{driver}/replace`.

### Noms de routes

- **dot notation**, snake_case : `user.vehicles.index`, `user.declarations.show`, `web.auth.login.show`.
- Le préfixe correspond à l'**espace** + au **domaine** : `user.vehicles.create`, `web.home.index`.

### Wayfinder — routes TypeScript typées (côté front)

Côté front Floty, les routes ne sont **jamais** utilisées par leur nom string. Floty utilise **Laravel Wayfinder** qui génère des fonctions TypeScript typées depuis les controllers.

```ts
import VehicleController from '@/actions/App/Http/Controllers/User/VehicleController'

// au lieu de route('user.vehicles.show', { vehicle: id })
VehicleController.show({ vehicle: id })  // → { url, method, ... } typé
```

Voir `inertia-navigation.md` § « Laravel Wayfinder » pour les patterns complets.

### Convention complète Floty

| URL | Nom de route | Contrôleur |
|---|---|---|
| `/` | `web.home.index` | `Web\Home\HomeController` |
| `/mentions-legales` | `web.legal.show` | `Web\MentionsLegales\MentionsLegalesController` |
| `/login` | `web.auth.login.show` | `Web\Auth\LoginController@show` |
| `/login` (POST) | `web.auth.login.store` | `Web\Auth\LoginController@store` |
| `/logout` (POST) | `web.auth.logout` | `Web\Auth\LoginController@destroy` |
| `/dashboard` | `user.dashboard` | `User\Dashboard\DashboardController` |
| `/vehicles` | `user.vehicles.index` | `User\Vehicle\VehicleController@index` |
| `/vehicles/{vehicle}` | `user.vehicles.show` | `User\Vehicle\VehicleController@show` |
| `/declarations/{declaration}/pdf` | `user.declarations.pdf.generate` | `User\Declaration\GenerateDeclarationPdfController` |

---

## Fichiers Vue (Pages, Components, Partials)

### Conventions de chemins

- **Pages Inertia** : `resources/js/Pages/{Espace}/{Domaine}/{PageName}/{PageName}.vue`
  - Exemple : `resources/js/Pages/User/Vehicles/Index/Index.vue`
- **Partials d'une page** : `resources/js/Pages/{Espace}/{Domaine}/{PageName}/Partials/{PartialName}.vue`
  - Exemple : `resources/js/Pages/User/Vehicles/Index/Partials/VehicleFilters.vue`
- **Composants UI Kit** : `resources/js/Components/Ui/{Famille}/{ComponentName}.vue`
  - Exemple : `resources/js/Components/Ui/Button/Button.vue`
- **Composants Domain réutilisables** : `resources/js/Components/Domain/{Domaine}/{ComponentName}.vue`
  - Exemple : `resources/js/Components/Domain/Vehicle/VehicleCard.vue`
- **Layouts** : `resources/js/Components/Layouts/{LayoutName}.vue`
  - Exemple : `resources/js/Components/Layouts/UserLayout.vue`

### Règles de nommage de fichier

- Toujours **PascalCase.vue**.
- Le fichier principal d'une page reproduit le nom du dossier : `Pages/User/Vehicles/Index/Index.vue` (pas `index.vue`).
- Pour les partials, **éviter le préfixe `_`** (convention Nuxt) — ce sont des composants à part entière.

→ Pour la structure complète : voir `structure-fichiers.md`.

---

## Constantes et configuration

### PHP

- **Constantes** en SCREAMING_SNAKE_CASE : `MAX_LOGIN_ATTEMPTS`, `DEFAULT_PAGINATION_SIZE`, `LCD_THRESHOLD_DAYS`.
- **Clés de configuration** en snake_case avec dot notation : `config('mail.default')`, `config('floty.fiscal.lcd_threshold')`.

### TypeScript

- **Constantes** en SCREAMING_SNAKE_CASE pour les vraies constantes globales : `MAX_HEATMAP_VEHICLES = 100`.
- **Constantes locales** en camelCase : `const todayDate = new Date()`.

### Variables d'environnement

- **SCREAMING_SNAKE_CASE** : `APP_NAME`, `DB_CONNECTION`, `MAIL_HOST`, `FLOTY_FISCAL_YEAR_DEFAULT`.
- Les variables exposées au front via Vite sont préfixées `VITE_` : `VITE_APP_VERSION`.

---

## Commentaires et documentation

| Lieu | Langue | Format |
|---|---|---|
| Commentaires PHP (inline, `//`, `/* */`) | **Anglais** | Court, explique le « pourquoi », jamais le « quoi » |
| PHPDoc (`/** */`) | **Anglais** | Annotations `@param`, `@return`, `@throws` exhaustives |
| Commentaires TypeScript / Vue (inline, `//`) | **Anglais** | Court, explique le « pourquoi » |
| JSDoc / TSDoc (`/** */`) | **Anglais** | Pour les fonctions exportées complexes |
| Commentaires SQL (migrations) | **Anglais** | Court |
| Messages de commit | **Anglais** | Format conventional commits recommandé |
| Documentation `project-management/` | **Français** | Cf. style des autres docs |
| Textes UI Vue (`<template>`, labels, messages) | **Français** | Cf. CLAUDE.md |

---

## Résumé

| Élément | Format | Langue | Exemple Floty |
|---|---|---|---|
| Classe PHP | PascalCase | Anglais | `VehicleFiscalCharacteristicsService` |
| Méthode PHP | camelCase, verbe en tête | Anglais | `calculateLcdCumul()` |
| Variable / propriété PHP | camelCase | Anglais | `$pendingAttributions` |
| Constante PHP | SCREAMING_SNAKE_CASE | Anglais | `MAX_RETRY_COUNT` |
| Type TS | PascalCase | Anglais | `VehicleListItemData` |
| Fonction TS | camelCase | Anglais | `formatEuro` |
| Composable TS | `useXxx.ts` | Anglais | `useFiscalYear` |
| Store Pinia TS | `xxxStore.ts` | Anglais | `fiscalYearStore` |
| Composant Vue | PascalCase.vue | Anglais | `VehicleCard.vue` |
| Page Inertia | PascalCase.vue | Anglais | `Index.vue`, `WeeklyEntry.vue` |
| Constante TS globale | SCREAMING_SNAKE_CASE | Anglais | `MAX_HEATMAP_VEHICLES` |
| Table BDD | snake_case, pluriel | Anglais | `vehicles`, `vehicle_fiscal_characteristics` |
| Colonne BDD | snake_case, singulier | Anglais | `is_active`, `effective_from` |
| Enum PHP — case | PascalCase | Anglais | `Draft`, `PluginHybrid` |
| Enum PHP — value | snake_case | Anglais (sauf codes admin FR universels : `VP`, `VU`, `CI`, `WLTP`, etc.) | `'draft'`, `'plugin_hybrid'`, `'VP'` (exception code admin) |
| Route URL | kebab-case, pluriel | Anglais (sauf segments métier français) | `/vehicles`, `/mentions-legales` |
| Route name | dot.notation, snake_case | Anglais | `user.vehicles.show` |
| Fichier migration | snake_case descriptif | Anglais | `create_vehicles_table` |
| Clé config | snake_case, dot notation | Anglais | `floty.fiscal.lcd_threshold` |
| Variable d'env | SCREAMING_SNAKE_CASE | Anglais | `FLOTY_FISCAL_YEAR_DEFAULT` |
| Commentaire code | — | Anglais | — |
| Texte UI (Vue template) | — | Français | « Ajouter un véhicule » |

---

## Cohérence avec les autres règles

- Architecture en couches (Action / Service / Repository / Resource / Composant Vue) : voir `architecture-solid.md`.
- Organisation des fichiers et dossiers : voir `structure-fichiers.md`.
- Bundling Vite et structure des assets : voir `assets-vite.md`.
- Gestion des exceptions et propagation des erreurs : voir `gestion-erreurs.md`.

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 2.1 | 24/04/2026 | Micha MEGRET | Passe d'audit (étape 5.6) — convention E1 (anglais strict pour les enums) appliquée : refonte des exemples (DeclarationStatus avec Draft/Verified/Generated/Sent au lieu de Brouillon/Verifiee/..., EnergySource avec Gasoline/PluginHybrid au lieu de SourceEnergie/Essence/HybrideRechargeable). Ajout de l'exception documentée pour les codes administratifs FR universels (VP, VU, CI, WLTP, etc.). Mise à jour du tableau récapitulatif. Correction des routes incohérentes ligne 353 (`vehicles.index` → `user.vehicles.index`, `web.auth.login` → `web.auth.login.show`). |
| 2.0 | 24/04/2026 | Micha MEGRET | **Refonte complète** pour stack Floty (Laravel 13 + Inertia v3 + Vue 3 + TypeScript 6 + Spatie Data + PHP 8.5). Suppression de Livewire et Blade, ajout des conventions TypeScript / Vue / composables / stores Pinia / utils, ajout des conventions Pages Inertia (avec partials par page), exemples métier Floty (vehicle, attribution, declaration), table de routes Floty, enums français-métier vs anglais-technique, accents harmonisés. |
| 1.0 | mars 2026 | Micha MEGRET | Version initiale, contexte ancien projet Livewire + Alpine + Blade. |
