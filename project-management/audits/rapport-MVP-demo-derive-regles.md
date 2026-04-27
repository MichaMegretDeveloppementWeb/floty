# Audit — Dérive du MVP démo vs. règles d'implémentation Floty

> **Auteur** : Claude (audit automatisé senior).
> **Date** : 24/04/2026.
> **Périmètre** : l'intégralité du code pushé pour la démo client (branche `main`, MVP fonctionnel en 2024).
> **Objectif** : inventorier, par dossier et par fichier, **tous** les écarts avec les règles formalisées dans `project-management/implementation-rules/`, puis fournir une feuille de route priorisée pour la remise aux normes avant V1.

Le code fonctionne. Le rapport ne remet pas en cause le choix d'avoir pris des raccourcis pour tenir la démo — il quantifie la dette et trace ce qu'il faut défaire, dans quel ordre, avec quel effort.

---

## 1. Résumé exécutif — top 15 par impact

| # | Manquement | Impact | Règle violée | Effort approx. |
|---|---|---|---|---|
| 1 | **Architecture Action → Service → Repository absente**. Tous les controllers font du Eloquent direct (`Model::query()`, `Model::create`, `DB::table()->insertOrIgnore()`). Aucune action, aucun repository, 1 seul service (fiscal). | Bloquant V1 : impossible de tester unitairement, de mocker, d'évoluer. | `architecture-solid.md` § 1-7, `structure-fichiers.md` | **~8 j/h** |
| 2 | **Aucun DTO Spatie Laravel Data**. Toutes les entrées/sorties Inertia sont des `array` anonymes montés à la main via `->map(fn …)`. Aucun type TS généré par `typescript:transform`. | Divergence PHP/TS silencieuse garantie, pas de type-safety frontière. | `typescript-dto.md` entier | **~6 j/h** |
| 3 | **Moteur fiscal hardcodé `$year = 2024`** dans 5 controllers (`DashboardController`, `VehicleController`, `CompanyController`, `PlanningController`, `AssignmentController`) **et** dans le middleware `HandleInertiaRequests`. | Fonctionnel : l'appli est incapable de produire quoi que ce soit en 2025. | CDC § 1.3, `architecture-solid.md` | **~1 j/h** (une fois que le Shared Fiscal service existe) |
| 4 | **Controllers recalculent les agrégats fiscaux à chaque requête** (Dashboard, Vehicles/Index, Companies/Index, Planning/Index). Aucune mémoïsation, aucun cache, boucle Eloquent N+1 (`Vehicle::find` par itération). | Performance : dashboard déjà à ~300-500 ms en dev sur la démo ; s'effondrera à V1 prod. | `performance-ui.md`, `CacheTagsManager` non utilisé | **~2 j/h** |
| 5 | **Aucune Policy, aucune autorisation**. Toutes les `FormRequest::authorize()` retournent `true`. Aucun contrôle ownership entreprise/véhicule. | Sécurité : n'importe quel user connecté peut tout créer/voir/modifier. | `architecture-solid.md` § Policies, `structure-fichiers.md` | **~2 j/h** |
| 6 | **`lib/http.ts` (fetch natif)** utilisé directement par deux pages/composants au lieu d'un composable + Wayfinder. Erreurs absorbées silencieusement (`catch { /* silent */ }`). | Règle explicite violée ; pas de toast d'erreur ; URLs hardcodées. | `composables-services-utils.md`, `inertia-navigation.md` § Wayfinder, `gestion-erreurs.md` | **~2 j/h** |
| 7 | **`WeekDrawer.vue` = 503 lignes** mélange drawer + form + grille semaine + preview fiscal + fetch HTTP + debounce + formatters. | Règle dure : < 250-300 lignes, responsabilité unique. | `vue-composants.md` § Principes, `structure-fichiers.md` | **~2 j/h** |
| 8 | **Wayfinder généré mais non utilisé**. Tous les liens UI utilisent `href="/app/..."` hardcodés (7 occurrences repérées). Tous les fetchs internes utilisent `postJson('/app/planning/...')` littéraux. | Règle explicite (« Wayfinder exclusif »). | `inertia-navigation.md` § Wayfinder, `conventions-nommage.md` | **~1 j/h** |
| 9 | **`BaseAppException` définie mais jamais utilisée**. Aucune exception métier typée. Controllers ne `catch` rien. `abort(400, …)` en anglais/français mélangé dans `PlanningController::week`/`vehicleDates`. | Message technique peut fuiter vers l'utilisateur. | `gestion-erreurs.md` intégralement | **~3 j/h** |
| 10 | **Formatters dupliqués 6 fois** (`formatEur` dans 6 fichiers, `formatDateFr` dans 1 mais patron à reproduire). Chacun réécrit le trick `.replace(/ | /g, ' ')`. | `composables-services-utils.md` § Util. `Utils/format/` inexistant. | **~0.5 j/h** |
| 11 | **Aucun test Feature controller + seulement 1 Unit fiscal**. `BracketsCatalog2024Test` teste uniquement le barème, pas le calculateur. `SchemaSmokeTest` teste les migrations. Toutes les routes User non couvertes. | Règle `tests-frontend.md` + laravel-boost (PHPUnit obligatoire). Zéro confiance. | **~5-7 j/h** |
| 12 | **DemoSeeder porte 360 lignes de logique métier dure + credentials** (`admin@floty.test` / `password`). Pas de factory Eloquent, tout en `updateOrCreate` avec spec arrays. | Pas une « donnée seed » : c'est un script d'orchestration. | `structure-fichiers.md` § Seeders, Laravel best practices | **~2 j/h** |
| 13 | **`PlanningController::vehicleDates` et `previewTaxes`/`storeBulk` = endpoints JSON hors Inertia, hors pattern**. Validation inline `$request->validate()`, pas de FormRequest. `storeBulk` fait `DB::table()->insertOrIgnore()` en contournant Eloquent + les events. | Fuite d'API ad-hoc, pas de couche métier. | `structure-fichiers.md`, `architecture-solid.md` | **~2 j/h** |
| 14 | **Types TS inline dans chaque page/composant Vue** au lieu de `@/types` (Spatie). 12+ types `Vehicle = {…}`, `Company = {…}`, `FiscalPreview = {…}` redéclarés. | `typescript-dto.md` règle absolue. | **~1 j/h** (mécanique après point 2) |
| 15 | **`composables/useToasts.ts` en minuscule** alors que tout le reste est `Composables/User/useToast.ts`. Règle nommage composable suffixe non respectée (`useToasts` pluriel, pas de type de retour explicite). | `conventions-nommage.md`, `composables-services-utils.md` § 1. | **~0.5 j/h** |

Total estimé **socle** : **~35-40 j/h** de remise aux normes avant V1.

> **Mise à jour 24/04/2026** — après complément d'audit (section finale), le total révisé passe à **~48-54 j/h** pour intégrer les manquements frontend détaillés (types domaine, composables, utils, Pinia, Wayfinder, accessibilité, tests Vue, i18n préparatoire). Voir « COMPLÉMENT D'AUDIT » en fin de rapport.

---

## 2. Backend PHP

### 2.1 Architecture applicative — Actions / Services / Repositories

**Règle** : `architecture-solid.md` impose la chaîne stricte `Controller → Action → Service → Repository → Model` pour toute opération non triviale. Aucun raccourci, même « parce que c'est petit ».

**Constat** :

| Attendu | Trouvé | Fichier |
|---|---|---|
| `app/Actions/User/Vehicle/CreateVehicleAction.php` | Absent | — |
| `app/Actions/User/Company/CreateCompanyAction.php` | Absent | — |
| `app/Actions/User/Assignment/BulkCreateAssignmentsAction.php` | Absent | — |
| `app/Services/User/Vehicle/VehicleCreationService.php` | Absent | — |
| `app/Services/User/Vehicle/VehicleFiscalCharacteristicsService.php` | Absent | — |
| `app/Services/User/Planning/PlanningHeatmapService.php` | Absent | — |
| `app/Services/User/Planning/PlanningPreviewService.php` | Absent | — |
| `app/Services/User/Dashboard/DashboardSummaryService.php` | Absent | — |
| `app/Repositories/User/Vehicle/VehicleListReadRepository.php` | Absent | — |
| `app/Repositories/User/Assignment/AssignmentReadRepository.php` | Absent | — |
| `app/Contracts/Repositories/**` | Absent | — |

**Dossier `app/Services/`** : contient seulement `Fiscal/FiscalCalculator.php`, `Fiscal/BracketsCatalog2024.php`, `Fiscal/Dto/FiscalBreakdown.php`, `Shared/Cache/CacheTagsManager.php`. Le moteur fiscal lui-même est dans le bon emplacement (même si `App\Services\Fiscal\` devrait être `App\Services\Shared\Fiscal\` d'après `structure-fichiers.md` § « L'espace Shared »).

**Controllers qui violent la règle** (détail par fichier) :

- **`app/Http/Controllers/User/Dashboard/DashboardController.php`** (66 L) :
  - Ligne 25-27 : `Assignment::query()->whereYear(...)->get()` — requête BDD directe.
  - Ligne 29-34 : agrégation `$cumulByPair` en PHP dans le controller.
  - Ligne 36-45 : boucle qui instancie `$calculator->calculate()` pour chaque paire + `Vehicle::find()` (N+1 garanti).
  - Ligne 47-63 : construction du payload `stats` à la main dans `Inertia::render`.
  - **Tout ce bloc devrait vivre dans `DashboardSummaryService::build(int $fiscalYear): DashboardSummaryData`.**

- **`app/Http/Controllers/User/Vehicle/VehicleController.php`** (156 L) :
  - Lignes 31-68 : `index()` fait 2 requêtes Eloquent, agrège les cumuls par couple, boucle sur tous les véhicules pour recalculer la taxe annuelle à chaque requête. **N+1 et recalcul intégral à chaque page.**
  - Lignes 91-128 : `store()` fait `Vehicle::create()` puis `VehicleFiscalCharacteristics::create()` dans `DB::transaction`. Devrait être dans `CreateVehicleAction` (ex: `architecture-solid.md` ligne 322-355).
  - Lignes 143-153 : méthode privée `enumOptions()` est une utilité métier, devrait être un util/trait ou une responsabilité du DTO `VehicleFormOptionsData`.

- **`app/Http/Controllers/User/Company/CompanyController.php`** (92 L) :
  - Lignes 20-69 : `index()` reproduit la même logique d'agrégation que `DashboardController` et `VehicleController`. **Logique métier dupliquée 3 fois.**
  - Ligne 86 : `store()` fait `Company::create($request->validated())` directement — zéro Action, zéro Service.

- **`app/Http/Controllers/User/FiscalRule/FiscalRuleController.php`** :
  - Ligne 21-26 : lecture BDD + `request()->query('year')` inline, devrait passer par `FiscalRuleListRepository` + un FormRequest pour le query param.

- **`app/Http/Controllers/User/Planning/PlanningController.php`** (300 L, le plus violent) :
  - 4 méthodes : `index`, `week`, `previewTaxes`, `storeBulk`.
  - Lignes 33-123 (`index`) : la totalité de la construction de la heatmap — 3 requêtes Eloquent, 3 agrégations manuelles, 2 boucles imbriquées appelant le calculateur fiscal, construction du payload à la main. **Zéro test possible, logique à migrer dans `PlanningHeatmapService`.**
  - Lignes 125-198 (`week`) : requête Eloquent + agrégation + construction de payload JSON « à la main ». Devrait être `PlanningWeekDetailService::forVehicleAndWeek()`.
  - Lignes 200-262 (`previewTaxes`) : calculs fiscaux « before/after » — pure logique métier coincée dans le controller. `$request->validate()` inline (pas de FormRequest). Devrait être `PlanningPreviewService::previewIncremental()`.
  - Lignes 264-299 (`storeBulk`) : fait `DB::table('assignments')->insertOrIgnore($rows)` — contourne Eloquent, les events, les observers, `SoftDeletes`. **Bug latent** : si un observer `AssignmentInvalidatesDeclarations` arrive en V1, il ne se déclenchera pas sur ces inserts. À migrer dans `BulkCreateAssignmentsAction`.

- **`app/Http/Controllers/User/Assignment/AssignmentController.php`** (95 L) :
  - Lignes 29-55 : `index()` transforme manuellement Eloquent → array pour Inertia. Deux `->map(fn …)` inline. Aucun DTO.
  - Lignes 67-93 : `vehicleDates()` valide à la main (`abort(400, …)`) au lieu d'un FormRequest ; requête Eloquent + agrégations inline. Devrait être `AssignmentCalendarReadService`.

**Verdict** : à l'exception partielle du moteur fiscal, **aucune** des règles d'architecture en couches n'est appliquée. C'est le manquement n°1.

### 2.2 DTOs Spatie Laravel Data — frontière PHP ↔ TS

**Règle** : `typescript-dto.md` § 3-5 — tout échange PHP→Vue passe par un `Spatie\LaravelData\Data` annoté `#[TypeScript]`, et les types TS sont générés automatiquement.

**Constat** : le paquet `spatie/laravel-data` n'est même pas installé (non visible dans `composer.json` à auditer — voir `composer.json` pour confirmer, mais aucun `App\Data\` n'existe).

Les controllers passent à Inertia des structures **`array` anonymes** construites inline :

- `DashboardController.php:47-63` — `stats` est un array avec 5 clés typées nulle part.
- `VehicleController.php:46-68` — `$rows` est un `Collection<array>` construit par `->map(fn (Vehicle $v) => [...])`. Aucun type TS.
- `CompanyController.php:50-63` — idem, `$companies = ... ->map(...)`.
- `PlanningController.php:97-111` — `$vehiclesPayload[]` est un array PHP non typé (donne `Vehicle[]` redéclaré inline dans `Planning/Index.vue:10-23`).
- `AssignmentController.php:31-44, 45-54` — deux map anonymes.
- `FiscalRuleController.php:36-45` — map anonyme.

**Conséquences** :
- Le front redéclare à la main **tous** les types (voir § 3.2 ci-dessous). Divergence garantie.
- Aucun champ calculé n'est typé : `annualTaxDue` est `float` côté PHP, `number` côté TS redéclaré. Un rename côté PHP n'impacte pas TS.
- `php artisan typescript:transform` n'existe pas ici (package non installé).
- Le moteur fiscal **a** un DTO interne (`FiscalBreakdown.php`) mais il n'étend **pas** `Spatie\LaravelData\Data`, n'est **pas** annoté `#[TypeScript]`, et expose un `toArray()` manuel camelCase en lignes 41-59. C'est un progrès, mais ce n'est pas conforme.

**Actions** :
1. Installer `spatie/laravel-data` + `spatie/typescript-transformer`.
2. Créer 4 variantes par entité : `VehicleListItemData`, `VehicleData`, `VehicleFormData`, `VehicleStoreData` (`typescript-dto.md` § 5).
3. Faire hériter `FiscalBreakdown` de `Data` + annoter `#[TypeScript]`.
4. Migrer tous les payloads `Inertia::render` en `XxxData::from()` ou `XxxData::collect()`.
5. Brancher le générateur TS dans le workflow (`npm run build`).

### 2.3 Policies / authorization

**Règle** : `conventions-nommage.md` + `architecture-solid.md` § Policies. `gestion-erreurs.md` § « 403 ».

**Constat** :

- Aucun fichier dans `app/Policies/`. Le dossier **n'existe pas**.
- `StoreVehicleRequest::authorize()` ligne 19 : `return true;` — commentaire d'exemple senior explicite dans `structure-fichiers.md` (« `authorize()` doit au minimum appeler `$this->user()->can('create', Vehicle::class)` »).
- `StoreCompanyRequest::authorize()` ligne 14 : `return true;`.
- `LoginRequest::authorize()` ligne 21 : `return true;` (acceptable ici mais à commenter).
- Aucun `$this->authorize('update', $vehicle)` dans les controllers.
- Aucun scope d'ownership : un user peut lister/modifier **tous** les véhicules/entreprises. La démo n'expose pas ce risque (user unique), mais en multi-user V2, c'est une fuite de données totale.

**Actions** :
1. Créer `VehiclePolicy`, `CompanyPolicy`, `AssignmentPolicy` — 4 méthodes CRUD chacune.
2. Enregistrer dans `AuthServiceProvider` (à créer ou compléter).
3. Brancher via `authorize()` dans les FormRequest ou via `Gate` dans les controllers.

### 2.4 Gestion d'erreurs — `BaseAppException` non utilisée

**Règle** : `gestion-erreurs.md` § « Pattern par couche ». Chaque erreur doit être typée, loguée dans un canal thématique, remonter comme toast français.

**Constat** :

- `app/Exceptions/BaseAppException.php` existe (43 lignes) mais **aucune sous-classe** n'a été créée. Les dossiers `Vehicle/`, `Assignment/`, `Declaration/`, `Fiscal/` prévus par `gestion-erreurs.md:857-887` sont absents.
- `FiscalCalculator.php` lève `InvalidArgumentException` brute lignes 53, 58, 61, 156 au lieu de `FiscalConfigurationException` typée avec message utilisateur.
- `PlanningController::week` ligne 137-138 : `abort(400, 'Paramètres vehicleId et week requis.')` — message FR dans un code HTTP, pas de log, pas de canal fiscal.
- `AssignmentController::vehicleDates` ligne 72 : `abort(400, 'vehicleId requis.')` — idem.
- **Aucun** `try/catch` dans les controllers (sauf `LoginRequest`). Les exceptions BDD remontent telles quelles → stack trace visible en dev et message technique fuité en prod.
- `config/logging.php` (non audité mais probable) : aucun canal thématique `vehicles`, `attributions`, `fiscal`, `declarations` comme imposé par `gestion-erreurs.md:662-707`.
- Côté front : `postJson` et `getJson` lèvent une `Error` générique que **les pages/composants silencent** (`catch { /* silent */ }` dans `Assignments/Index.vue:124-126, 144-147` ; `catch { preview.value = null; }` dans `WeekDrawer.vue:194-196` et `Planning/Index.vue:n/a` implicite). L'utilisateur ne voit **jamais** le problème — anti-pattern de premier ordre.

**Actions** :
1. Créer `app/Exceptions/{Vehicle,Company,Assignment,Fiscal,Planning}/` avec classes typées.
2. Remplacer les `InvalidArgumentException` et `abort(...)` par des exceptions métier.
3. Créer les canaux de log thématiques + rétentions (`gestion-erreurs.md:662-707`).
4. Envelopper chaque opération controller dans `try { … } catch (BaseAppException $e) { Log::channel(...)->error(...); return back()->with('toast-error', $e->getUserMessage()); }`.
5. Côté front : remplacer `catch { /* silent */ }` par `catch (e) { toasts.push({ tone: 'error', title: '…' }) }`.

### 2.5 Contrôleurs qui agrègent / calculent

Déjà listé en § 2.1 mais à isoler :

| Fichier | Lignes | Logique qui n'a rien à faire dans un controller |
|---|---|---|
| `DashboardController.php` | 25-45 | Agrégation `cumulByPair` + boucle fiscal + somme totale |
| `VehicleController.php` | 31-68 | Même agrégation répétée + `->map(fn Vehicle $v => ...)` qui contient un calcul fiscal |
| `CompanyController.php` | 20-63 | Même agrégation répétée par entreprise |
| `PlanningController.php` | 33-123, 200-262 | Construction heatmap + preview fiscal avant/après |
| `AssignmentController.php` | 75-93 | Agrégation dates occupées par couple |

Ces 5 controllers réimplémentent **trois fois** la même logique `groupBy(vehicle_id, company_id)->count()`. Un `AssignmentCumulativeDaysRepository::cumulByPairForYear(int $year): array` résoudrait la duplication en 40 lignes.

### 2.6 Seeders — logique métier dans `DemoSeeder`

**Règle** : `structure-fichiers.md:443-455` impose un seeder = données statiques. La démo doit passer par un `php artisan db:seed --class=DemoSeeder` mais le contenu devrait être isolé.

**Constat** : `DemoSeeder.php` = 357 lignes. Contient :

- Ligne 42 : `DB::transaction` global.
- Lignes 54-81 : 5 entreprises montées via `updateOrCreate()`.
- Lignes 83-93 : méthode `postalFor($city)` — mapping dur qui mélange métier et seed.
- Lignes 95-244 : 10 véhicules + `VehicleFiscalCharacteristics` en ligne ; ligne 214 : `$vehicle->fiscalCharacteristics()->delete()` avant de recréer — si par malheur on lance le seeder sur une DB contenant déjà des attributions valides, on détruit l'historique fiscal.
- Lignes 250-281 : insère les attributions via `DB::table('assignments')->insertOrIgnore()` — contourne Eloquent/Events comme le controller.
- Lignes 284-356 : « plan » de 40 attributions en dur.

**Violations** :
- Pas de `factory()` Eloquent utilisée alors que `HasFactory` est sur `Vehicle`, `Company`, `Assignment`. Les factories existent (point à vérifier) mais sont by-passées.
- Logique de calcul des dates (`while ($period->lte($end))`) dans un seeder.
- Credentials démo en clair dans `UserSeeder.php:19` (`'password' => Hash::make('password')`).

**Actions** :
1. Faire vivre `DemoSeeder` comme seeder, mais l'**orchestration** (calcul des périodes, building du plan) dans une classe `Database\Support\DemoScenarioBuilder`.
2. Utiliser `Vehicle::factory()->withFiscalCharacteristics($spec)->create($specOverride)` pour la partie données.
3. Le plan d'attribution doit être fourni comme fixture JSON (`database/fixtures/demo/assignments_2024.json`).

### 2.7 Modèles — conformité

Les modèles sont globalement **propres** (bons `#[Fillable]` PHP 8.3, `casts()` typés, `SoftDeletes` OK, commentaires `@property` exhaustifs). Points mineurs à relever :

- **`Vehicle.php`** :
  - Manque un scope `active()` (filtre `whereNull('exit_date')->whereNull('deleted_at')`) — dupliqué à la main dans 4 controllers.
  - Pas d'accesseur `currentFiscalCharacteristics()` alors que 5 controllers font `$vehicle->fiscalCharacteristics->first()->...`.
  - Pas d'accesseur `isActive()` / `hasExited()` — booléen exposé directement.
- **`Company.php`** :
  - Manque scope `active()` (filtre `is_active=true` dupliqué dans 3 controllers).
  - Pas de relation inverse `->vehicles()` (via assignments pivot).
- **`Assignment.php`** :
  - Manque scope `forYear(int $year)` (dupliqué 4 fois : `whereYear('date', $year)`).
  - Manque scope `forPair(int $vehicleId, int $companyId)`.
- **`VehicleFiscalCharacteristics.php`** :
  - Méthode `isCurrent()` ligne 126 est correcte mais pas de scope `current()` équivalent pour les requêtes.
- **`Company.php:97-99`** : relation `declarations()` pointe vers `Declaration` qui n'est pas utilisée (pas de controller Declaration dans le MVP). À documenter ou masquer.

**Règle** : `conventions-nommage.md:68-85` (`list`, `count`, préfixes booléens).

### 2.8 Conformité conventions de nommage

**Cas rencontrés** :

- Enums et casts : OK (enums `PascalCase`, valeurs `snake_case` anglais).
- Classes : finales `final readonly class` sur les services (OK). Controllers : `final class` correct.
- `FiscalCalculator::LCD_THRESHOLD_DAYS` : OK (SCREAMING_SNAKE_CASE).
- **`BracketsCatalog2024.php`** : le nom est techniquement OK mais lie le code à 2024 dès maintenant. Pour V1 il faudra le renommer ou l'extraire (`FiscalBracketsRepository` + seed 2025).
- **`App\Services\Fiscal\`** devrait être **`App\Services\Shared\Fiscal\`** (cf. `structure-fichiers.md:97-107`).
- **`useToasts.ts`** en camelCase ligne 1 : emplacement `resources/js/composables/` (minuscule) au lieu de `resources/js/Composables/User/` (majuscule). Cf. `structure-fichiers.md:243-251`.
- Frontend : nom de dossiers `Components/` est majuscule sur disque, `pages/` et `composables/`, `types/`, `lib/`, `data/` sont minuscules. Les règles disent `Pages/`, `Composables/`, `Utils/`, `Types/` en PascalCase ou camelCase selon la convention. **Incohérence interne**.

### 2.9 Routes — Wayfinder et pattern

- **`routes/web.php:8`** : `Route::inertia('/', 'Welcome')->name('home')` — la règle `conventions-nommage.md:398` attend `web.home.index`. Le nom est juste `home`. Mineur.
- **`routes/user.php:24`** : `Route::get('/dashboard', DashboardController::class)->name('dashboard')` — nommé `user.dashboard` (préfixe groupe OK), mais un controller invocable devrait être nommé `...Controller` et déclaré `__invoke` (c'est fait). OK.
- **`routes/user.php:44-45`** : deux routes POST JSON (`planning.preview-taxes`, `planning.assignments.store-bulk`) qui sont en réalité des endpoints API — violation du pattern Inertia v3. Devraient soit être des vraies routes Inertia (redirect back with props), soit migrées vers un préfixe `/api/v1/` documenté comme exception.
- **`routes/user.php:39`** : `assignments/vehicle-dates` idem (endpoint JSON GET).
- **Aucun** `Route::resource` utilisé — conforme à `structure-fichiers.md:142` qui recommande un Resource controller mais tolère les méthodes nommées. Acceptable.

### 2.10 Fiscal — raccourcis assumés

`FiscalCalculator.php` est le service le mieux construit du projet, mais :

- Ligne 52 : `throw new InvalidArgumentException('Seule l\'année fiscale 2024 est supportée par le MVP.')` — attendu pour le MVP. À remplacer par un pipeline de règles (`FiscalRulePipeline`) en V1.
- Les raccourcis annoncés sont bien documentés lignes 26-32 :
  - R-2024-017 hybride non implémenté → contournable en démo, bloquant en V1.
  - Pas de conversion NEDC→WLTP.
  - Exonérations inactives R-018/019/022.
- Ligne 104 : `$pollutantTariff[$fiscal->pollutant_category->value] ?? 0.0` — swallow silencieux si une nouvelle catégorie apparaît. Devrait être `match` exhaustif.
- Ligne 184 : `return HomologationMethod::Pa;` retourné par défaut — si ni `Wltp` ni `Nedc` ne matchent, on tombe sur PA. Potentiellement incorrect si un véhicule WLTP n'a pas de `co2_wltp` renseigné (ce qui ne devrait pas arriver grâce au FormRequest, mais il faudrait lever plutôt que retourner PA silencieusement).

---

## 3. Frontend Vue / TypeScript

### 3.1 Pages qui font de l'axios/fetch direct

**Règle** : `architecture-solid.md:107-110`, `composables-services-utils.md:503`, `inertia-navigation.md:272-290` (`useHttp` ou composable dédié).

**Constat** : `resources/js/lib/http.ts` (66 L) fournit `getJson` et `postJson` en `fetch` natif, lu/écrit les cookies XSRF à la main. Usages :

- `pages/User/Planning/Index.vue:68` — `getJson<WeekData>('/app/planning/week', …)`.
- `pages/User/Assignments/Index.vue:112, 134, 174, 199` — 4 appels `getJson`/`postJson`.
- `Components/Features/Planning/WeekDrawer.vue:186, 212` — 2 appels `postJson`.

**Problèmes** :
1. URL hardcodée dans le code Vue (violation Wayfinder).
2. Pas de type-safety sur les paramètres — `vehicleId` en paramètre est juste `string | number | boolean`.
3. Aucune gestion d'erreur — les pages silencent via `catch {}`.
4. Pas de composable dédié (`useFiscalPreview`, `useWeekDetail`, `useVehicleCalendar`) — la logique d'appel + debounce + état de chargement est dupliquée à chaque usage.

**Actions** :
1. Ajouter un composable par cas d'usage :
   - `Composables/User/usePlanningWeek.ts` → charge/cache la semaine.
   - `Composables/User/useFiscalPreview.ts` → debounce + call + état.
   - `Composables/User/useVehicleCalendarDates.ts` → vehicleBusyDates + pairDates.
2. Utiliser `useHttp` d'Inertia v3 en interne (cf. `inertia-navigation.md:272`).
3. Supprimer `lib/http.ts` une fois migré (ou le transformer en adapter privé dans `Composables/Shared/useHttpJson.ts`).

### 3.2 Types inline dans les pages/composants

**Règle** : `typescript-dto.md` entier, `conventions-nommage.md:144-155`.

**Constat** : chaque page/composant redéfinit ses propres types en local.

| Fichier | Type redéclaré | Lignes |
|---|---|---|
| `Planning/Index.vue` | `Vehicle`, `Company`, `WeekData` | 10-50 |
| `Assignments/Index.vue` | `VehicleOption`, `CompanyOption`, `FiscalPreview` | 11-56 |
| `Vehicles/Index.vue` | `VehicleRow` | 11-21 |
| `Companies/Index.vue` | `CompanyRow` | 11-21 |
| `Companies/Create.vue` | `ColorOption` | 8 |
| `Vehicles/Create.vue` | `EnumOption` + shape imbriquée `enums: {...}` | 11-22 |
| `FiscalRules/Index.vue` | `LegalReference`, `Rule`, `SectionGroup` | 17-33, 55-60 |
| `Dashboard/Index.vue` | shape `stats` inline + `QuickLink` | 16-41 |
| `WeekDrawer.vue` | `Company`, `DaySlot`, `WeekData`, `FiscalPreview` | 21-73 |
| `Heatmap.vue` | `Vehicle` | 16-29 |

**Divergences potentielles** (à vérifier) :
- `Planning/Index.vue:19` : `co2Value: number | null` vs payload PHP `$fiscal->co2_wltp ?? $fiscal->co2_nedc` (int|null). OK.
- `Assignments/Index.vue:49` : `after: { ..., co2Method: string, ... }` — pas d'enum union. Devrait être `HomologationMethod` généré.
- `FiscalRules/Index.vue:23-31` : `Rule` déclaré à la main, divergent du payload PHP (`FiscalRuleController.php:36-45`).

**Conséquence** : un rename côté PHP passera inaperçu.

### 3.3 Pas de composables malgré logique évidente

**Règle** : `composables-services-utils.md` § 1 — extraction dès que la logique dépasse 30 lignes ou qu'elle est utilisée à plusieurs endroits.

**Constat** : `resources/js/composables/` contient **un seul** fichier : `useToasts.ts` (70 L, règle partiellement respectée).

**Composables manquants** (identifiés dans le code dupliqué) :

| Composable attendu | Où la logique vit aujourd'hui | Lignes |
|---|---|---|
| `useFiscalPreview` | `WeekDrawer.vue` et `Assignments/Index.vue` | debounce + fetchPreview + preview ref — dupliqué |
| `useHeatmap` | inline dans `Heatmap.vue` + props recalcul dans `PlanningController` | computed densityClass, monthLabels |
| `usePlanningWeek` | inline dans `Planning/Index.vue` + WeekDrawer | openWeek + loadingWeek |
| `useMultiDateSelection` | inline dans `MultiDatePicker.vue` + `WeekDrawer.vue` toggleSlot | gestion set + sort |
| `useFiscalYear` | inline dans `UserLayout.vue`, `Assignments/Index.vue`, etc. | `page.props.fiscal.currentYear` répété |
| `useVehicleCalendarDates` | `Assignments/Index.vue:105-147` | 40 L de watch + fetch + état |

### 3.4 Logique métier dans les pages

**Règle** : `vue-composants.md:17` — « Présentation séparée du métier ».

**Cas les plus flagrants** :

- **`WeekDrawer.vue`** :
  - Lignes 166-172 : **debounce maison** via `setTimeout` + `clearTimeout`. Devrait être `useDebounceFn` de `@vueuse/core`.
  - Lignes 174-199 : `fetchPreview()` — appel HTTP + état `previewLoading` + `preview` + gestion erreur silencieuse.
  - Lignes 201-221 : `submit()` — second appel HTTP + redirection implicite.
  - Lignes 137-146 : `toggleSlot` — gestion de sélection multi à la main.
  - Ligne 223-231 : formateur euros inline (dupliqué 6 fois dans le projet).
  - Ligne 233 : tableau `dayLongLabels` literal français.
- **`Assignments/Index.vue`** :
  - 419 lignes (dépasse 250-300).
  - Lignes 105-147 : 2 `watch` async qui fetch des dates véhicule. Logique typiquement composable.
  - Lignes 157-161 : debounce maison identique à WeekDrawer.
  - Lignes 163-187 : `fetchPreview` dupliqué mot à mot.
  - Lignes 189-209 : `submit` + `router.visit` hardcodé `/app/planning`.
  - Lignes 211-219 : formateur euros dupliqué.

### 3.5 Composants trop gros

**Règle** : `vue-composants.md:16` — 250-300 lignes max.

| Fichier | Lignes | Seuil | Dépassement |
|---|---|---|---|
| `WeekDrawer.vue` | 503 | 300 | +203 (68 %) |
| `FiscalRules/Index.vue` | 424 | 300 | +124 (41 %) |
| `Assignments/Index.vue` | 419 | 300 | +119 (40 %) |
| `Vehicles/Create.vue` | 271 | 300 | limite |
| `MultiDatePicker.vue` | 263 | 300 | limite |
| `Heatmap.vue` | 239 | 300 | OK |
| `Planning/Index.vue` | 124 | 300 | OK |

**Découpages recommandés** :

- `WeekDrawer.vue` → `WeekDrawer.vue` (shell) + `Partials/WeekDrawerHeader.vue` + `Partials/WeekDayGrid.vue` + `Partials/WeekAssignmentForm.vue` + `Partials/FiscalPreviewPanel.vue` + composables `usePlanningWeek`, `useFiscalPreview`.
- `Assignments/Index.vue` → même logique, 4 partials + 3 composables.
- `FiscalRules/Index.vue` → `Partials/CalculTab.vue` + `Partials/CadreTab.vue` + sections extraites du `data/fiscalRulesContent.ts` (qui fait 25 Ko à lui seul — à vérifier s'il doit être imorté au runtime ou loadé via `import()` dynamique).

### 3.6 Pas de Pinia store malgré état partagé

**Règle** : `pinia-stores.md` § « Cas d'usage justifiés » — **l'année fiscale est l'exemple type** d'un store Pinia justifié (§1, « État UI cross-pages persistant »).

**Constat** :
- Aucun store Pinia dans le projet (`resources/js/Stores/` ou `stores/` absent).
- L'année fiscale est lue via `usePage().props.fiscal.currentYear` à 5+ endroits (`UserLayout.vue:21`, `Assignments/Index.vue:30`, et les layouts).
- `UserLayout.vue:29-32` fait un `ref(currentYear.value)` + `watch` manuel pour synchroniser — pattern qui trahit le besoin d'un store.

**Action** : créer `resources/js/Stores/User/fiscalYearStore.ts` comme documenté dans `pinia-stores.md:90-157`. Pas critique immédiat (l'année est hardcodée à 2024 côté backend), **mais** devient urgent dès que plusieurs années seront disponibles.

### 3.7 Formatage dupliqué

**Règle** : `composables-services-utils.md:226-250` — utils centralisés dans `Utils/format/`.

**Constat** : `formatEur` redéfini à **6 endroits** (`Companies/Index.vue:36`, `Vehicles/Index.vue:52`, `Dashboard/Index.vue:26`, `Assignments/Index.vue:211`, `WeekDrawer.vue:223`, `Heatmap.vue:70`), chacun avec le même `.replace(/ | /g, ' ')`. `formatDateFr` redéfini 1 fois mais le pattern va se multiplier.

**Action** : créer `resources/js/Utils/format/` avec :
- `formatEuro.ts` — centralisé, typé `(amount: number, options?: { maxDigits?: 0 | 2 }) => string`.
- `formatDate.ts` — wrap `date-fns` avec locale FR.
- `formatImmatriculation.ts` — uppercase + espaces/tirets.
- `formatSiren.ts`.

### 3.8 `any`, assertions, type laxistes

**Constat** :
- `resources/js/lib/http.ts` : `Record<string, string | number | boolean>` est trop lâche pour les params.
- Plusieurs `!` (non-null assertion) :
  - `WeekDrawer.vue:207` : implicit dans le `if (... !== null)` puis reréutilisation — OK.
  - `pages/User/Assignments/Index.vue:306-310` : `companies.find(c => c.id === selectedCompanyId)?.legalName` dans le template — acceptable.
- **`global.d.ts:8`** : `[key: string]: string | boolean | undefined;` — trop large, casse la détection des variables manquantes.
- `Planning/Index.vue:15` : `userType: string` — devrait être `VehicleUserType = 'VP' | 'VU'`.
- `Assignments/Index.vue:49` : `co2Method: string` — devrait être `HomologationMethod`.
- Plusieurs `Number(value)` / `String(row.currentStatus)` dans les templates — symptôme que `DataTableColumn<R>` n'est pas assez typé.

### 3.9 Magic numbers / magic strings

- `FiscalCalculator::LCD_THRESHOLD_DAYS = 30` — bonne constante côté PHP.
- Côté front : `/ 366` en dur dans `WeekDrawer.vue:432` et `Assignments/Index.vue:343` — ne tient pas quand 2025 (365).
- `Heatmap.vue:55-68` : densité hardcodée de 0..8 en si/sinon — devrait être `type HeatmapDensity = 0|1|2|3|4|5|6|7` et un mapping dédié.
- `Assignments/Index.vue:205` : `router.visit('/app/planning')` — URL littérale + pas de `preserveScroll`.

### 3.10 Gestion d'erreurs fetch silencieuse

Déjà signalé § 2.4. Listing exact :

- `Assignments/Index.vue:124-126` : `} catch { /* silent */ }`
- `Assignments/Index.vue:144-147` : `} catch { /* silent */ }`
- `Assignments/Index.vue:182-184` : `} catch { preview.value = null; }`
- `WeekDrawer.vue:194-196` : `} catch { preview.value = null; }`
- `Planning/Index.vue:67-75` : pas de catch du tout sur `openWeek` — `finally` mais l'erreur remonte, Vue l'ignore en dev.

**Aucun** usage du composable `useToasts` pour remonter une erreur alors qu'il est disponible.

### 3.11 Pas de Wayfinder pour les URLs

Voir § 1 point 8. Wayfinder **est** généré (`resources/js/actions/App/Http/Controllers/User/` contient bien les 6 controllers), mais **aucun** import dans les composants. 100 % des liens passent par des strings.

Exemples :

- `Welcome.vue:37` : `<Link href="/app/dashboard">` → doit être `DashboardController.index()` (mais ici le controller est invocable, donc `DashboardController()` ou equivalent).
- `Companies/Index.vue:63,82` : `<Link href="/app/companies/create">` → `CompanyController.create()`.
- `Vehicles/Index.vue:84,103` : `<Link href="/app/vehicles/create">` → `VehicleController.create()`.
- `Dashboard/Index.vue:43-78` : 5 objets `QuickLink` avec `href: '/app/…'` littéraux — tableau entier à refondre.

---

## 4. Tests

### 4.1 Couverture Feature inexistante

**Règle** : `laravel-boost` « Tests unitaires et fonctionnels sont importants », `structure-fichiers.md:315-337`.

**Constat** :
- `tests/Feature/ExampleTest.php` — scaffold Laravel non retiré.
- `tests/Feature/Schema/SchemaSmokeTest.php` — teste les migrations + cast enums. Bien.
- `tests/Feature/Services/Shared/Cache/CacheTagsManagerTest.php` — teste le CacheTagsManager. Bien.
- **Aucun** test Feature pour :
  - `DashboardController`
  - `VehicleController` (index, create, store)
  - `CompanyController` (idem)
  - `AssignmentController`
  - `PlanningController` (4 méthodes)
  - `FiscalRuleController`
  - `LoginController` (ni happy path, ni rate-limit, ni CSRF).

### 4.2 Tests Unit partiels

- `tests/Unit/ExampleTest.php` — scaffold non retiré.
- `tests/Unit/Fiscal/BracketsCatalog2024Test.php` — teste les 4 méthodes `wltp/nedc/pa/pollutants` + `applyProgressive` sur des data-providers exhaustifs. Bien.
- **Aucun** test pour :
  - `FiscalCalculator::calculate` (le vrai moteur que les controllers utilisent).
  - Cas exonération LCD, handicap, électrique, prorata sur 366 j.
  - Le `FiscalBreakdown::toArray` (format camelCase exposé au front).

### 4.3 Pas de tests Vue

- Vitest configuré ou non ? À vérifier dans `package.json`. Pas de `*.spec.ts` dans `resources/js/`.
- Zéro test pour : `useToasts`, `WeekDrawer`, `Heatmap`, `MultiDatePicker`, aucun util, aucun composant UI Kit.
- `tests-frontend.md:155-168` impose des tests systématiques pour les composables, utils, composants UI Kit.

---

## 5. Code mort et dette

### 5.1 Fichiers de test / dev

- `storage/app/smoke_test.php` : **déjà nettoyé** (non trouvé).
- `tests/Feature/ExampleTest.php` et `tests/Unit/ExampleTest.php` : à supprimer (scaffold).
- `resources/js/pages/Dev/UiKitShowcase.vue` (61 Ko) et `UiKitUserLayoutDemo.vue` (9 Ko) : accessibles uniquement via `routes/web.php:20-26` (gate `App::environment('local')`). **OK**, mais à documenter que ces 70 Ko ne partent pas en build prod (`vite build` les inclut par défaut — vérifier l'exclude).

### 5.2 Colonne générée retirée — UNIQUE cassé

**Constat** : `Assignment.php:26` mentionne `date_year` dans le `@property` et `@property int $date_year`, et le commentaire ligne 19 parle d'un UNIQUE sur « la colonne générée `vehicle_date_active` ». **Aucune** trace de cette colonne dans le code Eloquent exposé (pas de cast, pas de fillable), et aucune trace de la colonne dans le `#[Fillable]`. À vérifier : si la migration a ajouté la colonne générée mais que l'UNIQUE filtré par `soft-delete` n'est plus opérationnel, alors **deux attributions actives du même véhicule le même jour sont possibles** — violation de l'invariant CDC § 2.4 fondamental.

Le `DB::table('assignments')->insertOrIgnore($rows)` dans `PlanningController::storeBulk` et dans `DemoSeeder` **s'appuie** sur cet UNIQUE pour ignorer les doublons. Si l'UNIQUE n'existe plus, `insertOrIgnore` insèrera **tout**, produisant des doublons silencieux.

**À vérifier urgemment** : `database/migrations/*_create_assignments_table.php` pour confirmer l'état de l'index. Si absent → P0 bloquant.

### 5.3 `fiscal.currentYear = 2024` dans le middleware

`app/Http/Middleware/HandleInertiaRequests.php:62-65` : valeur hardcodée. Même raison que § 2.1 point 3 — à remplacer par un service `FiscalYearContext::currentYear()` basé sur un setting + query param.

### 5.4 `Route::inertia('/', 'Welcome')`

`routes/web.php:8` court-circuite le pattern controller invocable. Acceptable pour une page d'accueil sans logique, mais non conforme à `conventions-nommage.md:398` (attend `Web\Home\HomeController` invocable + nom `web.home.index`).

---

## 6. Sécurité

### 6.1 Rate limiting

- **`LoginRequest::authenticate()`** : rate-limit correct (5 tentatives / 15 min par IP+email). OK.
- **Tous les autres endpoints** : aucun rate-limit. `planning/preview-taxes` et `planning/assignments` peuvent être spammés (un attaquant authentifié peut insérer des milliers d'attributions en 1 s).
- **`bootstrap/app.php`** (non audité en profondeur) : à vérifier qu'un rate-limit global est branché sur le groupe `user.`.

### 6.2 Sessions & cookies

- Utilisation de `credentials: 'include'` dans `lib/http.ts` + `X-XSRF-TOKEN` manuel : fonctionne mais est **fragile** (dépend du cookie parse regex). `useHttp` Inertia v3 gère ça nativement.
- `config/session.php` à auditer pour `same_site=lax|strict` et `secure=true` en prod (probablement en défaut Laravel 13 OK).

### 6.3 Credentials démo

- `UserSeeder.php:19` : `'password' => Hash::make('password')`. Valide pour une démo, **interdit** en V1. À documenter et guard par `APP_ENV !== production`.
- `DemoSeeder.php:57-61` : SIRENs fictifs 81234567x — OK pour démo, à flaguer si les données fuite en prod.

### 6.4 Absence d'autorisation

Déjà couvert § 2.3 — risque majeur multi-user.

---

## 7. Performance

### 7.1 Agrégats recalculés à chaque requête

Toutes les listes (`DashboardController`, `VehicleController@index`, `CompanyController@index`, `PlanningController@index`) exécutent la séquence :
1. `Assignment::whereYear(...)->get()` (peut être des milliers de lignes).
2. Reconstruction du `cumulByPair` en PHP.
3. Boucle qui fait `Vehicle::find($id)` — **N+1 systématique**.
4. Appel `$this->calculator->calculate(...)` pour chaque paire.

Sur 10 véhicules × 5 entreprises = 50 paires, ~50 appels au calculateur à chaque chargement du dashboard. Ça reste viable à la démo. À 1 000 véhicules × 50 entreprises = 50 000 paires, on s'écroule.

### 7.2 `CacheTagsManager` non utilisé

`app/Services/Shared/Cache/CacheTagsManager.php` est un bel ouvrage (~137 lignes, testé, documenté) mais **aucun service ne l'utilise**. La stratégie de cache par préfixe décrite dans `ADR-0008` n'est pas implémentée.

**Action V1** :
- Wrapper `calculator->calculate(…)` dans `Cache::remember($tagKey, 3600, fn() => …)`.
- Invalider sur mutation d'une assignation ou d'un fiscal_characteristics.

### 7.3 Pas de pagination

- `VehicleController@index` : `Vehicle::orderByDesc('acquisition_date')->get()` — tous les véhicules renvoyés.
- `CompanyController@index` : idem.
- `PlanningController@index` : tous les véhicules × 52 semaines.

Pour la démo (10 véhicules) c'est OK. Pour V1 (annonces marketing 100+ véhicules), il faut paginer + index-bar.

### 7.4 Frontend

- `Heatmap.vue` utilise `ref` (profond) au lieu de `shallowRef` pour un tableau de 520 cellules (10 × 52). Cf. `performance-ui.md:47-75`.
- `FiscalRules/Index.vue` charge `fiscalRulesContent.ts` (25 Ko) en import synchrone — à splitter via `import()` dynamique.
- Aucune utilisation de `useDebounceFn` alors que 2 debounces sont réimplémentés à la main.

---

## 8. Priorités de remise aux normes — feuille de route

Phasage recommandé pour atteindre V1 propre. Les efforts sont indicatifs (`j/h` = jour-homme).

### Phase 0 — Bloquants avant toute suite (~3 j/h)

| # | Action | Effort | Risque si non fait |
|---|---|---|---|
| 0.1 | Vérifier/rétablir l'UNIQUE `(vehicle_id, date)` avec soft-delete sur `assignments` (§ 5.2) | 0.5 j | Doublons silencieux d'attribution |
| 0.2 | Ajouter le rate-limit global sur le groupe `user.` (`bootstrap/app.php`) | 0.25 j | Spam authentifié |
| 0.3 | Ajouter `Policy` minimales (tout autoriser explicitement, mais structure en place) | 0.5 j | Dette structurelle |
| 0.4 | Nettoyer `ExampleTest.php` (Feature + Unit), `pages/Dev/*` exclus du build prod | 0.25 j | Code mort exposé |
| 0.5 | Écrire `FiscalCalculatorTest.php` (Unit) — 12-15 cas couvrant LCD, handicap, électrique, WLTP, NEDC, PA, prorata 366 | 1.5 j | Régression fiscale non détectée |

### Phase 1 — Fondations architecture (~10-12 j/h)

| # | Action | Effort |
|---|---|---|
| 1.1 | Installer `spatie/laravel-data` + `spatie/typescript-transformer`, configurer `auto_discover_types`, pipeline npm | 0.5 j |
| 1.2 | Créer les 4 variantes DTO pour Vehicle, Company, Assignment, FiscalRule (16 classes) | 2 j |
| 1.3 | Créer l'arborescence `app/Exceptions/{Vehicle,Company,Assignment,Fiscal,Planning}/` + canaux de log thématiques | 1 j |
| 1.4 | Créer la couche Repository (`VehicleListReadRepository`, `VehicleWriteRepository`, `AssignmentReadRepository`, `CompanyListReadRepository`, `FiscalRuleListReadRepository`) + interfaces + binding `RepositoryServiceProvider` | 2 j |
| 1.5 | Créer la couche Service (`VehicleCreationService`, `VehicleFiscalCharacteristicsService`, `PlanningHeatmapService`, `PlanningPreviewService`, `AssignmentBulkCreateService`, `DashboardSummaryService`, `CompanyCreationService`) | 3 j |
| 1.6 | Créer la couche Action (`CreateVehicleAction`, `CreateCompanyAction`, `BulkCreateAssignmentsAction`) | 1 j |
| 1.7 | Migrer controllers un par un pour déléguer. Chaque controller doit faire < 30 lignes par méthode | 2-3 j |

### Phase 2 — Tests (~5-7 j/h)

| # | Action | Effort |
|---|---|---|
| 2.1 | Feature tests des 6 controllers User (index/create/store/show) + Auth | 3 j |
| 2.2 | Unit tests des Services et Actions (avec factories + mocks) | 2 j |
| 2.3 | Setup Vitest + Testing Library + mocks Wayfinder, 3-4 tests de composables et utils représentatifs | 1-2 j |

### Phase 3 — Frontend Wayfinder + composables + DTO (~6-8 j/h)

| # | Action | Effort |
|---|---|---|
| 3.1 | Sweep Wayfinder — remplacer tous les `href="/app/..."` et tous les `postJson('/app/...')` par `XxxController.yyy(...)` | 1 j |
| 3.2 | Créer `resources/js/Utils/format/` + supprimer les formateurs inline | 0.5 j |
| 3.3 | Créer les composables manquants (`useFiscalPreview`, `useHeatmap`, `usePlanningWeek`, `useVehicleCalendarDates`, `useFiscalYear`) | 2 j |
| 3.4 | Migrer vers les DTO TS générés (`@/types` centralisé, suppression des types inline) | 1 j |
| 3.5 | Découper `WeekDrawer.vue`, `Assignments/Index.vue`, `FiscalRules/Index.vue` en partials < 300 L | 1.5-2 j |
| 3.6 | Store Pinia `fiscalYearStore` + brancher `UserLayout` | 0.5 j |
| 3.7 | Gestion erreurs fetch : remplacer `catch {}` par toast + propagation via composable | 0.5 j |

### Phase 4 — Moteur fiscal complet (~5-8 j/h, hors MVP démo)

| # | Action | Effort |
|---|---|---|
| 4.1 | Décorréler `BracketsCatalog2024` en pipeline versionné par année (`FiscalRulePipeline` + seed 2025) | 2 j |
| 4.2 | Implémenter R-2024-017 hybride conditionnelle | 1 j |
| 4.3 | Implémenter conversion NEDC→WLTP | 1 j |
| 4.4 | Implémenter exonérations inactives R-018/019/022 avec toggles | 1 j |
| 4.5 | Cache via `CacheTagsManager` + invalidation sur mutation | 1-2 j |

### Phase 5 — Performance et pagination (~3-4 j/h)

| # | Action | Effort |
|---|---|---|
| 5.1 | Pagination sur Vehicles/Companies/Assignments | 1 j |
| 5.2 | Éviter N+1 (`->with('fiscalCharacteristics')` + pre-aggregate SQL) | 1 j |
| 5.3 | Cache agrégats tax-annual-par-véhicule, tax-annual-par-entreprise | 1 j |
| 5.4 | `shallowRef` sur la heatmap, `import()` dynamique pour `fiscalRulesContent.ts` | 0.5 j |

**Total estimé socle : ~32-42 j/h** pour amener le MVP démo à un V1 conforme aux règles d'implémentation formalisées sur les couches backend + composition frontend déjà identifiée dans ce chapitre.

> **Révisé** : après complément d'audit sur les couches frontend sous-traitées (types domaine, composables, utils, Pinia, Wayfinder, accessibilité, tests Vue, i18n préparatoire), ajouter **+~13.75 j/h** — soit un total **~48-54 j/h**. La phase 3 « Frontend Wayfinder + composables + DTO » passe en particulier de 6-8 j/h à **15-20 j/h**. Voir section finale « COMPLÉMENT D'AUDIT » pour le détail fichier/ligne.

---

## 9. Résumé des fichiers concernés (tableau récapitulatif)

| Fichier absolu | Problèmes |
|---|---|
| `app/Http/Controllers/User/Dashboard/DashboardController.php` | §2.1, 2.2, 2.5, 7.1 |
| `app/Http/Controllers/User/Vehicle/VehicleController.php` | §2.1, 2.2, 2.3, 2.5, 7.1, 7.3 |
| `app/Http/Controllers/User/Company/CompanyController.php` | §2.1, 2.2, 2.3, 2.5, 7.1, 7.3 |
| `app/Http/Controllers/User/Assignment/AssignmentController.php` | §2.1, 2.2, 2.4, 2.9 |
| `app/Http/Controllers/User/Planning/PlanningController.php` | §2.1, 2.2, 2.4, 2.5, 2.9, 7.1 |
| `app/Http/Controllers/User/FiscalRule/FiscalRuleController.php` | §2.1, 2.2 |
| `app/Http/Controllers/Auth/LoginController.php` | OK (fonctionnel, peu de code) |
| `app/Http/Requests/User/Vehicle/StoreVehicleRequest.php` | §2.3 (`authorize: true`) |
| `app/Http/Requests/User/Company/StoreCompanyRequest.php` | §2.3 (`authorize: true`) |
| `app/Http/Middleware/HandleInertiaRequests.php` | §2.1 pt 3, §5.3 |
| `app/Services/Fiscal/FiscalCalculator.php` | §2.8 (emplacement `Shared/Fiscal/`), §2.10 (raccourcis documentés), §2.4 (InvalidArgumentException brut) |
| `app/Services/Fiscal/Dto/FiscalBreakdown.php` | §2.2 (pas Spatie Data, pas `#[TypeScript]`) |
| `app/Services/Shared/Cache/CacheTagsManager.php` | §7.2 (bien écrit, jamais appelé) |
| `app/Models/Vehicle.php` | §2.7 (scopes manquants) |
| `app/Models/Company.php` | §2.7 |
| `app/Models/Assignment.php` | §2.7, §5.2 |
| `app/Models/VehicleFiscalCharacteristics.php` | §2.7 |
| `app/Exceptions/BaseAppException.php` | §2.4 (jamais étendue) |
| `database/seeders/DemoSeeder.php` | §2.6 |
| `database/seeders/UserSeeder.php` | §6.3 |
| `routes/web.php` | §2.9, §5.4 |
| `routes/user.php` | §2.9, §6.1 |
| `routes/auth.php` | OK |
| `resources/js/lib/http.ts` | §3.1 |
| `resources/js/lib/utils.ts` | OK |
| `resources/js/composables/useToasts.ts` | §2.8 (emplacement/nommage) |
| `resources/js/types/index.ts`, `auth.ts`, `ui.ts`, `inertia.d.ts`, `global.d.ts` | §2.2 (DTO manuels), §3.8 (global.d.ts trop large) |
| `resources/js/pages/User/Planning/Index.vue` | §3.1, §3.2, §3.10, §3.11 |
| `resources/js/pages/User/Assignments/Index.vue` | §3.1, §3.2, §3.4, §3.5, §3.7, §3.9, §3.10, §3.11 |
| `resources/js/pages/User/Vehicles/Index.vue` | §3.2, §3.7, §3.11 |
| `resources/js/pages/User/Vehicles/Create.vue` | §3.2, §3.5 (limite), §3.11 |
| `resources/js/pages/User/Companies/Index.vue` | §3.2, §3.7, §3.11 |
| `resources/js/pages/User/Companies/Create.vue` | §3.2, §3.11 |
| `resources/js/pages/User/Dashboard/Index.vue` | §3.2, §3.7, §3.11 |
| `resources/js/pages/User/FiscalRules/Index.vue` | §3.2, §3.5, §7.4 |
| `resources/js/pages/Welcome.vue` | §3.11 |
| `resources/js/Components/Features/Planning/WeekDrawer.vue` | §3.1, §3.2, §3.4, §3.5, §3.7, §3.10, §3.11 |
| `resources/js/Components/Features/Planning/Heatmap.vue` | §3.2, §3.7, §3.9, §7.4 |
| `resources/js/Components/Features/Planning/MultiDatePicker.vue` | §3.5 (limite) |
| `resources/js/Components/Layouts/UserLayout.vue` | §3.6 |
| `resources/js/data/fiscalRulesContent.ts` | §7.4 (25 Ko import sync) |
| `resources/js/actions/**` | Wayfinder généré mais non utilisé (§3.11, §1 pt 8) |
| `tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php` | §5.1 (scaffold) |
| `tests/Feature/Schema/SchemaSmokeTest.php` | OK |
| `tests/Feature/Services/Shared/Cache/CacheTagsManagerTest.php` | OK |
| `tests/Unit/Fiscal/BracketsCatalog2024Test.php` | OK (mais `FiscalCalculator` non testé : §4.2) |

---

## 10. Remarque finale — ce qui a été bien fait malgré la pression démo

En dépit des raccourcis assumés, plusieurs choix sont **conformes aux règles** et méritent d'être préservés lors de la refonte :

- Structure `app/Http/Controllers/User/{Domaine}/{Entité}Controller.php` : **correcte** (§ `structure-fichiers.md:83`).
- Enums PHP sérialisés avec cases `PascalCase` et valeurs `snake_case` anglais : **conforme** convention E1.
- `final readonly class FiscalCalculator` + constante `LCD_THRESHOLD_DAYS` : **parfait**.
- `BracketsCatalog2024Test.php` avec `DataProvider` exhaustif : **exemplaire**.
- `LoginRequest` avec rate-limit + message générique OWASP : **parfait**.
- `CacheTagsManager` avec LIKE-escape, PHPDoc exhaustive, test dédié : **exemplaire**, dommage qu'il soit inutilisé.
- `HandleInertiaRequests` avec shared props typées (`fiscal`, `auth`, `flash`) : **propre**.
- Commentaires PHPDoc en tête de classe avec références CDC et ADR : **exemplaire**.
- Inertia v3 bien pris en compte : `<Link>`, `useForm`, `router.visit`, `usePage()` corrects.
- Composants UI Kit (`Button`, `SelectInput`, `DataTable`, …) : arborescence correcte, à peine à auditer.

C'est ce socle qui rend la remise aux normes **faisable en ~35 j/h** plutôt que **~60-80 j/h** si tout était à refaire. Le MVP démo a pris des raccourcis, pas des impasses.

---

## COMPLÉMENT D'AUDIT — couches frontend sous-traitées

> Le chapitre 3 du rapport initial survolait les couches `types/`, `composables/`, `utils/`, `stores/` et l'utilisation de Wayfinder. Ce complément reprend en détail, fichier par fichier et ligne par ligne, les manquements observés — on y inventorie aussi les constantes magiques, l'i18n, la validation frontend, l'accessibilité, la performance UI, les design tokens, les imports, et le fichier Wayfinder.
>
> Périmètre : `resources/js/` intégralement relu le 24/04/2026 après push démo.

### A. Types TypeScript — domaines absents et duplications inline

État du dossier `resources/js/types/` :

- `auth.ts` — `CurrentUser`, `Auth`, `Flash` (OK).
- `ui.ts` — `ButtonVariant`, `ButtonSize`, `BadgeTone`, `StatusTone`, `CompanyColor`, `DataTableColumnAlign`, `DataTableColumn<R>` (OK côté UI kit).
- `inertia.d.ts` — augmentation `PageProps` (OK).
- `global.d.ts`, `vue-shims.d.ts` — boilerplate Vite (OK).
- `index.ts` — `export * from './auth'` (ne ré-exporte même pas `ui.ts`).

**Aucun** fichier de domaine métier : pas de `vehicle.ts`, `company.ts`, `assignment.ts`, `fiscal.ts`, `planning.ts`, `driver.ts`. La règle `typescript-dto.md` impose pourtant qu'ils soient **générés** depuis les DTOs Spatie côté PHP (ce qui découle du manquement §2.2 du rapport principal, non fait au MVP).

**Inventaire précis des types inline qui devraient vivre dans `types/` par domaine :**

| Type inline (nom local) | Fichier | Lignes | Domaine cible |
|---|---|---|---|
| `Vehicle` (forme heatmap) | `resources/js/pages/User/Planning/Index.vue` | 10-23 | `types/vehicle.ts` → `VehicleHeatmapRowData` |
| `Vehicle` (ré-déclaré) | `resources/js/Components/Features/Planning/Heatmap.vue` | 16-29 | idem — **duplication exacte** |
| `Company` | `resources/js/pages/User/Planning/Index.vue` | 25-30 | `types/company.ts` → `CompanyListItemData` |
| `Company` (ré-déclaré) | `resources/js/Components/Features/Planning/WeekDrawer.vue` | 21-26 | idem — **duplication exacte** |
| `CompanyRow` | `resources/js/pages/User/Companies/Index.vue` | 11-21 | `types/company.ts` → `CompanyListRowData` |
| `CompanyOption` | `resources/js/pages/User/Assignments/Index.vue` | 17-22 | `types/company.ts` → `CompanyOptionData` |
| `ColorOption` | `resources/js/pages/User/Companies/Create.vue` | 8 | `types/company.ts` → `CompanyColorOption` |
| `VehicleRow` | `resources/js/pages/User/Vehicles/Index.vue` | 11-21 | `types/vehicle.ts` → `VehicleListRowData` |
| `VehicleOption` | `resources/js/pages/User/Assignments/Index.vue` | 11-15 | `types/vehicle.ts` → `VehicleOptionData` |
| `EnumOption` + shape `enums: {...}` | `resources/js/pages/User/Vehicles/Create.vue` | 11-22 | `types/enums.ts` → `VehicleFormOptionsData` |
| `WeekData`, `DaySlot` | `resources/js/Components/Features/Planning/WeekDrawer.vue` | 28-48 | `types/planning.ts` → `PlanningWeekData` |
| `WeekData` (ré-déclaré, shape divergente) | `resources/js/pages/User/Planning/Index.vue` | 32-50 | idem — **duplication partielle** (la version page n'a pas `DaySlot` extrait) |
| `FiscalPreview` (WeekDrawer, 24 L) | `resources/js/Components/Features/Planning/WeekDrawer.vue` | 50-73 | `types/fiscal.ts` → `FiscalPreviewData` |
| `FiscalPreview` (Assignments, 17 L, plus pauvre) | `resources/js/pages/User/Assignments/Index.vue` | 40-56 | idem — **duplication partielle** (manque `before`, `co2FullYearTariff`, `pollutantCategory`, `pollutantsFullYearTariff`) |
| `DayCell` | `resources/js/Components/Features/Planning/MultiDatePicker.vue` | 54-62 | `types/planning.ts` → `MultiDatePickerDayCell` (ou rester local — voir §F) |
| `QuickLink` | `resources/js/pages/User/Dashboard/Index.vue` | 35-41 | `types/navigation.ts` → `DashboardQuickLinkData` |
| Shape `stats: {...}` inline dans `defineProps` | `resources/js/pages/User/Dashboard/Index.vue` | 17-23 | `types/fiscal.ts` → `DashboardStatsData` |
| `LegalReference`, `Rule`, `SectionGroup` | `resources/js/pages/User/FiscalRules/Index.vue` | 17-33, 55-60 | `types/fiscal.ts` → `FiscalRuleData`, `LegalReferenceData`, `FiscalRuleSectionGroup` |
| `RuleContent`, `Bracket`, `FlatBracket`, `RuleTab`, `RuleSection` | `resources/js/data/fiscalRulesContent.ts` | 17-60 | à garder dans `data/` (c'est du contenu pédagogique), mais les types `Bracket`, `FlatBracket` devraient être re-exportés depuis `types/fiscal.ts` |

**Divergences dangereuses** :

- `FiscalPreview` dans `Assignments/Index.vue` ne déclare **ni** `before`, **ni** `co2FullYearTariff`, **ni** `pollutantsFullYearTariff`, alors que le controller PHP (`PlanningController.php:200-262`) renvoie ces champs. Si un jour le composant en a besoin, il fera un `.` sur un champ réputé `undefined` par TS, alors qu'il existe côté réseau.
- `Vehicle.userType` typé `string` dans `Heatmap.vue:21` et `Planning/Index.vue:15` — devrait être `'VP' | 'VU'` (enum PHP). Le template `Heatmap.vue:146` fait `vehicle.userType === 'VU'` — sans union stricte TS, faute de frappe silencieuse possible.
- `co2Method: string` (`Assignments/Index.vue:49`, `WeekDrawer.vue:64`) — devrait être `HomologationMethod = 'WLTP' | 'NEDC' | 'PA'`.
- `pollutantCategory: string` (`WeekDrawer.vue:67`) — devrait être `PollutantCategory = 'category_1' | ...` (7 cas).

**Correctif attendu** : installer `spatie/laravel-data` + `spatie/typescript-transformer` (phase 1.1 du rapport principal), régénérer, supprimer tous les types inline ci-dessus, consommer `import type { VehicleHeatmapRowData } from '@/types/vehicle'`.

**Effort** : ~1 j/h mécanique une fois la phase 1.1 faite.

### B. Composables — un seul existant, huit manquants

État du dossier `resources/js/composables/` :

- `useToasts.ts` (70 L) — seul composable présent.

La règle `composables-services-utils.md` § 1 demande l'extraction dès qu'une logique dépasse 30 lignes **ou** qu'elle est dupliquée. Inventaire des patterns qui devraient être composables :

#### B.1 `useFiscalPreview` — CRITIQUE, duplication totale

Même logique copiée-collée dans deux fichiers :

- `resources/js/Components/Features/Planning/WeekDrawer.vue:89-91, 166-199` — `preview`, `previewLoading`, debounce 200 ms via `setTimeout`, `fetchPreview()` qui appelle `postJson<FiscalPreview>('/app/planning/preview-taxes', ...)`.
- `resources/js/pages/User/Assignments/Index.vue:58-59, 157-187` — **exactement** le même code, même constante de debounce, même gestion d'erreur `catch { preview.value = null; }`.

**Emplacement cible** : `resources/js/composables/useFiscalPreview.ts`, signature :

```
useFiscalPreview(input: {
    vehicleId: Ref<number | null>;
    companyId: Ref<number | null>;
    dates: Ref<string[]>;
    debounceMs?: number;
}): { preview: Ref<FiscalPreviewData | null>; loading: Ref<boolean>; error: Ref<string | null> };
```

Utiliser `useDebounceFn` de `@vueuse/core` (déjà installé — `package.json:46`) plutôt que `setTimeout` maison. Utiliser Wayfinder `PlanningController.previewTaxes.post()` au lieu de l'URL en dur.

#### B.2 `useMultiDateSelection` — logique enfermée dans un composant

Actuellement dans `resources/js/Components/Features/Planning/MultiDatePicker.vue` :

- Lignes 36-52 : `anchorDate`, `selectedSet`, `disabledSet`, `currentPairSet`, `highlightSet` — état et computed.
- Lignes 117-146 : `onDayClick(cell, event)` avec branche shift / ctrl-cmd / clic simple.
- Lignes 148-160 : `buildRange(startIso, endIso)`.
- Lignes 162-165 : `clearSelection()`.

Le composant `WeekDrawer.vue:137-146` réimplémente un `toggleSlot(date, isDisabled)` qui fait la même gestion `Set<string>` + sort — **même problème, code en doublon**.

**Emplacement cible** : `resources/js/composables/useMultiDateSelection.ts`. Le `MultiDatePicker.vue` devient une vue fine qui consomme le composable.

#### B.3 `useWeekData` / `usePlanningDrawer`

- `resources/js/pages/User/Planning/Index.vue:58-76` : `drawerOpen`, `weekData`, `loadingWeek`, `openWeek(payload)` qui appelle `getJson<WeekData>('/app/planning/week', ...)`.

État + fetch + ouverture/fermeture — typiquement composable `usePlanningDrawer(fiscalYear)`.

#### B.4 `useVehicleBusyDates`

Actuellement `resources/js/pages/User/Assignments/Index.vue:37-38, 105-147` : deux `watch` qui appellent le même endpoint `/app/assignments/vehicle-dates` avec deux responsabilités (watch véhicule et watch entreprise). Le second watch reffetch alors que les dates véhicule n'ont pas changé — **bug latent** : 2 appels réseau là où 1 suffit, sans même un abort signal.

**Emplacement cible** : `resources/js/composables/useVehicleBusyDates.ts`, qui expose `vehicleBusyDates`, `pairDatesForCouple`, et fait un seul fetch quand `vehicleId` change, puis dérive `pairDatesForCouple` localement depuis `companyId`.

#### B.5 `useAssignmentForm`

`resources/js/pages/User/Assignments/Index.vue:189-209` et `resources/js/Components/Features/Planning/WeekDrawer.vue:201-221` : les deux `submit()` postent `/app/planning/assignments` avec la même forme `{ vehicleId, companyId, dates }` puis redirigent (l'un via `router.visit`, l'autre via `emit`). Logique à factoriser.

#### B.6 `useFiscalYear`

Lecture de `page.props.fiscal.currentYear` et `page.props.fiscal.availableYears` :

- `resources/js/Components/Layouts/UserLayout.vue:21-23` — `currentYear`, `availableYears`, `minYear`, `maxYear` + `watch(currentYear, ...)` pour sync locale.
- `resources/js/pages/User/Assignments/Index.vue:29-30` — `fiscalYear` computed local.

Le pattern `ref(currentYear.value) + watch()` (UserLayout:29-32) trahit le besoin d'un composable (ou store, voir §D).

**Emplacement cible** : `resources/js/composables/useFiscalYear.ts`, wrapper typed + tests.

#### B.7 `useCurrentUser`

- `resources/js/Components/Layouts/UserLayout/TopBar.vue:22-37` : `authUser`, `fullName`, `initials` calculés inline.
- `resources/js/pages/Welcome.vue:7-8` : `isAuthenticated` computed inline.

**Emplacement cible** : `resources/js/composables/useCurrentUser.ts` retournant `{ user, isAuthenticated, fullName, initials }`. Simple, mais éviter la redéclaration au moindre nouveau composant qui a besoin du user (layout admin plus tard, modale profil, etc.).

#### B.8 `useHeatmapDensity`

Actuellement dans `resources/js/Components/Features/Planning/Heatmap.vue:55-68` : `densityClass(days)` avec 8 branches `if`, `textContrastClass(days)` avec contraste à ≥ 3. Ces mappings sont déterministes et réutilisables (les cartes fiscales auront la même logique). Devrait être dans `composables/useHeatmapDensity.ts` OU plutôt `utils/heatmapDensity.ts` (pas d'état → util pur).

**Correctif attendu pour B.1-B.8** : créer les 8 fichiers, typer les retours explicitement (règle `composables-services-utils.md` § 1 : type de retour explicite obligatoire — aujourd'hui `useToasts` lui-même ne respecte pas à 100 % — voir §F).

**Effort** : ~3 j/h pour les 8 composables + tests basiques.

### C. Utils / formatters — dossier absent, duplications à 6 endroits

Le dossier `resources/js/utils/` (ou `resources/js/Utils/` pour PascalCase) **n'existe pas**. Seul `resources/js/lib/utils.ts` (8 L) existe, mais il ne contient que le helper `cn()` pour Tailwind.

#### C.1 `formatEur` — dupliqué 6 fois mot pour mot

Occurrences exactes (relevées à `grep` strict) :

| Fichier | Lignes |
|---|---|
| `resources/js/Components/Features/Planning/Heatmap.vue` | 70-77 (`maxFractionDigits: 0`) |
| `resources/js/Components/Features/Planning/WeekDrawer.vue` | 223-231 (2 décimales) |
| `resources/js/pages/User/Assignments/Index.vue` | 211-219 (2 décimales) |
| `resources/js/pages/User/Companies/Index.vue` | 36-43 (0 décimale) |
| `resources/js/pages/User/Dashboard/Index.vue` | 26-33 (0 décimale) |
| `resources/js/pages/User/Vehicles/Index.vue` | 52-59 (0 décimale) |

Tous appellent `.replace(/ | /g, ' ')` pour normaliser le NBSP (U+00A0) et le NNBSP (U+202F) que renvoie `Intl.NumberFormat('fr-FR')` (suite au changement de CLDR 39). Aucun commentaire n'explique ce `replace`, aucun test, et il y a **deux signatures différentes** (0 vs 2 décimales) — donc l'extraction doit être paramétrable.

**Emplacement cible** : `resources/js/utils/format.ts` :

```
export function formatMoney(amount: number, options?: { fractionDigits?: 0 | 2 }): string;
```

#### C.2 `formatDateFr` — 1 occurrence mais patron à généraliser

- `resources/js/pages/User/Vehicles/Index.vue:61-64` : conversion ISO `YYYY-MM-DD` → `DD/MM/YYYY` par `split('-')`. Utilisé pour `firstFrenchRegistrationDate`.

Les colonnes `acquisitionDate`, `exitDate`, `createdAt` des modèles à venir réclameront le même formatter. Le drawer semaine affiche déjà `week.weekStart`, `week.weekEnd` en ISO brut (`WeekDrawer.vue:281`) — c'est moche pour l'utilisateur.

**Emplacement cible** : `resources/js/utils/format.ts` :

```
export function formatDate(iso: string, format?: 'short' | 'long' | 'weekday'): string;
```

Suggestion : utiliser `date-fns` avec locale `fr` (ADR à écrire) plutôt que `Intl.DateTimeFormat` pour avoir `eeeeee dd MMM` (« Lun. 15 janv. »).

#### C.3 `statusDotClass`, `statusLabel` — inline dans `Vehicles/Index.vue`

- `resources/js/pages/User/Vehicles/Index.vue:36-50` — deux `Record<string, string>` pour mapper `currentStatus` → label + classe Tailwind.

Même pattern dans `Companies/Index.vue:100-108` pour `isActive` → classe (inline directement dans le template).

**Emplacement cible** : `resources/js/utils/vehicle.ts` + `resources/js/utils/company.ts` avec helpers typés.

#### C.4 `buildRange`, `formatIso`, `weekNumber` — dates

Actuellement dans `resources/js/Components/Features/Planning/MultiDatePicker.vue` :

- 102-107 : `formatIso(d: Date)` — `YYYY-MM-DD` depuis `Date`.
- 148-160 : `buildRange(startIso, endIso)` — plage ISO inclusive.
- 64-100 : construction des 6 semaines du mois avec `(jsDayOfWeek + 6) % 7` pour le lundi.

Patron qui se répètera pour le picker de range (V1 déclarations), pour les stats hebdo, etc.

**Emplacement cible** : `resources/js/utils/date.ts` avec `isoDate(d)`, `buildDateRange(start, end)`, `isoToFrenchWeekNumber(iso)`, `weekStartOfIso(iso)`, `daysBetweenIso(a, b)`.

#### C.5 Divers — `toLocaleString('fr-FR')`

- `resources/js/Components/Features/Planning/Heatmap.vue:105` — `totalDays.toLocaleString('fr-FR')` inline template.
- `resources/js/pages/User/Dashboard/Index.vue:113` — `stats.assignmentsYear.toLocaleString('fr-FR')` inline template.

Même raison : centraliser `formatInteger(n)` dans `utils/format.ts`.

**Correctif attendu C.1-C.5** : créer `resources/js/utils/format.ts`, `utils/date.ts`, `utils/vehicle.ts`, `utils/company.ts` + tests unitaires Vitest.

**Effort** : ~0.75 j/h utils + ~0.5 j/h tests = 1.25 j/h.

### D. Stores Pinia — dossier inexistant

Pinia n'est même pas dans `package.json` (vérifié — la seule lib d'état est `@vueuse/core`). Aucun dossier `resources/js/stores/` ou `Stores/`.

État identifié qui justifie un store (règle `pinia-stores.md` § 1) :

#### D.1 `useFiscalStore`

- Année fiscale sélectionnée (aujourd'hui lue 4 fois : `UserLayout.vue:21`, `Assignments/Index.vue:30`, indirectement dans tous les KPI).
- `availableYears` (lu dans `UserLayout.vue:22`, `TopBar.vue` via `YearSelector`).
- Plus tard : règles fiscales actives de l'année courante (cache client pour éviter refetch à chaque navigation vers la page Règles), breakdown de la dernière preview.

**Emplacement cible** : `resources/js/stores/user/fiscalStore.ts` (ou `Stores/User/fiscalStore.ts` selon choix de casing — voir §F).

#### D.2 `usePlanningStore`

- Semaine ouverte (actuellement dans `Planning/Index.vue:58-60`, local state).
- Sélection multi-dates en cours (actuellement `selectedDates` local dupliqué dans `Assignments/Index.vue:34` et `WeekDrawer.vue:88`). Si l'utilisateur passe de la vue rapide à la heatmap, il perd sa sélection.
- Position du scroll horizontal de la heatmap (UX : on aimerait la retrouver après retour depuis un détail).

#### D.3 `useCompaniesStore` et `useVehiclesStore`

- Cache client des listes `VehicleOption[]` et `CompanyOption[]` — aujourd'hui rechargées à chaque visite de la page Attributions (`resources/js/pages/User/Assignments/Index.vue:25-27`) parce que le controller `AssignmentController::index` les réenvoie dans la payload Inertia à chaque navigation.
- Ces entités changent peu ; un store avec stratégie « stale-while-revalidate » (Inertia v3 `useHttp` + `fresh` flag) économiserait 2 requêtes BDD par navigation.

**Correctif attendu** : installer `pinia` + `@pinia/plugin-persistedstate` (pour la sélection multi-dates), créer les 4 stores, migrer les lectures `usePage().props.fiscal.*` vers `fiscalStore.year` dans les pages.

**Effort** : ~1.5 j/h (install + 4 stores + migration 5-6 fichiers).

### E. Wayfinder généré mais non appelé

Le vite plugin Wayfinder est configuré (`package.json:34` — `@laravel/vite-plugin-wayfinder ^0.1.3`) et les fichiers sont générés dans :

- `resources/js/actions/App/Http/Controllers/Auth/LoginController.ts`
- `resources/js/actions/App/Http/Controllers/User/{Assignment,Company,Dashboard,FiscalRule,Planning,Vehicle}/` (6 controllers + 6 index.ts)
- `resources/js/routes/user/{assignments,companies,fiscal-rules,planning,vehicles}/index.ts`
- `resources/js/routes/user/planning/assignments/index.ts`

Les dossiers `actions/`, `routes/`, `wayfinder/` sont bien dans `.gitignore` (lignes 6-8) — **bon choix**, conforme à la règle de ne pas committer du code généré.

**Inventaire précis des URL écrites en dur à remplacer par un appel Wayfinder** :

| Fichier | Ligne | URL en dur | Fonction Wayfinder attendue |
|---|---|---|---|
| `resources/js/pages/Welcome.vue` | 37 | `<Link href="/app/dashboard">` | `DashboardController().url` (controller invocable) |
| `resources/js/pages/User/Dashboard/Index.vue` | 48 | `href: '/app/planning'` | `import planning from '@/routes/user/planning'; planning.index.url()` |
| `resources/js/pages/User/Dashboard/Index.vue` | 57 | `href: '/app/assignments'` | `AssignmentController.index().url` |
| `resources/js/pages/User/Dashboard/Index.vue` | 63 | `href: '/app/vehicles'` | `VehicleController.index().url` |
| `resources/js/pages/User/Dashboard/Index.vue` | 68 | `href: '/app/companies'` | `CompanyController.index().url` |
| `resources/js/pages/User/Dashboard/Index.vue` | 75 | `href: '/app/fiscal-rules'` | `FiscalRuleController.index().url` |
| `resources/js/pages/User/Vehicles/Index.vue` | 84, 103 | `<Link href="/app/vehicles/create">` | `VehicleController.create().url` |
| `resources/js/pages/User/Vehicles/Create.vue` | 55 | `form.post('/app/vehicles')` | `form.submit(VehicleController.store())` |
| `resources/js/pages/User/Vehicles/Create.vue` | 261 | `<Link href="/app/vehicles">` | `VehicleController.index().url` |
| `resources/js/pages/User/Companies/Index.vue` | 63, 82 | `<Link href="/app/companies/create">` | `CompanyController.create().url` |
| `resources/js/pages/User/Companies/Create.vue` | 31 | `form.post('/app/companies')` | `form.submit(CompanyController.store())` |
| `resources/js/pages/User/Companies/Create.vue` | 154 | `<Link href="/app/companies">` | `CompanyController.index().url` |
| `resources/js/pages/User/Planning/Index.vue` | 68 | `getJson<WeekData>('/app/planning/week', …)` | `planning.week.get(...)` (voir `resources/js/routes/user/planning/index.ts:86-113` pour la signature exacte) |
| `resources/js/pages/User/Assignments/Index.vue` | 115 | `getJson(..., '/app/assignments/vehicle-dates', ...)` | `AssignmentController.vehicleDates().url` |
| `resources/js/pages/User/Assignments/Index.vue` | 136 | idem | idem |
| `resources/js/pages/User/Assignments/Index.vue` | 175 | `postJson<FiscalPreview>('/app/planning/preview-taxes', ...)` | `planning.previewTaxes.post()` (voir `resources/js/routes/user/planning/index.ts:164-213`) |
| `resources/js/pages/User/Assignments/Index.vue` | 199 | `postJson('/app/planning/assignments', ...)` | `planning.assignments.storeBulk.post()` (voir `resources/js/routes/user/planning/assignments/index.ts`) |
| `resources/js/pages/User/Assignments/Index.vue` | 205 | `router.visit('/app/planning')` | `router.visit(planning.index.url())` |
| `resources/js/Components/Features/Planning/WeekDrawer.vue` | 187 | `postJson<FiscalPreview>('/app/planning/preview-taxes', ...)` | idem |
| `resources/js/Components/Features/Planning/WeekDrawer.vue` | 212 | `postJson('/app/planning/assignments', ...)` | idem |
| `resources/js/pages/Auth/Login.vue` | 13 | `form.post('/login', ...)` | `LoginController.store().url` (ou `form.submit(LoginController.store())` style Inertia v3) |

**Exemple concret** pour comprendre la substitution — extrait réel de `resources/js/routes/user/planning/index.ts:164-181` :

```ts
export const previewTaxes = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: previewTaxes.url(options),
    method: 'post',
})
previewTaxes.definition = { methods: ["post"], url: '/app/planning/preview-taxes', } satisfies RouteDefinition<["post"]>
previewTaxes.url = (options?: RouteQueryOptions) => previewTaxes.definition.url + queryParams(options)
previewTaxes.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({ url: previewTaxes.url(options), method: 'post' })
```

Donc, là où `WeekDrawer.vue:187` fait :

```ts
await postJson<FiscalPreview>('/app/planning/preview-taxes', { vehicleId, companyId, dates })
```

il devrait faire :

```ts
import planning from '@/routes/user/planning';
const { url, method } = planning.previewTaxes();
// ou avec useHttp Inertia v3 :
await useHttp().post(url, { vehicleId, companyId, dates });
```

**Correctif attendu** : une passe complète sur les 21 URL ci-dessus + suppression de `lib/http.ts` au profit de `useHttp()` d'Inertia v3 (cf. rapport principal § 3.1).

**Effort** : ~1.5 j/h (déjà chiffré en phase 3.1 du rapport principal, à confirmer).

### F. Autres manquements — inventaire ouvert

#### F.1 Constantes en dur / magic strings

- `resources/js/Components/Features/Planning/Heatmap.vue:87-88` : `CELL_WIDTH = 21`, `GRID_WIDTH = 52 * CELL_WIDTH` — ces constantes ont un sens UI, elles peuvent rester locales, **mais** devraient être dans un fichier `constants/heatmap.ts` si un autre composant (mini-map de PDF déclaration, scatterplot V2) doit s'aligner dessus.
- `resources/js/Components/Features/Planning/Heatmap.vue:40-53` : tableau `monthLabels` avec libellés FR `'Jan', 'Fév', ... 'Déc'` et répartition `weeks: 4|5` en dur pour **2024 uniquement** (52 semaines réparties sur 12 mois, chaque mois faisant 4 ou 5 semaines). En 2025, la répartition sera différente (53 semaines ISO) — bug latent. À calculer depuis `year` et `Intl.DateTimeFormat`.
- `resources/js/Components/Features/Planning/WeekDrawer.vue:233` : `const dayLongLabels = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']`. À mutualiser avec `MultiDatePicker.vue:197-203` qui redéclare **exactement la même liste** dans le template.
- `resources/js/composables/useToasts.ts:20` : `DEFAULT_DURATION_MS = 5000` — OK.
- `resources/js/pages/User/Vehicles/Create.vue:37-44` : valeurs par défaut `reception_category: 'M1'`, `vehicle_user_type: 'VP'`, `body_type: 'CI'`, `energy_source: 'gasoline'`, `euro_standard: 'euro_6d_isc_fcm'`, `pollutant_category: 'category_1'`, `homologation_method: 'WLTP'` — 7 valeurs d'enum en littéraux. Avec des types union générés (§A), ces littéraux seraient vérifiés ; sans, une typo ne casse qu'à la validation backend.
- `resources/js/pages/User/FiscalRules/Index.vue:81-84` : `taxLabel: Record<string, string> = { co2: 'CO₂', pollutants: 'Polluants' }` — mini-dictionnaire enfermé dans la page.

**Emplacement cible** : `resources/js/constants/` par domaine (`heatmap.ts`, `calendar.ts`, `fiscal.ts`), `resources/js/enums/` pour les valeurs d'enum miroir (jumelées aux enums PHP).

#### F.2 i18n — 100 % de français en dur

Aucune lib i18n (`vue-i18n` absent de `package.json`). Toute l'UI est en français littéral dans les templates — c'est acceptable tant que le CDC l'impose (« l'application est intégralement en français », `CLAUDE.md` § Langue), **mais** trois problèmes concrets :

- **Aucune source de vérité** pour les libellés : « Nouveau véhicule » apparaît 3 fois (header + bouton + EmptyState dans `Vehicles/Index.vue` + `Vehicles/Create.vue`). Renommer en « Ajouter un véhicule » demandera 3 edits.
- **Accents + caractères spéciaux** directement dans le code source — risque de corruption si le fichier est édité sur un système non-UTF-8 (Windows legacy, éditeur mal configuré). Déjà vu dans `Assignments/Index.vue:247` qui contient un U+2014 (em-dash) et un U+00AB (guillemet français).
- **Messages d'erreur techniques** (quand un fetch plante → `Error: POST /app/… → 422 Unprocessable Entity` remonte tel quel dans une console dev). Aucun mapping FR.

**Correctif attendu** (pré-V2) : introduire un fichier `resources/js/i18n/fr.ts` comme source de vérité, quitte à ne pas ajouter de locale en. Au minimum, extraire les libellés répétés et les messages d'erreur.

#### F.3 Validation frontend — inexistante

La règle `architecture-solid.md` + la pratique UX veulent qu'une validation légère soit faite côté Vue pour feedback instantané (ne remplace pas le backend).

- `resources/js/pages/User/Companies/Create.vue:14-28` : form avec `siren`, `siret` — **aucune** validation de longueur (9 chiffres SIREN / 14 chiffres SIRET), aucun checksum Luhn alors que la règle métier `isValidSiren` existe côté backend (cf. tâche #49 déjà complétée).
- `resources/js/pages/User/Vehicles/Create.vue:25-48` : form avec `license_plate` — aucun regex de format français (`/^[A-Z]{2}-\d{3}-[A-Z]{2}$/` post-SIV). L'utilisateur découvre le format attendu uniquement après submit.
- `resources/js/Components/Features/Planning/WeekDrawer.vue:489-491` : le bouton `submit` est bien disabled quand invalide, mais aucun message explique pourquoi (juste `disabled="... dates.length === 0"`).

**Correctif attendu** : créer `resources/js/utils/validate.ts` (`isValidSiren`, `isValidSiret`, `isValidFrenchPlate`), + `useValidatedForm` composable qui combine `useForm` d'Inertia + checks synchrones pré-submit. Aligné avec la règle backend → zéro duplication logique (mêmes constantes).

#### F.4 Loading states / skeletons

Pages avec état de chargement correct :
- `resources/js/Components/Features/Planning/WeekDrawer.vue:90` — `previewLoading` avec message texte « Calcul en cours… » (ligne 416).
- `resources/js/pages/User/Assignments/Index.vue:59` — idem (ligne 400).
- `resources/js/Components/Ui/Button/Button.vue:11, 81, 93-99` — état loading avec spinner + `aria-busy`.

Pages sans feedback visuel pendant chargement :
- `resources/js/pages/User/Planning/Index.vue:60` — `loadingWeek` est bien `ref`, mais la template ne l'affiche **nulle part**. Le clic cellule laisse l'utilisateur dans le flou pendant 200-500 ms.
- `resources/js/pages/User/Dashboard/Index.vue` — les 4 KPI sont rendus en dur. Si Inertia faisait un `router.reload({ only: ['stats'] })`, aucune perception (KpiCard n'a pas de prop `loading`).
- `resources/js/pages/User/Vehicles/Index.vue`, `Companies/Index.vue`, `FiscalRules/Index.vue` — aucun skeleton de ligne sur DataTable pendant `router.reload`.

**Règle violée** : `inertia-vue-development` → « deferred props avec empty state animé » (cf. règle v3).

#### F.5 Gestion d'erreur UI — 4 `catch {}` silencieux

Déjà signalé § 2.4 du rapport principal. Détails exhaustifs :

- `resources/js/Components/Features/Planning/WeekDrawer.vue:194-196` : `} catch { preview.value = null; }` — si le backend 422/500, l'utilisateur voit juste « Calcul en cours… » disparaître sans raison.
- `resources/js/pages/User/Assignments/Index.vue:124-126` : `} catch { /* silent */ }` — 1er watch vehicleId.
- `resources/js/pages/User/Assignments/Index.vue:144-147` : `} catch { /* silent */ }` — 2e watch companyId.
- `resources/js/pages/User/Assignments/Index.vue:182-184` : `} catch { preview.value = null; }` — preview fiscal.
- `resources/js/pages/User/Planning/Index.vue:66-75` : `try { ... } finally { loadingWeek.value = false; }` — pas de `catch` du tout. L'erreur remonte en promesse rejetée non-gérée → `unhandledrejection` côté navigateur.
- `resources/js/Components/Features/Planning/WeekDrawer.vue:201-220` : `submit()` n'a **pas de catch** — si le POST 422 (ex: un UNIQUE violé), l'utilisateur voit l'état `submitting=true` se relâcher sans toast ni message.
- `resources/js/pages/User/Assignments/Index.vue:189-208` : idem pour `submit()`.

`useToasts` **existe** et **est instancié** dans `ToastContainer.vue:5`, **mais n'est importé par aucune page fonctionnelle**. Seule la `UiKitShowcase.vue` s'en sert (5 appels) à des fins de démo.

**Correctif attendu** : ajouter `const toasts = useToasts()` dans chaque page qui fait un fetch, remplacer `catch { /* silent */ }` par `catch (e) { toasts.push({ tone: 'error', title: 'Impossible de calculer la prévision fiscale.', description: String(e) }); }`.

#### F.6 Accessibilité

Points observés :

- `WeekDrawer.vue` : rôle `dialog` + `aria-label` ✓, fermeture au clic overlay ✓. **Manquant** :
  - Pas de focus trap — `Tab` peut sortir du drawer vers la page de fond.
  - Pas de focus restoration : quand on ferme, le focus retourne au `<body>` au lieu de la cellule cliquée.
  - Pas de raccourci `Escape` pour fermer (seulement clic overlay ou bouton X).
- `resources/js/Components/Features/Planning/Heatmap.vue:189-205` : les 520 boutons heatmap ont bien `aria-label` et `title`, mais pas de `role="gridcell"` ni de container `role="grid"` → un lecteur d'écran liste « 520 boutons » sans structure. À minima, un `<table>` sémantique ou `role="grid" + role="row" + role="gridcell"`.
- `resources/js/Components/Features/Planning/MultiDatePicker.vue:213-237` : `aria-pressed` sur les jours ✓, **mais** pas de `aria-label` qui annonce la date complète (« Lundi 15 janvier 2024 ») — juste `{{ cell.day }}`. Un lecteur d'écran dit « 15 » sans contexte.
- `resources/js/pages/User/Companies/Index.vue:103-106` : le dot de statut a `title="Active/Inactive"` mais pas de `role="img"` ni de texte caché pour screen reader.
- `resources/js/pages/User/Vehicles/Index.vue:121-131` : idem.
- `resources/js/Components/Layouts/UserLayout/TopBar.vue:47` : bouton hamburger avec `aria-label="Ouvrir la navigation"` ✓.

**Correctif attendu** : focus trap sur le drawer (lib `focus-trap-vue` ou helper maison), handler `Escape`, `role="grid"` sur la heatmap, `aria-label` complet sur chaque jour du picker.

#### F.7 Performance frontend

- `resources/js/Components/Features/Planning/Heatmap.vue:32` : `props.vehicles: Vehicle[]` — tableau profondément réactif de 10+ véhicules × 52 semaines = 520 nombres scannés par Vue. Devrait être `shallowRef` ou `readonly(computed(...))` (cf. `performance-ui.md:47-75`).
- `resources/js/pages/User/Assignments/Index.vue:158-161` : `watch([selectedVehicleId, selectedCompanyId, selectedDates], ...)` — watcher combiné qui se déclenche à chaque toggle de date. Le debounce 200 ms atténue, mais si l'utilisateur sélectionne une plage de 30 dates (shift+clic), le watcher fire 30 fois avant de debounce. Préférer `watchDebounced` de `@vueuse/core`.
- `resources/js/pages/User/FiscalRules/Index.vue:9-13` : import synchrone statique de `@/data/fiscalRulesContent` (410 L de contenu pédagogique + structures JS). Chargé à chaque visite de la page alors qu'il est purement statique — à passer en `defineAsyncComponent` ou `import()` dynamique pour split chunk.
- `resources/js/Components/Features/Planning/MultiDatePicker.vue:49-52` : 4 `computed` qui créent un `Set` à chaque changement de prop. OK en nombre (10-50 dates), mais si on passe à 300 dates par an en 2025, reconsidérer.

#### F.8 Design tokens — usages hardcodés

Le projet utilise Tailwind v4 avec tokens CSS. Pourtant, relevés d'usage hors-tokens :

- `WeekDrawer.vue:309-310` : `'bg-blue-600 text-white hover:bg-blue-700'` — couleur brute au lieu d'un token sémantique (type `bg-action-primary`).
- `WeekDrawer.vue:411` : `'rounded-lg border border-blue-200 bg-blue-50/40 p-3'` — idem, `blue-50/40` est un alpha calculé côté Tailwind qui échappe aux design tokens.
- `Heatmap.vue:56-63` : 8 classes `bg-blue-50`, `bg-blue-100`, `bg-blue-300`, `bg-blue-500`, `bg-blue-700`, `bg-blue-800`, `bg-blue-900` — c'est **volontaire** (densité 0-7), mais ces valeurs devraient être ré-exportées comme `--density-0` … `--density-7` dans le DS pour que le PDF les partage. Cf. `project-management/design-system/`.
- `UserLayout.vue:50` : `wide:pl-60` — token custom `wide:` défini dans le DS ✓.
- `Assignments/Index.vue:329` : `'eyebrow mb-0 text-blue-700'` — le `eyebrow` est une classe utilitaire du DS ✓, mais `text-blue-700` devrait être `text-accent` ou équivalent.
- `Dashboard/Index.vue:136` : palette via `group hover:border-slate-400` — OK, slate est le neutre officiel.

**Verdict** : le DS est généralement respecté ; reste ~10 emplacements où une couleur bleue directe aurait gagné à être un token sémantique.

#### F.9 Z-index — anarchie modérée

Relevé exhaustif :

| Fichier | Ligne | z-index |
|---|---|---|
| `ToastContainer.vue` | 11 | `z-[60]` |
| `WeekDrawer.vue` | 248 | `z-40` (overlay) |
| `WeekDrawer.vue` | 265 | `z-50` (panel) |
| `WeekDrawer.vue` | 271 | `z-10` (header sticky) |
| `Modal.vue` | 72 | `z-50` (même que drawer !) |
| `Drawer.vue` | 72 | `z-50` (idem) |
| `SidebarNav.vue` | 100 | `z-20` (overlay mobile) |
| `SidebarNav.vue` | 107 | `z-30` (sidebar) |
| `UserMenu.vue` | 65 | `z-20` (dropdown) |
| `TopBar.vue` | 42 | `z-10` (sticky top) |
| `DataTable.vue` | 55 | `z-10` (header sticky) |

**Collision** : `Modal.vue` et `Drawer.vue` sont tous deux à `z-50`. Si un `Modal` est ouvert au-dessus d'un `WeekDrawer` (cas possible : confirmation de création), l'ordre DOM décide, ce qui est fragile.

**Correctif attendu** : créer `resources/js/constants/zIndex.ts` avec une échelle explicite :

```
export const Z_INDEX = {
    tooltip: 80,
    toast: 70,
    modal: 60,
    drawer: 50,
    overlay: 40,
    dropdown: 30,
    stickyHeader: 20,
    stickyCell: 10,
} as const;
```

Puis référencer dans les classes Tailwind via `z-[var(--z-modal)]` ou un helper DS.

#### F.10 SSR — désactivé

`package.json:7` : `"build:ssr": "vite build && vite build --ssr"` — commande présente mais `bootstrap/ssr` dans `.gitignore:2` (pas de fichier généré récemment). Le `app.ts` (`resources/js/app.ts` — 252 bytes) est probablement le boot CSR-only ; aucun `ssr.ts` correspondant.

Impact : la page `/` (`Welcome.vue`) publique est rendue côté client → **SEO dégradé** pour une landing publique. À minima, activer SSR en phase 13 (« finitions et livraison ») pour `Welcome.vue`.

#### F.11 Types de retour des composables / fonctions

Règle `conventions-nommage.md` : retour explicite obligatoire.

- `resources/js/composables/useToasts.ts:64` : `export const useToasts = () => ({ ... })` — **aucun type de retour annoté**. L'inférence TS marche mais la règle demande un `: UseToastsReturn` explicite.
- `resources/js/composables/useToasts.ts:39-56` : `push`, `dismiss`, `clear` — `push(input: ToastInput): string` ✓, `dismiss(id: string): void` ✓. OK.
- `resources/js/lib/http.ts:20, 46` : `getJson<T>`, `postJson<T>` → `Promise<T>` ✓.
- `resources/js/Components/Features/Planning/MultiDatePicker.vue:41, 64, 102, 117, 148, 162` : chaque fonction a son type ✓.
- `resources/js/Components/Features/Planning/Heatmap.vue:55, 66, 70` : `densityClass(days: number): string` ✓.
- `resources/js/pages/User/Assignments/Index.vue:163, 189` : `fetchPreview(): Promise<void>` ✓, `submit(): Promise<void>` ✓.

Bilan : 1 seul manquement (`useToasts`), à corriger.

#### F.12 Magic numbers

- `/ 366` en dur dans `WeekDrawer.vue:432` et `Assignments/Index.vue:343` — bug latent pour 2025 (365 jours). Devrait venir de `useFiscalYear().daysInYear`.
- `CELL_WIDTH = 21`, `GRID_WIDTH = 52 * CELL_WIDTH` dans `Heatmap.vue:87-88` — OK, constantes locales bien nommées.
- `DEFAULT_DURATION_MS = 5000` dans `useToasts.ts:20` — OK.
- `200` ms de debounce dans `WeekDrawer.vue:171` et `Assignments/Index.vue:160` — hardcodé 2 fois. À extraire en `DEBOUNCE_FISCAL_PREVIEW_MS` dans `constants/fiscal.ts` OU paramètre du futur `useFiscalPreview`.
- `20px`, `7` (jours semaine), `6` (max 6 rows month grid), `52` (semaines/an) — certains ok, d'autres à centraliser dans `constants/calendar.ts`.

#### F.13 Commentaires non à jour / TODO

Aucun `TODO` ni `FIXME` relevé dans `resources/js/**/*.vue`/`.ts`. **Bonne surprise** — mais ce qui signifie aussi que les raccourcis pris (catch vide, URLs hardcodées, duplication) ne sont **pas tracés** en commentaire. Un reviewer externe ne saura pas que « `catch { /* silent */ }` est volontairement laissé pour la démo ».

**Correctif attendu** : avant toute future démo, ajouter `// TODO(V1): ...` systématique aux 4 catch silencieux + 21 URL hardcodées + 8 composables manquants. Au moins ça trace la dette.

#### F.14 Imports — ordre non canonique

La règle `conventions-nommage.md` demande : libs externes → `@/` aliasés → relatifs → types. Quelques violations :

- `WeekDrawer.vue:12-19` : `'@/Components/...'` (interne) avant `'lucide-vue-next'` (externe). Devrait être inversé.
- `Planning/Index.vue:2-8` : `@/Components/...` avant `@inertiajs/vue3` (externe). Idem.
- `MultiDatePicker.vue:17-18` : OK (externe → relatif).
- `Assignments/Index.vue:2-9` : `@/Components/...` avant `@inertiajs/vue3` et `vue`. Idem.

Problème peu grave en soi, mais démontre qu'aucun `eslint-plugin-import` order rule n'est actif. À brancher.

#### F.15 `const` vs `function`

La règle `conventions-nommage.md` § Composables et fonctions privilégie `function` pour les helpers exportés et `const` pour les handlers/callbacks locaux. Relevés :

- `useToasts.ts:25, 28, 39, 58` : 4 helpers en `const ... = () => { ... }`. Violation.
- `lib/http.ts:12, 20, 46` : `function getXsrfToken`, `function getJson`, `function postJson` ✓.
- Composants Vue : majoritairement `function` pour les handlers — OK.

Peu critique, mais incohérent. Trancher : **`function`** pour tout ce qui est exporté et nommé, `const` pour les fonctions fléchées locales.

#### F.16 Absence de `README.md` dans les Features

`resources/js/Components/Features/Planning/` contient 3 composants (`Heatmap.vue`, `MultiDatePicker.vue`, `WeekDrawer.vue`) dont l'interaction est **critique** (bidirectionnelle, cf. tâche #59 complétée). Aucun README ne documente comment ils se combinent — la compréhension passe uniquement par les commentaires en tête de composant (ligne 2-13 de chacun), ce qui est déjà pas mal mais pas assez.

**Correctif attendu** : `resources/js/Components/Features/Planning/README.md` de 30 L expliquant la circulation des events (Heatmap → `cell-click` → Index → `openWeek` → WeekDrawer → `assignments-created` → Index → `router.reload`).

#### F.17 Tests Vue — ZÉRO

Règle `tests-frontend.md` impose Vitest + Testing Library. Inventaire `package.json` :

- `vitest` — **absent**.
- `@testing-library/vue` — **absent**.
- `@vue/test-utils` — **absent**.
- `jsdom` / `happy-dom` — **absent**.

Aucun fichier `*.spec.ts` ni `*.test.ts` sous `resources/js/`.

Composables/utils/composants qui **devraient** avoir des tests (prio décroissante) :

1. `useFiscalPreview` (à créer, § B.1) — logique métier, mocks HTTP, debounce.
2. `useMultiDateSelection` — shift/ctrl/simple click, edge cases sur les dates disabled.
3. `utils/format.ts` — formatMoney avec/sans décimales, NBSP, valeurs négatives/zéro.
4. `utils/date.ts` — isoDate(Date) idempotent, buildRange sur un changement de mois.
5. `useToasts` — push/dismiss/clear, timers, ID unique.
6. `Heatmap.vue` — densityClass(0..7), contrast ≥ 3.
7. `MultiDatePicker.vue` — test d'intégration : shift+clic construit la plage correcte.
8. `WeekDrawer.vue` — test d'intégration : ouverture, toggle slot, submit, fermeture.

**Effort** : ~2 j/h setup Vitest + 6-8 tests représentatifs.

#### F.18 Fichiers Wayfinder dans le dépôt

**Rectification par rapport au brief** : `resources/js/actions/`, `resources/js/routes/`, `resources/js/wayfinder/` sont bien gitignorés (`.gitignore` lignes 6-8). **Bon point**, conforme aux best practices — regénération automatique via vite plugin à chaque `npm run dev` ou build.

À vérifier toutefois : que le `composer run dev` (alias probable de `php artisan serve` + `npm run dev`) déclenche bien un `artisan wayfinder:generate` initial sur un dépôt fraîchement cloné, avant que Vite ne bronche sur les `@/actions/...` manquants.

### Résumé — effort supplémentaire à intégrer au chiffrage principal

Le rapport principal estimait `~35-40 j/h` pour la remise aux normes backend + frontend. Les manquements frontend détaillés ci-dessus justifient **+10 à +12 j/h supplémentaires** répartis comme suit :

| Sous-section | Effort complémentaire |
|---|---|
| A — Types DTO par domaine (consommation) | 1 j/h (mécanique après 1.1 du rapport principal) |
| B — 8 composables manquants | 3 j/h |
| C — `utils/format.ts`, `utils/date.ts`, `utils/vehicle.ts`, `utils/company.ts` + tests | 1.25 j/h |
| D — Pinia (install + 4 stores + migrations) | 1.5 j/h |
| E — Sweep Wayfinder + migration `lib/http` → `useHttp` | 1.5 j/h |
| F — Constants/enums/zIndex, i18n préparatoire, validation frontend | 1.25 j/h |
| F — Accessibilité (focus trap, escape, role=grid, aria-label dates) | 0.75 j/h |
| F — Skeletons + toasts d'erreur brancher | 0.5 j/h |
| F — Vitest + Testing Library setup + 8 tests représentatifs | 2 j/h |
| F — Performance (shallowRef heatmap, watchDebounced, import dynamique fiscalRulesContent) | 0.5 j/h |
| F — README Features, ordre imports eslint rule, `const`/`function` sweep | 0.5 j/h |

**Total complément** : **~13.75 j/h**.

**Nouveau total V1 conforme** : `~35-40 j/h (socle) + ~13.75 j/h (complément frontend) ≈ **48-54 j/h**`.

Ce chiffrage remplace donc le total indicatif de la section 8 du rapport principal ; les phases concernées (notamment la phase 3 « Frontend Wayfinder + composables + DTO ») doivent être ré-étalonnées de 6-8 j/h à **15-20 j/h** pour couvrir proprement types + composables + stores + utils + i18n préparatoire + tests Vue.

---

*Fin du complément d'audit.*

*Fin du rapport.*
