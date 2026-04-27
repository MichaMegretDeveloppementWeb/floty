# Plan de remise aux normes — MVP démo → V1 conforme

> **Date** : 24/04/2026
> **Auteur** : plan senior validé par le prestataire
> **Source de vérité** : `project-management/audits/rapport-MVP-demo-derive-regles.md`
> **Règles applicables** : `project-management/implementation-rules/*.md` (12 documents) + ADRs `project-management/decisions/*.md` (12 documents) + `CLAUDE.md`
> **Stack cible** : Laravel 13, PHP 8.5, Inertia v3, Vue 3.5, TypeScript 6, Spatie Laravel Data 4, Tailwind v4, MySQL 8, Pinia 3, Vitest 4

---

## Comment lire ce document

Ce plan est un **battle plan**. Il est écrit pour qu'un dev senior (le prestataire ou un second qui arriverait en renfort) puisse l'exécuter phase par phase sans avoir à re-décider à chaque étape. Les décisions structurantes sont tranchées dans le plan lui-même ou renvoient explicitement à un ADR à rédiger.

Chaque item suit un format strict :

- **Fichiers concernés** : chemins absolus relatifs à la racine `floty/`, avec ligne pertinente.
- **Problème** : description condensée du manquement (référencée au rapport d'audit).
- **Solution** : l'état cible après remise aux normes.
- **Méthode** : étapes d'implémentation + classes/fichiers/commandes nommées.
- **Estimation** : en j/h (jour-homme).
- **Dépendances** : items préalables (quand pertinent).

Le plan couvre **uniquement la remise aux normes du MVP démo actuel** — pas l'ajout de features V1 (déclarations, PDF, moteur multi-années). Ce périmètre reste traité par les phases 03 à 13 existantes dans `project-management/plan-implementation/tasks/`.

---

## Phasage d'ensemble

| Phase | Titre | Objectif | J/H estimés |
|---|---|---|---|
| 0 | Bloquants pré-refonte | Éliminer les bugs fonctionnels connus avant toute refonte | 2.5 |
| 1 | DTOs Spatie Laravel Data + génération TS | Poser la frontière PHP↔TS typée, source unique de vérité | 4.5 |
| 2 | Architecture hexagonale applicative | Actions / Services / Repositories, controllers = délégation pure | 7 |
| 3 | Authorization & sécurité | Policies, rate-limit, headers, credentials démo | 2.5 |
| 4 | Gestion d'erreurs | Exceptions typées, canaux de log, toasts front | 2.5 |
| 5 | Moteur fiscal — complétion | Pipeline règles versionnées, conversions, cache | 6 |
| 6 | Performance & agrégation | N+1, pagination, scopes, cache tags | 3 |
| 7 | Types TS frontend | Types générés + types manuels par domaine, suppression inline | 1.5 |
| 8 | Composables Vue | 8 composables manquants, extraction logique dupliquée | 3 |
| 9 | Utils, stores, constants | Formatters, helpers, Pinia, constantes par domaine | 2.5 |
| 10 | Wayfinder partout + HTTP v3 | Suppression URLs hardcodées, migration lib/http → useHttp | 2 |
| 11 | Refactor composants trop gros | Découpage WeekDrawer, Assignments/Index, FiscalRules/Index | 3 |
| 12 | Accessibilité & UX | Focus trap, role=grid, skeletons, z-index, toasts d'erreur | 2 |
| 13 | i18n — décision + préparation | ADR FR-only ou extraction strings | 0.75 |
| 14 | Tests | Feature (backend), Unit (fiscal/services), Vitest (front) | 7 |
| 15 | DX & ops | README, CI, monitoring, Sentry, secrets, build | 2 |
| **Total** | | | **~48.25 j/h** |

L'ordre est **bloquant** : la phase 1 (DTOs) conditionne la phase 2 (architecture) et la phase 7 (types TS). La phase 2 conditionne la phase 4 (gestion d'erreurs propre), la phase 5 (moteur fiscal servicé) et la phase 6 (performance). La phase 10 (Wayfinder) ne peut se faire qu'après les phases 8 et 9 pour éviter un double refactor sur des fichiers en cours de découpage. La phase 14 (tests) vient couvrir ce qui a été refait, **pas avant** (tests contre l'ancien code = dette).

Un Gantt synthétique est donné en fin de document.

---

## Phase 0 — Bloquants pré-refonte

**Objectif** : désamorcer les risques fonctionnels connus avant de commencer toute refonte structurelle. Ce ne sont pas des optimisations, ce sont des correctifs de bugs potentiellement présents qui fausseraient l'évaluation du reste. Aucune tâche de cette phase ne dépasse 0.5 j/h.

**Pourquoi** : un bug sur l'UNIQUE assignments ou sur le 366 jours en dur fausse immédiatement tout le reste (tests, démos, calculs fiscaux). On doit partir d'un MVP fonctionnellement sain.

### [0.1] Vérifier l'UNIQUE `(vehicle_id, date)` et le comportement soft-delete

**Fichiers concernés** :
- `database/migrations/2026_04_24_190005_create_assignments_table.php:57`
- `app/Http/Controllers/User/Planning/PlanningController.php` méthode `storeBulk`
- `database/seeders/DemoSeeder.php:250-281`

**Problème** : la migration actuelle pose un UNIQUE direct `(vehicle_id, date)` sans filtre sur `deleted_at`. Conséquence : une attribution soft-deletée verrouille le slot et empêche sa re-création. Par ailleurs, le `DB::table('assignments')->insertOrIgnore()` du controller et du seeder s'appuie sur cet index pour éviter les doublons — si le trigger ou l'index disparaissait (migration future), les doublons silencieux reviendraient.

**Solution** : remplacer l'UNIQUE simple par un mécanisme UNIQUE filtré par soft-delete, implémenté en MySQL via trigger `BEFORE INSERT`/`BEFORE UPDATE` qui lève `SQLSTATE '45000'` si une ligne active existe pour le même `(vehicle_id, date)`. Pattern déjà utilisé dans `2026_04_24_190010_create_fiscal_schema_triggers.php` pour `vehicle_fiscal_characteristics`.

**Méthode** :
1. Créer `database/migrations/2026_04_2X_XXXXXX_replace_assignments_unique_with_soft_delete_trigger.php` :
   ```php
   Schema::table('assignments', fn (Blueprint $t) => $t->dropUnique(['vehicle_id', 'date']));
   ```
2. Créer trigger `assignments_unique_active_insert` :
   ```sql
   BEFORE INSERT ON assignments FOR EACH ROW
   BEGIN
     IF EXISTS (SELECT 1 FROM assignments
                WHERE vehicle_id = NEW.vehicle_id
                  AND date = NEW.date
                  AND deleted_at IS NULL)
     THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'assignments: active duplicate for (vehicle_id, date)';
     END IF;
   END
   ```
3. Créer trigger équivalent `assignments_unique_active_update` (on check si `NEW.deleted_at IS NULL` et qu'il existe une autre ligne active).
4. Conserver le `NOT NULL check` + index `(vehicle_id, date, deleted_at)` pour la perf.
5. Commenter dans la migration : `// SQLite skip` (tests).
6. Ajouter un test Feature `AssignmentUniqueTriggerTest` :
   - insert → soft delete → re-insert même couple → OK.
   - insert → insert même couple sans soft-delete → `QueryException` attendue.

**Estimation** : 0.5 j/h
**Dépendances** : aucune

---

### [0.2] Supprimer les scaffolds et fichiers de debug

**Fichiers concernés** :
- `tests/Feature/ExampleTest.php`
- `tests/Unit/ExampleTest.php`
- `storage/app/smoke_test.php` (à vérifier, probablement déjà absent)
- `resources/js/pages/Dev/UiKitShowcase.vue` et `resources/js/pages/Dev/UiKitUserLayoutDemo.vue` : à conserver en local, mais exclure du build prod.

**Problème** : scaffolds Laravel `ExampleTest` inutiles, présence de pages Dev qui gonflent le bundle prod inutilement (70 Ko).

**Solution** : zéro scaffold, pages Dev exclues du build prod via gate env.

**Méthode** :
1. `rm tests/Feature/ExampleTest.php tests/Unit/ExampleTest.php`.
2. Vérifier `routes/web.php:20-26` : les routes `/dev/*` sont déjà gated par `App::environment('local')`, OK. Ajouter en complément un exclude Vite :
   ```ts
   // vite.config.ts, bloc build.rollupOptions
   external: process.env.NODE_ENV === 'production' ? [/\/pages\/Dev\//] : [],
   ```
   (vérifier que ça n'explose pas `import.meta.glob` Inertia ; si oui, déplacer les pages Dev hors de `pages/` et les réimporter manuellement dans une route dev).
3. Lancer `php artisan test --compact` pour confirmer.

**Estimation** : 0.25 j/h
**Dépendances** : aucune

---

### [0.3] Année fiscale 366 en dur → calcul bissextile dynamique

**Fichiers concernés** :
- `resources/js/Components/Features/Planning/WeekDrawer.vue:432` (`{{ preview.futureCumul }} j / 366`)
- `resources/js/pages/User/Assignments/Index.vue:343` (idem)
- `resources/js/Components/Features/Planning/MultiDatePicker.vue:64-100` (construction grille)
- `app/Services/Fiscal/FiscalCalculator.php:52-70` (`$year === 2024`)

**Problème** : la division `/ 366` est hardcodée à deux endroits du front. En 2025, ce sera 365 — toute valeur affichée au prorata sera fausse. La logique grille mois du `MultiDatePicker` part aussi d'hypothèses 2024 sur le nombre de semaines par mois.

**Solution** : centraliser dans un util `daysInYear(year)` pur, consommable depuis front (`utils/date.ts`) et back (`FiscalYearContext`). Rendre `WeekDrawer` / `Assignments/Index` dépendants de `useFiscalYear().daysInYear`.

**Méthode** :
1. Créer `resources/js/Utils/date/daysInYear.ts` (voir item 9.1) :
   ```ts
   export function daysInYear(year: number): 365 | 366 {
     return ((year % 4 === 0 && year % 100 !== 0) || year % 400 === 0) ? 366 : 365;
   }
   ```
2. Dans `useFiscalYear()` (item 8.6), exposer `daysInYear: ComputedRef<365 | 366>`.
3. Remplacer `/ 366` par `/ daysInYear` dans les deux templates Vue.
4. Côté PHP : créer `app/Services/Shared/Fiscal/FiscalYearContext.php` avec `daysInYear(int $year): int` + `currentYear(): int` (à compléter phase 2). Remplacer les usages hardcodés dans `FiscalCalculator`, `DashboardController`, `VehicleController`, `CompanyController`, `PlanningController`, `AssignmentController`, `HandleInertiaRequests`.
5. Tests :
   - `tests/Unit/Utils/DaysInYearTest.php` avec DataProvider [2000→366, 2024→366, 2025→365, 2100→365, 2400→366].
   - Pour TS : `resources/js/Utils/date/daysInYear.spec.ts` (attendra le setup Vitest phase 14).

**Estimation** : 0.5 j/h
**Dépendances** : aucune (le gros vient dans les phases 8-9)

---

### [0.4] Nettoyage `useToasts.ts` — emplacement et typage de retour

**Fichiers concernés** :
- `resources/js/composables/useToasts.ts` (actuel)
- cible : `resources/js/Composables/Shared/useToasts.ts`

**Problème** : le fichier vit dans `composables/` minuscule alors que la règle `structure-fichiers.md:244-247` impose `Composables/Shared/` ou `Composables/User/`. Le nom pluriel `useToasts` devrait être `useToasts` (pile de toasts → OK pluriel conservable) mais le **type de retour explicite** manque (`composables-services-utils.md` § 1 l'impose).

**Solution** : déplacer + typer.

**Méthode** :
1. `git mv resources/js/composables/useToasts.ts resources/js/Composables/Shared/useToasts.ts`. Si casing Windows pose souci, faire un `git mv` en deux temps (`_toasts.ts` intermédiaire).
2. Ajouter le type de retour :
   ```ts
   export type UseToastsReturn = {
     toasts: Readonly<Ref<Toast[]>>;
     push: (input: ToastInput) => string;
     dismiss: (id: string) => void;
     clear: () => void;
   };
   export function useToasts(): UseToastsReturn { ... }
   ```
3. Mettre à jour les imports (grep `from '@/composables/useToasts'`).
4. Supprimer le dossier `resources/js/composables/` vide.

**Estimation** : 0.25 j/h
**Dépendances** : aucune (préparatoire à la phase 8)

---

### [0.5] Tests unit `FiscalCalculator::calculate` — couverture minimale avant refonte

**Fichiers concernés** :
- `tests/Unit/Fiscal/FiscalCalculatorTest.php` (à créer)
- `app/Services/Fiscal/FiscalCalculator.php`

**Problème** : `BracketsCatalog2024Test.php` couvre uniquement le barème. Le calculateur qui assemble CO₂ + polluants + LCD + exonérations n'a **aucun test**. Toute refonte (phase 5) va le casser sans filet.

**Solution** : 12 tests minimum couvrant les branches principales du calculateur — servent de tests de non-régression avant refonte.

**Méthode** :
1. `php artisan make:test --unit Fiscal/FiscalCalculatorTest`.
2. DataProvider avec au moins 12 scénarios documentés :
   - VP WLTP standard, cumul 120 j, CO₂ 150 g → vérifier montant attendu à l'euro près.
   - VP NEDC non convertible (avant 2020), cumul 250 j.
   - VU PA (puissance administrative), cumul plein 366 j.
   - LCD 30 j → exonération totale.
   - LCD 29 j → pas d'exonération.
   - Électrique BEV → exonération CO₂ (LCD note 2024).
   - Hybride rechargeable WLTP bas → pollutants only.
   - Handicap R-2024-019 (si toggle activé).
   - Euro 6d-ISC-FCM catégorie 1 polluants.
   - Catégorie polluants par défaut si enum non couvert → test d'échec.
   - Année non 2024 → `InvalidArgumentException` (comportement actuel).
   - `FiscalBreakdown::toArray` : vérifier que chaque clé camelCase existe.
3. Utiliser factories `Vehicle::factory()->withFiscalCharacteristics(...)` après phase 2, ou arrays PHP directs pour l'instant.
4. Commande : `php artisan test --compact --filter=FiscalCalculator`.

**Estimation** : 1 j/h
**Dépendances** : aucune

---

## Phase 1 — DTOs Spatie Laravel Data + génération TypeScript

**Objectif** : poser la frontière contractuelle typée entre PHP et TypeScript. À la fin de cette phase, aucun `array` anonyme ne circule plus dans les `Inertia::render(...)`, et tous les types TS consommés par les composants Vue proviennent de fichiers générés automatiquement par Spatie TypeScript Transformer.

**Pourquoi** : c'est le manquement numéro 2 du rapport (impact maximal, effort forfaitaire à payer une fois). Sans DTO, les phases 2, 5, 7, 10 sont bâties sur du sable — un rename côté PHP passerait inaperçu côté TS.

### [1.1] Installer Spatie Laravel Data + TypeScript Transformer

**Fichiers concernés** :
- `composer.json`
- `config/typescript-transformer.php` (à générer)
- `config/data.php` (à générer)
- `.gitignore` (ajout `resources/js/types/generated/`)
- `package.json` (script `wayfinder:generate` probablement à renommer en chain)

**Problème** : le package `spatie/laravel-data` n'est pas installé. Aucun mécanisme de génération des types TS actif (rapport § 2.2).

**Solution** : packages installés, commande `php artisan typescript:transform` opérationnelle, fichiers générés dans `resources/js/types/generated/`, exclus du git.

**Méthode** :
1. `composer require spatie/laravel-data:^4.22 spatie/laravel-typescript-transformer:^2.5` — attention : c'est `spatie/laravel-typescript-transformer` (le wrapper Laravel), pas `spatie/typescript-transformer` direct.
2. `php artisan vendor:publish --tag=data-config`
3. `php artisan vendor:publish --tag=typescript-transformer-config`
4. Dans `config/typescript-transformer.php` :
   ```php
   'auto_discover_types' => [app_path('Data')],
   'collectors' => [Spatie\LaravelData\Support\TypeScriptTransformer\DataTypeScriptCollector::class],
   'transformers' => [
       Spatie\LaravelData\Support\TypeScriptTransformer\DataTypeScriptTransformer::class,
       Spatie\TypeScriptTransformer\Transformers\EnumTransformer::class,
   ],
   'output_file' => 'generated.d.ts',
   'output_path' => resource_path('js/types/generated'),
   ```
5. Dans `config/data.php` : activer `transform_values => Spatie\LaravelData\Support\Transformation\GlobalTransformersCollection` pour `DateTime → ISO string`.
6. `.gitignore` : ajouter `resources/js/types/generated/`.
7. `package.json` : remplacer `"build": "vite build"` par :
   ```json
   "build": "php artisan wayfinder:generate --with-form=true && php artisan typescript:transform && vite build"
   ```
   et pareil sur `dev` (scripts chainés via `concurrently` ou `npm-run-all`).
8. Tester : `php artisan typescript:transform` génère `resources/js/types/generated/generated.d.ts` avec les DTOs des phases suivantes.

**Estimation** : 0.5 j/h
**Dépendances** : aucune

---

### [1.2] Créer l'arborescence `app/Data/` + DTOs par domaine

**Fichiers concernés** : à créer
- `app/Data/User/Vehicle/VehicleListItemData.php`
- `app/Data/User/Vehicle/VehicleData.php`
- `app/Data/User/Vehicle/VehicleOptionData.php`
- `app/Data/User/Vehicle/VehicleFormOptionsData.php`
- `app/Data/User/Company/CompanyListItemData.php`
- `app/Data/User/Company/CompanyData.php`
- `app/Data/User/Company/CompanyOptionData.php`
- `app/Data/User/Company/CompanyColorOptionData.php`
- `app/Data/User/Assignment/AssignmentData.php`
- `app/Data/User/Assignment/AssignmentListItemData.php`
- `app/Data/User/Planning/PlanningHeatmapVehicleData.php` (remplace `Vehicle` inline heatmap)
- `app/Data/User/Planning/PlanningWeekData.php`
- `app/Data/User/Planning/PlanningWeekDaySlotData.php`
- `app/Data/User/Planning/PlanningWeekCompanyData.php`
- `app/Data/User/Planning/PlanningHeatmapPayloadData.php` (enveloppe page)
- `app/Data/User/Fiscal/FiscalPreviewData.php` (hérite ce qui est renvoyé par `PlanningController::previewTaxes`)
- `app/Data/User/Fiscal/FiscalBreakdownData.php` (refonte `FiscalBreakdown` existant)
- `app/Data/User/Fiscal/FiscalRuleData.php`
- `app/Data/User/Fiscal/FiscalRuleLegalReferenceData.php`
- `app/Data/User/Fiscal/FiscalRuleSectionGroupData.php`
- `app/Data/User/Dashboard/DashboardStatsData.php`
- `app/Data/User/Dashboard/DashboardQuickLinkData.php`
- `app/Data/User/Shared/FiscalYearContextData.php` (enrichit les shared props)
- `app/Data/User/Shared/AuthUserData.php` (shared props)
- `app/Data/User/Shared/FlashMessageData.php`

**Problème** : les controllers passent des `array` anonymes à Inertia. Les types TS sont redéclarés à la main dans chaque `.vue`. Zéro typage de frontière.

**Solution** : une classe Data par shape envoyée à Inertia. Le générateur TS produit `resources/js/types/generated/generated.d.ts` avec tous les types d'un coup.

**Méthode** :
1. `mkdir -p app/Data/User/{Vehicle,Company,Assignment,Planning,Fiscal,Dashboard,Shared}`.
2. Patron pour chaque DTO (exemple `VehicleListItemData`) :
   ```php
   namespace App\Data\User\Vehicle;

   use App\Enums\Vehicle\VehicleUserType;
   use App\Enums\Vehicle\EnergySource;
   use Spatie\LaravelData\Data;
   use Spatie\TypeScriptTransformer\Attributes\TypeScript;

   #[TypeScript]
   final class VehicleListItemData extends Data
   {
       public function __construct(
           public readonly int $id,
           public readonly string $licensePlate,
           public readonly string $brand,
           public readonly string $model,
           public readonly VehicleUserType $userType,
           public readonly EnergySource $energySource,
           public readonly ?int $co2Value,
           public readonly string $co2Method,
           public readonly int $cumulativeDaysYear,
           public readonly float $annualTaxDue,
           public readonly string $currentStatus,
           public readonly ?string $firstFrenchRegistrationDate,
       ) {}
   }
   ```
3. Pour `FiscalBreakdownData` : reprendre `FiscalBreakdown.php:41-59`, hériter `Data`, annoter `#[TypeScript]`, supprimer le `toArray()` manuel (Spatie Data le gère).
4. Ajouter `collect` helpers : `VehicleListItemData::collect($vehicles)` dans les controllers.
5. Pour les DTOs de page : créer des `PageData` enveloppes, ex :
   ```php
   #[TypeScript]
   final class VehicleIndexPageData extends Data
   {
       /** @param Collection<int, VehicleListItemData> $vehicles */
       public function __construct(
           public readonly Collection $vehicles,
           public readonly FiscalYearContextData $fiscal,
       ) {}
   }
   ```
6. Générer et vérifier : `php artisan typescript:transform`, ouvrir `resources/js/types/generated/generated.d.ts`, chaque type doit apparaître en `export type VehicleListItemData = { id: number; licensePlate: string; ... }`.

**Estimation** : 2 j/h
**Dépendances** : 1.1

---

### [1.3] Migrer les `FormRequest` vers validation Spatie Data

**Fichiers concernés** :
- `app/Http/Requests/User/Vehicle/StoreVehicleRequest.php`
- `app/Http/Requests/User/Company/StoreCompanyRequest.php`
- `app/Http/Requests/User/Auth/LoginRequest.php` (à conserver avec rate-limit)
- Nouveaux : `app/Data/User/Vehicle/StoreVehicleData.php`, `app/Data/User/Company/StoreCompanyData.php`, `app/Data/User/Planning/PreviewTaxesData.php`, `app/Data/User/Planning/BulkCreateAssignmentsData.php`, `app/Data/User/Assignment/VehicleDatesQueryData.php`.

**Problème** : les FormRequests font la validation mais le flux vers les Actions/Services utilise toujours des `array` non typés. Par ailleurs, `PlanningController::previewTaxes` et `vehicleDates` valident via `$request->validate()` inline au lieu d'un FormRequest.

**Solution** : chaque endpoint de mutation accepte un `Data` typé qui porte les règles de validation. Les FormRequest historiques délèguent à `Data::from($request)` ou sont supprimés.

**Méthode** :
1. Exemple pour `StoreVehicleData` :
   ```php
   #[TypeScript]
   final class StoreVehicleData extends Data
   {
       public function __construct(
           #[Required, StringType, Max(20)]
           public readonly string $licensePlate,
           #[Required, StringType, Max(100)]
           public readonly string $brand,
           // ...
       ) {}

       public static function rules(ValidationContext $context): array
       {
           return [
               'licensePlate' => ['required', 'string', 'max:20', new FrenchLicensePlateRule()],
               // règles dynamiques si besoin
           ];
       }
   }
   ```
2. Dans le controller :
   ```php
   public function store(StoreVehicleData $data, CreateVehicleAction $action): RedirectResponse
   {
       $vehicle = $action->execute($data, $this->user());
       return to_route('user.vehicles.index');
   }
   ```
3. Conserver `StoreVehicleRequest` uniquement pour `authorize()` → à terme remplacé par Policy (phase 3).
4. Pour `LoginRequest` : garder tel quel (rate-limit + session), ne pas migrer en Data.
5. Créer `PreviewTaxesData`, `BulkCreateAssignmentsData`, `VehicleDatesQueryData` — ce qui supprime les `abort(400)` actuels et remplace les `$request->validate()` inline.

**Estimation** : 1 j/h
**Dépendances** : 1.2

---

### [1.4] Migrer les payloads `Inertia::render` vers Data

**Fichiers concernés** : tous les controllers `app/Http/Controllers/User/**/*.php`.

**Problème** : les controllers passent encore des `array` à `Inertia::render(...)`. Avec les DTOs créés en 1.2 mais pas branchés, la génération TS serait inutile.

**Solution** : chaque `render` reçoit un `Data` ou une `DataCollection`.

**Méthode** :
1. Exemple pour `VehicleController@index` :
   ```php
   return Inertia::render('User/Vehicles/Index', [
       'vehicles' => VehicleListItemData::collect(
           $this->vehicleListRepository->listActiveWithCumuls($fiscalYear)
       ),
       'fiscal' => FiscalYearContextData::fromContext($this->fiscalContext),
   ]);
   ```
2. Faire ça pour 6 controllers × ~3 actions = ~18 endroits.
3. Ajuster le middleware `HandleInertiaRequests` :
   ```php
   'auth' => AuthUserData::fromRequest($request),
   'flash' => FlashMessageData::fromSession($request->session()),
   'fiscal' => FiscalYearContextData::fromContext($this->fiscalContext),
   ```
4. Regénérer les types TS : `php artisan typescript:transform`.
5. Phase 7 s'occupera de la consommation côté Vue.

**Estimation** : 1 j/h
**Dépendances** : 1.2, 1.3

---

## Phase 2 — Architecture hexagonale applicative

**Objectif** : atteindre une chaîne stricte `Controller → Action → Service → Repository → Model` partout. À la fin de cette phase, aucun controller ne contient de `Model::query()`, aucun `DB::table()`, aucun calcul métier. La couche métier est entièrement testable sans HTTP.

**Pourquoi** : c'est le manquement numéro 1 du rapport (impact bloquant V1). Sans cette séparation, les tests unitaires ciblés sont impossibles, le moteur fiscal ne peut pas être mis en cache de façon fiable, et toute évolution V1.2 (facturation) serait greffée sur un monolithe.

### [2.1] Créer l'arborescence des couches + interfaces

**Fichiers concernés** : à créer
- `app/Actions/User/{Vehicle,Company,Assignment,Planning}/`
- `app/Services/User/{Vehicle,Company,Assignment,Planning,Dashboard}/`
- `app/Services/Shared/{Fiscal,FiscalYear,Cache}/`
- `app/Repositories/User/{Vehicle,Company,Assignment,FiscalRule}/`
- `app/Repositories/Shared/{Cache}/`
- `app/Contracts/Repositories/User/{Vehicle,Company,Assignment,FiscalRule}/`

**Problème** : arborescence inexistante (rapport § 2.1).

**Solution** : dossiers + fichiers stubs + binding.

**Méthode** :
1. Créer les répertoires listés.
2. Pour chaque Repository, créer **interface** + **implémentation** :
   - `app/Contracts/Repositories/User/Vehicle/VehicleListReadRepositoryInterface.php`
   - `app/Repositories/User/Vehicle/VehicleListReadRepository.php`
3. Binder dans `app/Providers/RepositoryServiceProvider.php` :
   ```php
   $this->app->bind(
       VehicleListReadRepositoryInterface::class,
       VehicleListReadRepository::class,
   );
   ```
4. Déplacer `app/Services/Fiscal/` → `app/Services/Shared/Fiscal/` (correction rapport § 2.8). Update namespaces.

**Estimation** : 0.5 j/h
**Dépendances** : 1.x

---

### [2.2] Extraire la couche Repository (lectures complexes)

**Fichiers concernés** : nouveaux
- `app/Repositories/User/Assignment/AssignmentReadRepository.php`
- `app/Repositories/User/Assignment/AssignmentWriteRepository.php`
- `app/Repositories/User/Vehicle/VehicleListReadRepository.php`
- `app/Repositories/User/Vehicle/VehicleWriteRepository.php`
- `app/Repositories/User/Company/CompanyListReadRepository.php`
- `app/Repositories/User/Company/CompanyWriteRepository.php`
- `app/Repositories/User/FiscalRule/FiscalRuleReadRepository.php`
- interfaces miroir dans `app/Contracts/Repositories/User/`

**Problème** : les controllers font `Assignment::query()->whereYear(...)` etc. partout (rapport § 2.5).

**Solution** : les requêtes complexes vivent dans des méthodes nommées du repository, retournent des Collections ou des primitives PHP, jamais des `Builder`.

**Méthode** :
1. `AssignmentReadRepository` — méthodes extraites du code actuel :
   - `cumulByPairForYear(int $year): array` — remplace le code répété dans Dashboard/Vehicle/Company.
   - `listByVehicleAndWeek(int $vehicleId, Carbon $weekStart): Collection`.
   - `listVehicleBusyDates(int $vehicleId, int $year): Collection<int, string>`.
   - `listForYear(int $year): Collection<int, Assignment>`.
2. `AssignmentWriteRepository` :
   - `bulkInsertIgnoringDuplicates(BulkCreateAssignmentsData $data): int` — retourne le nombre de lignes insérées. Implémentation : `insertOrIgnore` **mais** avec observer firing (voir item 4.x pour `AssignmentInvalidatesDeclarations`).
3. `VehicleListReadRepository::listActiveWithCumuls(int $fiscalYear): Collection<int, array{vehicle: Vehicle, cumul: int, taxDue: float}>` — méthode qui précharge `fiscalCharacteristics` + agrège les cumuls en une seule requête SQL (pas N+1).
4. Tests : `tests/Unit/Repositories/User/Assignment/AssignmentReadRepositoryTest.php` avec `RefreshDatabase` + factory, 5 tests par méthode publique.
5. Binder dans `RepositoryServiceProvider`.

**Estimation** : 2 j/h
**Dépendances** : 2.1

---

### [2.3] Créer la couche Service

**Fichiers concernés** : nouveaux
- `app/Services/User/Dashboard/DashboardSummaryService.php`
- `app/Services/User/Vehicle/VehicleCreationService.php`
- `app/Services/User/Vehicle/VehicleFiscalCharacteristicsService.php`
- `app/Services/User/Vehicle/VehicleListProjectionService.php` (agrège cumul + tax par véhicule)
- `app/Services/User/Company/CompanyCreationService.php`
- `app/Services/User/Company/CompanyListProjectionService.php`
- `app/Services/User/Planning/PlanningHeatmapService.php`
- `app/Services/User/Planning/PlanningWeekService.php`
- `app/Services/User/Planning/PlanningPreviewService.php`
- `app/Services/User/Assignment/AssignmentBulkCreateService.php`
- `app/Services/User/Assignment/AssignmentCalendarService.php`
- `app/Services/Shared/Fiscal/FiscalYearContext.php`

**Problème** : tout le calcul d'agrégation vit dans les controllers (rapport § 2.5).

**Solution** : la logique métier vit dans des services `final readonly class` avec dépendances injectées en constructeur.

**Méthode** :
1. Patron pour `PlanningPreviewService` (exemple le plus complexe) :
   ```php
   final readonly class PlanningPreviewService
   {
       public function __construct(
           private AssignmentReadRepositoryInterface $assignmentRepo,
           private VehicleListReadRepositoryInterface $vehicleRepo,
           private FiscalCalculator $calculator,
       ) {}

       public function previewIncremental(PreviewTaxesData $input): FiscalPreviewData
       {
           $vehicle = $this->vehicleRepo->findWithFiscalChars($input->vehicleId);
           $currentCumul = $this->assignmentRepo->countForPair($input->vehicleId, $input->companyId, $vehicle->year);
           $newDaysCount = $this->countNewDays($input, $vehicle->year);

           $before = $this->calculator->calculate($vehicle, $currentCumul);
           $after = $this->calculator->calculate($vehicle, $currentCumul + $newDaysCount);

           return FiscalPreviewData::fromResults($before, $after, $newDaysCount);
       }
   }
   ```
2. `DashboardSummaryService::build(int $fiscalYear): DashboardStatsData` — remplace `DashboardController` lignes 25-63.
3. `PlanningHeatmapService::build(int $fiscalYear): PlanningHeatmapPayloadData` — remplace `PlanningController@index` lignes 33-123.
4. Exemple avant/après `PlanningController@index` (le plus lourd) :
   ```php
   // AVANT — 90 lignes de logique
   public function index(): Response {
       $year = 2024;
       $assignments = Assignment::query()->whereYear('date', $year)->get();
       // ... 80 lignes de groupBy, boucle, calcul...
       return Inertia::render('User/Planning/Index', [...]);
   }

   // APRÈS — 6 lignes
   public function index(PlanningHeatmapService $service): Response
   {
       return Inertia::render(
           'User/Planning/Index',
           $service->build($this->fiscalYear->currentYear())->toArray()
       );
   }
   ```
5. Tests : 1 test unit par service (avec mocks des repositories) + 1 test feature par controller.

**Estimation** : 2.5 j/h
**Dépendances** : 2.1, 2.2

---

### [2.4] Créer la couche Action

**Fichiers concernés** : nouveaux
- `app/Actions/User/Vehicle/CreateVehicleAction.php`
- `app/Actions/User/Company/CreateCompanyAction.php`
- `app/Actions/User/Assignment/BulkCreateAssignmentsAction.php`

**Problème** : `VehicleController::store()` fait `Vehicle::create(...)` puis `VehicleFiscalCharacteristics::create(...)` dans `DB::transaction`. La transaction métier devrait vivre dans une Action (`architecture-solid.md:322-355`).

**Solution** : l'Action orchestre services + transactions + events. Un seul point d'entrée métier par intention utilisateur.

**Méthode** :
1. `CreateVehicleAction::execute(StoreVehicleData $data, User $actor): Vehicle` :
   ```php
   final readonly class CreateVehicleAction
   {
       public function __construct(
           private VehicleCreationService $vehicleService,
           private VehicleFiscalCharacteristicsService $fiscalCharService,
           private DatabaseManager $db,
       ) {}

       public function execute(StoreVehicleData $data, User $actor): Vehicle
       {
           return $this->db->transaction(function () use ($data, $actor): Vehicle {
               $vehicle = $this->vehicleService->create($data, $actor);
               $this->fiscalCharService->createInitial($vehicle, $data);
               VehicleCreated::dispatch($vehicle);
               return $vehicle;
           });
       }
   }
   ```
2. `BulkCreateAssignmentsAction::execute(BulkCreateAssignmentsData $data, User $actor): int` — retourne le nb d'attributions créées. Dispatch event `AssignmentsBulkCreated` pour invalider le cache fiscal (phase 5).
3. Tests Feature via controller, tests Unit via mocks.

**Estimation** : 1 j/h
**Dépendances** : 2.3

---

### [2.5] Refactoriser les controllers — délégation pure

**Fichiers concernés** : tous les `app/Http/Controllers/User/**/*.php`

**Problème** : controllers obèses (PlanningController 300L, VehicleController 156L, DashboardController 66L...).

**Solution** : chaque méthode controller ≤ 20 lignes, une seule responsabilité = (1) résoudre dépendances (2) déléguer (3) render.

**Méthode** :
1. Patron pour un controller refactorisé (`VehicleController` exemple) :
   ```php
   final class VehicleController extends Controller
   {
       public function index(
           VehicleListProjectionService $service,
           FiscalYearContext $fiscalContext,
       ): Response {
           $year = $fiscalContext->currentYear();
           return Inertia::render('User/Vehicles/Index', [
               'vehicles' => VehicleListItemData::collect($service->listWithCumuls($year)),
               'fiscal' => FiscalYearContextData::from($fiscalContext),
           ]);
       }

       public function create(): Response
       {
           return Inertia::render('User/Vehicles/Create', [
               'options' => VehicleFormOptionsData::default(),
           ]);
       }

       public function store(StoreVehicleData $data, CreateVehicleAction $action): RedirectResponse
       {
           $this->authorize('create', Vehicle::class);
           $vehicle = $action->execute($data, $this->user());
           return to_route('user.vehicles.index')->with('flash.success', 'Véhicule créé.');
       }
   }
   ```
2. Faire ça pour les 6 controllers User.
3. Supprimer `enumOptions()` méthode privée de `VehicleController` — déplacée dans `VehicleFormOptionsData::default()` (méthode statique).
4. Vérifier que `routes/user.php` ne change pas (noms de routes conservés).
5. `php artisan wayfinder:generate` pour rafraîchir.

**Estimation** : 1 j/h
**Dépendances** : 2.2, 2.3, 2.4

---

### [2.6] Scopes Eloquent manquants

**Fichiers concernés** :
- `app/Models/Vehicle.php`
- `app/Models/Company.php`
- `app/Models/Assignment.php`
- `app/Models/VehicleFiscalCharacteristics.php`

**Problème** : filtrages dupliqués à la main dans 4 controllers (rapport § 2.7).

**Solution** : scopes PHP 8+ `#[Scope]` typés.

**Méthode** :
1. Sur `Vehicle` :
   ```php
   #[Scope]
   protected function active(Builder $query): void { $query->whereNull('exit_date'); }

   public function currentFiscalCharacteristics(): ?VehicleFiscalCharacteristics
   {
       return $this->fiscalCharacteristics()->orderByDesc('effective_from')->first();
   }
   ```
2. Sur `Company` : scope `active` (filter `is_active=true`).
3. Sur `Assignment` : scopes `forYear(int $year)`, `forPair(int $vehicleId, int $companyId)`.
4. Sur `VehicleFiscalCharacteristics` : scope `current()`.
5. Tests unit : `tests/Unit/Models/VehicleScopesTest.php`.

**Estimation** : 0.5 j/h
**Dépendances** : aucune, peut être fait en parallèle de 2.1-2.5

---

### [2.7] Refactor `DemoSeeder` — factories + fixtures

**Fichiers concernés** :
- `database/seeders/DemoSeeder.php` (357 L)
- `database/factories/VehicleFactory.php`
- `database/factories/CompanyFactory.php`
- `database/factories/AssignmentFactory.php`
- `database/factories/VehicleFiscalCharacteristicsFactory.php`
- nouveau : `database/fixtures/demo/assignments_2024.json`
- nouveau : `database/Support/DemoScenarioBuilder.php`

**Problème** : `DemoSeeder` porte 360L de logique métier (rapport § 2.6).

**Solution** : Seeder = orchestration fine, données = factories + fixtures JSON.

**Méthode** :
1. Créer `VehicleFactory::state()` de base + états `withFiscalCharacteristics(array $spec)`, `electric()`, `hybrid()`, `plugIn()`.
2. Créer `CompanyFactory` + mapping postal via `Faker::addProvider` ou via state.
3. Créer `AssignmentFactory::state()` de base.
4. Extraire le plan d'attribution vers `database/fixtures/demo/assignments_2024.json` :
   ```json
   [{ "vehicleIndex": 0, "companyIndex": 2, "periodStart": "2024-01-15", "periodEnd": "2024-01-28", "driverIndex": null }, ...]
   ```
5. Créer `Database\Support\DemoScenarioBuilder` avec méthode `build(): void` qui lit le JSON, appelle `AssignmentBulkCreateService::create(...)` — **passe par la couche métier** (plus de `DB::table->insertOrIgnore` qui by-passe les observers).
6. `DemoSeeder::run()` = 20 lignes : instancie les factories, appelle `$builder->build()`.

**Estimation** : 0.5 j/h
**Dépendances** : 2.4

---

## Phase 3 — Authorization & sécurité

**Objectif** : aucune opération sans vérification d'autorisation explicite, rate-limiting étendu, credentials démo remplaçables par un flow propre, conformité ADR-0011.

**Pourquoi** : sécurité au sens strict (OWASP) + structure qui permet V2 multi-rôles par ajout pur (`architecture-solid.md` + ADR-0011).

### [3.1] Créer les Policies

**Fichiers concernés** : nouveaux
- `app/Policies/VehiclePolicy.php`
- `app/Policies/CompanyPolicy.php`
- `app/Policies/AssignmentPolicy.php`
- `app/Policies/FiscalRulePolicy.php`
- `app/Providers/AuthServiceProvider.php` (à créer)

**Problème** : `app/Policies/` inexistant, tous les `authorize()` des FormRequest retournent `true` (rapport § 2.3).

**Solution** : Policies en place, même si V1 mono-rôle elles retournent `true`. Structure prête pour V2.

**Méthode** :
1. `php artisan make:policy VehiclePolicy --model=Vehicle` puis même pour Company, Assignment, FiscalRule.
2. Implémenter 4 méthodes CRUD (`viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete`) :
   ```php
   public function viewAny(User $user): bool { return true; } // V1 mono-rôle
   public function create(User $user): bool { return true; }
   // ... etc.
   ```
3. Dans `AuthServiceProvider::boot()` :
   ```php
   Gate::policy(Vehicle::class, VehiclePolicy::class);
   // ... idem
   ```
4. Dans les controllers : `$this->authorize('create', Vehicle::class);` avant toute mutation.
5. Dans les FormRequests/Data : `authorize()` → `$this->user()->can('create', Vehicle::class)`.
6. Tests : `tests/Feature/Policies/VehiclePolicyTest.php`.

**Estimation** : 1 j/h
**Dépendances** : 2.5

---

### [3.2] Rate-limiting étendu

**Fichiers concernés** :
- `bootstrap/app.php`
- `app/Providers/AppServiceProvider.php`
- `routes/user.php`

**Problème** : seule la route login est rate-limitée. `planning/preview-taxes` et `planning/assignments` peuvent être spammés (rapport § 6.1).

**Solution** : rate-limit global sur le groupe `user.` + rate-limits spécifiques sur les endpoints JSON.

**Méthode** :
1. Dans `AppServiceProvider::boot()` :
   ```php
   RateLimiter::for('user-api', fn (Request $r) => Limit::perMinute(60)->by($r->user()?->id ?: $r->ip()));
   RateLimiter::for('user-mutations', fn (Request $r) => Limit::perMinute(20)->by($r->user()?->id ?: $r->ip()));
   ```
2. Dans `bootstrap/app.php` ou `routes/user.php` — grouper avec `middleware('throttle:user-api')` pour toutes les routes, et `throttle:user-mutations` pour les POST `previewTaxes`, `storeBulk`, `store` (véhicules, entreprises).
3. Tester : `tests/Feature/RateLimit/UserRateLimitTest.php` qui envoie 61 requêtes, vérifie un 429.

**Estimation** : 0.25 j/h
**Dépendances** : aucune

---

### [3.3] Credentials démo → flow de première connexion

**Fichiers concernés** :
- `database/seeders/UserSeeder.php:19`
- Migration à ajouter : `2026_XX_XX_add_must_change_password_to_users_table.php`
- `app/Models/User.php`
- `app/Http/Middleware/EnsurePasswordChanged.php` (nouveau)
- `app/Http/Controllers/User/Auth/ForcePasswordChangeController.php` (nouveau)
- `resources/js/pages/User/Auth/ForcePasswordChange.vue` (nouveau)

**Problème** : `admin@floty.test / password` en clair dans `UserSeeder` (rapport § 6.3). Interdit en V1.

**Solution** : flag `must_change_password` sur `users`, middleware qui redirige vers une page de changement si flag vrai, seeder qui fixe `must_change_password=true` + un mot de passe aléatoire 32 chars fourni via `.env.demo` jamais commité.

**Méthode** :
1. Migration : `$table->boolean('must_change_password')->default(false);` + `$table->timestamp('password_changed_at')->nullable();`.
2. `UserSeeder` :
   ```php
   $password = env('DEMO_INITIAL_PASSWORD') ?? Str::password(32);
   User::updateOrCreate(['email' => 'admin@floty.test'], [
       'password' => Hash::make($password),
       'must_change_password' => true,
   ]);
   if (!app()->environment('production')) {
       logger()->info("DEMO password: {$password}");
   }
   ```
3. `EnsurePasswordChanged` middleware : si `auth()->user()?->must_change_password`, redirige vers `user.auth.force-password-change`. Sauf si on y est déjà ou qu'on se déconnecte.
4. Page Vue minimaliste avec `useForm({ password, passwordConfirmation })`, validation backend via `ForcePasswordChangeData` + `ResetPasswordRule::min(12)`.
5. Test Feature : `ForcePasswordChangeTest` (3 scénarios).

**Estimation** : 0.75 j/h
**Dépendances** : 3.1

---

### [3.4] Audit CSRF / session / cookies sécurisés

**Fichiers concernés** :
- `config/session.php`
- `.env` (production)
- `app/Http/Middleware/SecureHeaders.php` (nouveau — cf. ADR-0011 § 6)

**Problème** : ADR-0011 liste 6 headers obligatoires en prod, non présents.

**Solution** : middleware `SecureHeaders` branché sur le group `web` en prod, session cookie secure + lax.

**Méthode** :
1. `config/session.php` :
   ```php
   'secure' => env('SESSION_SECURE_COOKIE', !app()->isLocal()),
   'same_site' => 'lax',
   'http_only' => true,
   'lifetime' => 120,
   ```
2. Créer `SecureHeaders` middleware qui pose HSTS + X-Frame-Options + X-Content-Type-Options + CSP (valeurs ADR-0011 § 6).
3. Enregistrer dans `bootstrap/app.php` : `->withMiddleware(fn ($m) => $m->web(append: [SecureHeaders::class]))`.
4. Test : `tests/Feature/Security/SecureHeadersTest.php` assert presence en env prod.

**Estimation** : 0.5 j/h
**Dépendances** : aucune

---

## Phase 4 — Gestion d'erreurs

**Objectif** : aucun stack trace ne doit atteindre l'utilisateur, chaque erreur est typée, loguée dans un canal thématique, remontée sous forme de toast français.

**Pourquoi** : conforme à `gestion-erreurs.md` entier. Aujourd'hui, le front silence les erreurs (`catch {}`), le back jette des `InvalidArgumentException` brutes — UX catastrophique, debug impossible.

### [4.1] Créer l'arborescence Exceptions métier

**Fichiers concernés** : nouveaux
- `app/Exceptions/Vehicle/VehicleNotFoundException.php`
- `app/Exceptions/Vehicle/VehicleCreationException.php`
- `app/Exceptions/Vehicle/VehicleUnavailableException.php`
- `app/Exceptions/Company/CompanyCreationException.php`
- `app/Exceptions/Assignment/AssignmentConflictException.php`
- `app/Exceptions/Assignment/BulkCreateAssignmentsException.php`
- `app/Exceptions/Fiscal/FiscalConfigurationException.php`
- `app/Exceptions/Fiscal/FiscalRuleNotApplicableException.php`
- `app/Exceptions/Fiscal/UnsupportedFiscalYearException.php`
- `app/Exceptions/Planning/InvalidPlanningParametersException.php`

**Problème** : `BaseAppException` existe mais **aucune** sous-classe (rapport § 2.4).

**Solution** : 10 classes typées, chacune avec `technicalMessage` + `userMessage` FR, factory methods nommées.

**Méthode** :
1. Patron :
   ```php
   final class UnsupportedFiscalYearException extends BaseAppException
   {
       public static function forYear(int $year): self
       {
           return new self(
               technicalMessage: "Fiscal year {$year} not supported by rule engine.",
               userMessage: 'Cette année n\'est pas prise en charge par le moteur fiscal. Veuillez contacter le support.',
           );
       }
   }
   ```
2. Remplacer dans `FiscalCalculator.php` lignes 52, 58, 61, 156 les `InvalidArgumentException` par les exceptions typées.
3. Remplacer dans `PlanningController::week` et `AssignmentController::vehicleDates` les `abort(400, ...)` par des exceptions `InvalidPlanningParametersException`.

**Estimation** : 1 j/h
**Dépendances** : 2.3

---

### [4.2] Canaux de log thématiques

**Fichiers concernés** :
- `config/logging.php`
- `app/Exceptions/Handler.php` (ou `bootstrap/app.php::withExceptions`)

**Problème** : pas de canaux dédiés (rapport § 2.4).

**Solution** : canaux `fiscal`, `vehicles`, `companies`, `assignments`, `auth`, `security` avec rétention dédiée.

**Méthode** :
1. Dans `config/logging.php::channels` :
   ```php
   'fiscal' => ['driver' => 'daily', 'path' => storage_path('logs/fiscal.log'), 'level' => 'info', 'days' => 90],
   'vehicles' => [...],
   'companies' => [...],
   'assignments' => [...],
   'auth' => [...],
   'security' => [...],
   ```
2. Dans `bootstrap/app.php::withExceptions` : handler global qui mappe `BaseAppException` → canal ciblé + renvoi Inertia 422 ou flash + redirect selon le contexte :
   ```php
   ->render(function (BaseAppException $e, Request $r) {
       Log::channel($this->channelFor($e))->error($e->getMessage(), ['ex' => $e]);
       if ($r->header('X-Inertia')) {
           return back()->with('flash.error', $e->getUserMessage());
       }
       return response()->view('errors.generic', ['message' => $e->getUserMessage()], 500);
   });
   ```

**Estimation** : 0.5 j/h
**Dépendances** : 4.1

---

### [4.3] Toasts frontend branchés sur les erreurs

**Fichiers concernés** :
- `resources/js/Components/Features/Planning/WeekDrawer.vue:194-196, 201-221`
- `resources/js/pages/User/Assignments/Index.vue:124-147, 182-184, 189-208`
- `resources/js/pages/User/Planning/Index.vue:66-75`
- Nouveau : `resources/js/Composables/Shared/useFlashToasts.ts`

**Problème** : 4+ `catch {}` silencieux (rapport § 2.4).

**Solution** : toute branche `catch` émet un toast. Un composable `useFlashToasts()` lit les `flash.success`/`flash.error` depuis les shared props Inertia et les pousse automatiquement.

**Méthode** :
1. Créer `useFlashToasts()` qui `watch(() => usePage().props.flash)` et appelle `useToasts().push({ tone: ..., title: ... })`.
2. L'invoquer **une fois** dans `UserLayout.vue` pour capturer tous les flashes.
3. Dans chaque `catch` local, remplacer `{ /* silent */ }` par :
   ```ts
   catch (e: unknown) {
     useToasts().push({ tone: 'error', title: 'Impossible de calculer la prévision fiscale.', description: String(e) });
     preview.value = null;
   }
   ```
4. À terme, ces `catch` disparaissent quand les appels passent par `useHttp` Inertia v3 (phase 10) qui gère automatiquement les flashes.

**Estimation** : 0.5 j/h
**Dépendances** : 0.4, 4.1, 4.2

---

### [4.4] Remplacement des `abort(400)` par Data + validation

**Fichiers concernés** :
- `app/Http/Controllers/User/Planning/PlanningController.php:137-138, 200-262`
- `app/Http/Controllers/User/Assignment/AssignmentController.php:72`

**Problème** : `abort(400, 'Paramètres vehicleId et week requis.')` (rapport § 2.4). Bypass FormRequest, pas de log, message en dur.

**Solution** : ces endpoints reçoivent un `PreviewTaxesData`, `VehicleDatesQueryData`, `PlanningWeekQueryData` (créés en 1.3). Plus aucun `abort` dans les controllers.

**Méthode** :
1. Déjà fait en 1.3 — vérifier que tous les endpoints ont leur Data associée.
2. Ajouter tests Feature : `tests/Feature/Http/Controllers/User/Planning/PlanningPreviewTaxesTest.php` avec 3 cas d'invalidité + happy path.

**Estimation** : 0.5 j/h
**Dépendances** : 1.3, 4.1

---

## Phase 5 — Moteur fiscal complet

**Objectif** : atteindre le périmètre fiscal ADR-0009 conforme pour 2024, architecture pipeline versionnée, cache fonctionnel, 100+ scénarios de tests.

**Pourquoi** : le moteur fiscal est le **cœur métier** de Floty. Aucun raccourci toléré (CLAUDE.md : « Ne jamais prendre de raccourci sur les calculs fiscaux »). Aujourd'hui : MVP simplifié, plusieurs R-2024-xxx non implémentés.

### [5.1] Implémenter R-2024-017 (hybride conditionnelle)

**Fichiers concernés** :
- `app/Services/Shared/Fiscal/Rules/` (nouveau dossier)
- `app/Services/Shared/Fiscal/FiscalCalculator.php`

**Problème** : R-2024-017 annoncé non implémenté (rapport § 2.10).

**Solution** : règle encapsulée en pipeline, appliquée si `energy_source in [hybrid_electric_gas, hybrid_diesel]` et autonomie > 50 km avec CO₂ ≤ 50 g/km.

**Méthode** :
1. Créer `app/Services/Shared/Fiscal/Rules/RuleR2024_017_HybridConditionalExemption.php` qui implémente `FiscalRuleInterface::apply(FiscalContext $ctx): ?FiscalAdjustment`.
2. Structure : la règle check les conditions, si vraies retourne un `FiscalAdjustment::co2Exempt()`.
3. Câbler dans un `FiscalRulePipeline` (phase 5.4).
4. Tests : minimum 4 cas (activée, désactivée, autonomie insuffisante, CO₂ > 50).

**Estimation** : 1 j/h
**Dépendances** : 2.3

---

### [5.2] Implémenter R-2024-013 catégorisation polluants calculée

**Fichiers concernés** :
- `app/Services/Shared/Fiscal/PollutantCategoryResolver.php` (nouveau)
- `app/Services/Shared/Fiscal/FiscalCalculator.php:104`

**Problème** : aujourd'hui on lit `pollutant_category` depuis `vehicle_fiscal_characteristics` (fourni par saisie utilisateur). C'est contraire à R-2024-013 qui demande **calcul** depuis `euro_standard` + `fuel_type` + `first_french_registration_date`.

**Solution** : résolveur qui prend `(EuroStandard, EnergySource, ?date)` et retourne `PollutantCategory`. Le champ DB devient une info dérivée (ou historisée pour cohérence V1→V2).

**Méthode** :
1. Matrice R-2024-013 encodée dans le résolveur (tableau `match`).
2. Appeler depuis `FiscalCalculator::pollutantCategoryFor($fiscal)`.
3. Si valeur DB diffère, log warning dans canal `fiscal`.
4. Tests : 20+ combinaisons (DataProvider).

**Estimation** : 1.25 j/h
**Dépendances** : 2.3

---

### [5.3] Conversion NEDC→WLTP

**Fichiers concernés** :
- `app/Services/Shared/Fiscal/Co2MethodResolver.php` (nouveau)
- `app/Services/Shared/Fiscal/FiscalCalculator.php:170-184`

**Problème** : pas de conversion NEDC → WLTP (rapport § 2.10). Les véhicules avec uniquement NEDC sont taxés selon le barème NEDC, mais R-2024-010 en annonce la conversion depuis 2020.

**Solution** : résolveur qui (1) détermine la méthode de calcul (WLTP prioritaire), (2) si seulement NEDC, convertit via coefficient réglementaire. Si avant 2020, fallback PA.

**Méthode** :
1. Encoder les coefficients de conversion dans une constante versionnée par règle (recherches fiscales `project-management/recherches-fiscales/`).
2. Méthode `resolve(VehicleFiscalCharacteristics $f): Co2MethodResult` retournant `(method, co2Used, conversionApplied)`.
3. Dans `FiscalBreakdownData` : ajouter champ `co2MethodResolved`.
4. Tests : 10 cas.

**Estimation** : 1 j/h
**Dépendances** : 5.1, 5.2

---

### [5.4] `FiscalRulePipeline` versionné

**Fichiers concernés** :
- `app/Services/Shared/Fiscal/FiscalRulePipeline.php` (nouveau)
- `app/Services/Shared/Fiscal/FiscalRuleInterface.php` (nouveau)
- `app/Services/Shared/Fiscal/Rules/2024/` (nouveau — 10 règles 2024)
- `app/Services/Shared/Fiscal/BracketsCatalog2024.php` (à déplacer sous `Rules/2024/`)
- `config/fiscal.php` (nouveau)

**Problème** : `BracketsCatalog2024` couple le code à 2024. Pas d'extension facile à 2025.

**Solution** : pipeline de règles `FiscalRuleInterface` ordonnées, sélectionnées par année, configurables via `config/fiscal.php`. Le `FiscalCalculator` devient un orchestrateur qui applique le pipeline.

**Méthode** :
1. Définir `FiscalRuleInterface::apply(FiscalContext $ctx): FiscalContext` (retour d'un contexte enrichi, pattern pipeline).
2. Créer 10 classes `RuleR2024_0XX_Xxx.php` dans `Rules/2024/`.
3. `config/fiscal.php` :
   ```php
   return [
       'rules_by_year' => [
           2024 => [RuleR2024_001::class, RuleR2024_002::class, ...],
       ],
   ];
   ```
4. `FiscalCalculator` devient :
   ```php
   public function calculate(Vehicle $v, int $cumul): FiscalBreakdownData {
       $rules = config("fiscal.rules_by_year.{$year}") ?? throw UnsupportedFiscalYearException::forYear($year);
       return $this->pipeline->apply($ctx, $rules)->toBreakdown();
   }
   ```
5. Tests : structure inchangée, 12 scénarios Phase 0.5 passent toujours.

**Estimation** : 1.75 j/h
**Dépendances** : 5.1, 5.2, 5.3

---

### [5.5] Arrondis R-2024-003 — half-up commercial

**Fichiers concernés** :
- `app/Services/Shared/Fiscal/Money.php` (nouveau Value Object)
- `app/Services/Shared/Fiscal/FiscalCalculator.php`

**Problème** : arrondi actuel probablement PHP `round()` standard. R-2024-003 demande half-up commercial au cent.

**Solution** : un Value Object `Money` qui porte la règle.

**Méthode** :
1. `Money::halfUpRound(float $euros): self` qui fait `(int) floor($euros * 100 + 0.5) / 100`.
2. Tests : 50 / 0,005 / -50,005 / edge cases.

**Estimation** : 0.5 j/h
**Dépendances** : aucune

---

### [5.6] Cache agrégats via CacheTagsManager

**Fichiers concernés** :
- `app/Services/Shared/Cache/CacheTagsManager.php` (existant, non utilisé)
- Tous les services phase 2.3
- `app/Listeners/Fiscal/InvalidateAggregatesOnAssignmentMutation.php` (nouveau)
- `app/Events/Assignment/AssignmentsBulkCreated.php`, `AssignmentsSoftDeleted.php`

**Problème** : `CacheTagsManager` n'est appelé nulle part (rapport § 7.2).

**Solution** : chaque service d'agrégation (Dashboard, VehicleListProjection, PlanningHeatmap) wrappe son calcul dans `Cache::tags([...])->remember(...)`. Listeners sur events invalident le cache.

**Méthode** :
1. Clé de cache : `agg:planning-heatmap:{year}`, `agg:dashboard:{year}`, `agg:vehicle-list:{year}:{vehicleId?}`.
2. Tags : `['fiscal', "year:{$year}"]`.
3. Listener `InvalidateAggregatesOnAssignmentMutation` : `Cache::tags(['fiscal', "year:{$year}"])->flush()`.
4. Events dispatchés depuis `AssignmentBulkCreateService` et `AssignmentWriteRepository::softDelete`.
5. Tests : 1 test d'invalidation, 1 test de cache hit.

**Estimation** : 1.25 j/h
**Dépendances** : 2.3, 5.4

---

### [5.7] Tests exhaustifs `FiscalCalculator` — 100+ scénarios

**Fichiers concernés** :
- `tests/Unit/Services/Shared/Fiscal/FiscalCalculatorTest.php` (extension de 0.5)
- `tests/Unit/Services/Shared/Fiscal/Rules/*Test.php` (une par règle R-2024-XXX)

**Problème** : 12 scénarios en Phase 0.5 — insuffisant pour la confiance V1.

**Solution** : 100+ scénarios via `DataProvider`. Chaque règle R-2024-XXX a son test unit dédié.

**Méthode** :
1. Ajouter `$this->dataProviderCases` découpés en 6 groupes :
   - Barème CO₂ WLTP (20 cas)
   - Barème CO₂ NEDC (15 cas)
   - Barème PA (10 cas)
   - Exonérations (15 cas — LCD, handicap, électrique, hybride)
   - Polluants par catégorie (15 cas)
   - Prorata 366/365 (15 cas + cas mi-année)
   - Cas limites (10 cas : cumul 0, année future, enum inconnu, montant 0)
2. Les valeurs attendues sont sourcées des exemples `recherches-fiscales/` (Florian a déjà rédigé plusieurs exemples A-E).

**Estimation** : 0.5 j/h (si exemples déjà rédigés) — sinon 1 j/h.
**Dépendances** : 5.1-5.6

---

## Phase 6 — Performance & agrégation

**Objectif** : éliminer les N+1, paginer les listes, cache opérationnel, respect `performance-ui.md`.

**Pourquoi** : aujourd'hui le dashboard est à ~300-500 ms en dev sur 10 véhicules. À 100 véhicules × 50 entreprises, on est sur du multi-seconde.

### [6.1] Éliminer les N+1

**Fichiers concernés** :
- `app/Repositories/User/Vehicle/VehicleListReadRepository.php` (créé en 2.2)
- `app/Services/User/Vehicle/VehicleListProjectionService.php` (créé en 2.3)

**Problème** : `Vehicle::find($id)` dans les boucles (rapport § 7.1).

**Solution** : `->with('fiscalCharacteristics')` + `whereIn` + `keyBy` + agrégation SQL précalculée.

**Méthode** :
1. `VehicleListProjectionService::listWithCumuls(int $year)` :
   ```php
   $cumuls = $this->assignmentRepo->cumulByPairForYear($year); // 1 requête groupée
   $vehicles = $this->vehicleRepo->listActive(['fiscalCharacteristics' => fn ($q) => $q->current()]); // 1 requête + 1 eager
   return $vehicles->map(fn ($v) => [...$v, 'cumul' => $cumuls[$v->id] ?? 0, 'taxDue' => $this->calculator->calculate($v, $cumuls[$v->id] ?? 0)]);
   ```
2. Activer `DB::enableQueryLog` en test, asserter ≤ 3 requêtes pour 100 véhicules.

**Estimation** : 0.75 j/h
**Dépendances** : 2.2, 2.3

---

### [6.2] Pagination des listes

**Fichiers concernés** :
- `app/Http/Controllers/User/Vehicle/VehicleController.php`
- `app/Http/Controllers/User/Company/CompanyController.php`
- `resources/js/pages/User/Vehicles/Index.vue`
- `resources/js/pages/User/Companies/Index.vue`

**Problème** : aucune pagination (rapport § 7.3).

**Solution** : 25 éléments par page, liens page via query param.

**Méthode** :
1. Repository retourne `LengthAwarePaginator`.
2. Data collectée via `PaginatedDataCollection` de Spatie Data.
3. Composant frontend : créer `resources/js/Components/Ui/Pagination.vue`.

**Estimation** : 0.5 j/h
**Dépendances** : 2.2

---

### [6.3] Index manquants

**Fichiers concernés** :
- nouvelle migration `2026_XX_XX_add_missing_indexes.php`

**Problème** : à auditer via `EXPLAIN` sur les requêtes critiques.

**Solution** : index composés là où `EXPLAIN` montre un `filesort` ou `Using temporary`.

**Méthode** :
1. Lancer les requêtes du `VehicleListProjectionService` en MySQL avec `EXPLAIN`.
2. Ajouter au minimum :
   - `assignments(company_id, date, deleted_at)` — pour liste par entreprise.
   - `vehicle_fiscal_characteristics(vehicle_id, effective_from DESC, effective_to)` — pour current().
3. Rollback si régression.

**Estimation** : 0.5 j/h
**Dépendances** : 6.1

---

### [6.4] Performance frontend ciblée

**Fichiers concernés** :
- `resources/js/Components/Features/Planning/Heatmap.vue:32` (`ref` → `shallowRef`)
- `resources/js/pages/User/FiscalRules/Index.vue:9-13` (`import()` dynamique de `@/data/fiscalRulesContent`)
- `resources/js/pages/User/Assignments/Index.vue:158-161` (`watchDebounced`)

**Problème** : rapport § 7.4.

**Solution** : 3 optimisations ciblées sans cérémonie.

**Méthode** :
1. `Heatmap.vue` : `const vehicles = shallowRef<VehicleHeatmapRowData[]>(props.vehicles)`.
2. `FiscalRules/Index.vue` : remplacer l'import statique par `const fiscalRulesContent = await import('@/data/fiscalRulesContent')` dans un `<Suspense>` ou via `defineAsyncComponent`.
3. `Assignments/Index.vue` : remplacer `setTimeout` debounce par `watchDebounced([...], cb, { debounce: 200 })` de `@vueuse/core`.

**Estimation** : 0.25 j/h
**Dépendances** : 11.x (après découpage)

---

## Phase 7 — Types TS frontend

**Objectif** : consommer les types générés côté front. Zéro type inline dans les pages. Ré-export propre.

**Pourquoi** : conséquence directe de la phase 1. Sans consommation, la chaîne est cassée.

### [7.1] Consommer les types générés

**Fichiers concernés** :
- `resources/js/types/generated/generated.d.ts` (généré)
- `resources/js/types/index.ts` (augmentation + ré-export)
- Toutes les pages/composants qui ont un type inline (liste complète dans rapport § 3.2 + A)

**Problème** : 12+ types redéclarés inline (rapport § 3.2 + A).

**Solution** : chaque composant importe depuis `@/types` et plus jamais redéclare.

**Méthode** :
1. Réorganiser `resources/js/types/index.ts` :
   ```ts
   export * from './generated/generated';
   export type * from './auth';
   export type * from './ui';
   export type * from './navigation';
   ```
2. Créer `types/navigation.ts` (DashboardQuickLinkData déjà dans generated, mais extras UI ici).
3. Sweeper chaque fichier listé § 3.2 — supprimer le type local, ajouter `import type { VehicleListItemData } from '@/types'`.
4. Corriger les shapes divergentes (`FiscalPreview` incomplet dans Assignments/Index.vue, `userType: string` devient `VehicleUserType`).
5. `npm run build` doit passer sans erreur TS.

**Estimation** : 1 j/h
**Dépendances** : 1.4

---

### [7.2] Resserrer `global.d.ts` et les `any` résiduels

**Fichiers concernés** :
- `resources/js/types/global.d.ts:8`
- `resources/js/lib/http.ts`

**Problème** : `[key: string]: string | boolean | undefined;` trop large (rapport § 3.8). Ce fichier disparaîtra en phase 10 avec `useHttp`.

**Solution** : fix ponctuel avant suppression.

**Méthode** :
1. Typer plus finement le `[key: string]` → `ImportMetaEnv` typed ou `Record<string, string | boolean>`.
2. Enlever les `Number(value)` / `String(row.currentStatus)` dans les templates (symptôme d'un `DataTableColumn<R>` sous-typé).

**Estimation** : 0.25 j/h
**Dépendances** : 1.4

---

### [7.3] Enums miroir côté front (si non générés)

**Fichiers concernés** :
- `resources/js/types/enums.ts` (ou subfolder `enums/`)

**Problème** : les enums PHP ne sont pas systématiquement exportés comme unions TS.

**Solution** : vérifier que `EnumTransformer` produit bien `type VehicleUserType = 'VP' | 'VU'`. Si non, les écrire à la main en miroir, tests de parité PHP↔TS.

**Méthode** :
1. Vérifier dans `generated.d.ts` la présence des unions d'enums.
2. Si manquant, ajouter les enums à `auto_discover_types` ou écrire manuellement dans `types/enums.ts` avec commentaire `// Miroir de app/Enums/Vehicle/VehicleUserType.php` + test PHP qui asserte la parité.

**Estimation** : 0.25 j/h
**Dépendances** : 1.1

---

## Phase 8 — Composables Vue

**Objectif** : extraire la logique réactive dupliquée dans 8 composables typés, chacun avec type de retour explicite et testable.

**Pourquoi** : règle `composables-services-utils.md` § 1. Aujourd'hui 1 composable (useToasts) pour toute l'app, le reste est dupliqué inline.

### [8.1] `useFiscalPreview`

**Fichiers concernés** :
- `resources/js/Composables/User/useFiscalPreview.ts` (nouveau)
- remplace le code dupliqué dans `WeekDrawer.vue:89-199` et `Assignments/Index.vue:58-187`.

**Problème** : duplication totale (rapport § B.1).

**Solution** : composable qui prend refs d'input et retourne `{ preview, loading, error }` (API déjà détaillée dans le rapport § B.1).

**Méthode** :
1. Utiliser `useDebounceFn` de `@vueuse/core` (déjà installé).
2. Utiliser `useHttp` d'Inertia v3 (phase 10) — pour l'instant `postJson`, migration en phase 10.
3. Test Vitest : `tests/js/Composables/useFiscalPreview.spec.ts` (phase 14).

**Estimation** : 0.5 j/h
**Dépendances** : 0.4

---

### [8.2] `useMultiDateSelection`

**Fichiers concernés** :
- `resources/js/Composables/User/useMultiDateSelection.ts` (nouveau)
- Simplifie `MultiDatePicker.vue:36-165` et `WeekDrawer.vue:137-146`.

**Problème** : logique set + shift/ctrl/simple click dupliquée (rapport § B.2).

**Solution** : composable qui expose `{ selected: Ref<Set<string>>, disabled: ..., onClick(cell, event), clear() }`.

**Méthode** :
1. Signature :
   ```ts
   export type UseMultiDateSelectionReturn = {
     selected: Ref<ReadonlySet<string>>;
     toggle: (iso: string, event?: MouseEvent, options?: { anchor?: Ref<string | null> }) => void;
     selectRange: (startIso: string, endIso: string) => void;
     clear: () => void;
     asSortedArray: ComputedRef<string[]>;
   };
   ```
2. Tester les branches shift/ctrl/simple avec un event simulé.

**Estimation** : 0.5 j/h
**Dépendances** : 0.4

---

### [8.3] `usePlanningDrawer`

**Fichiers concernés** :
- `resources/js/Composables/User/usePlanningDrawer.ts` (nouveau)
- Remplace l'état local dans `Planning/Index.vue:58-76`.

**Problème** : état + fetch + open/close dupliqués potentiellement.

**Solution** : `{ open, close, isOpen, weekData, loading }`.

**Méthode** : pattern identique à `useFiscalPreview`, branche sur Wayfinder `planning.week.get()`.

**Estimation** : 0.25 j/h
**Dépendances** : 8.1

---

### [8.4] `useVehicleBusyDates`

**Fichiers concernés** :
- `resources/js/Composables/User/useVehicleBusyDates.ts` (nouveau)
- Supprime les 2 `watch` de `Assignments/Index.vue:105-147`.

**Problème** : 2 watchers font 2 fetchs là où 1 suffit, sans abort signal (rapport § B.4).

**Solution** : un seul fetch par changement de `vehicleId`, `pairDates` calculé localement depuis `companyId`.

**Méthode** :
1. `useVehicleBusyDates(vehicleId: Ref<number | null>): { vehicleBusyDates, pairDatesFor, loading }`.
2. `pairDatesFor(companyId: number): ComputedRef<string[]>`.
3. `AbortController` pour éviter les requêtes obsolètes lors de changements rapides.

**Estimation** : 0.5 j/h
**Dépendances** : 8.1

---

### [8.5] `useAssignmentForm`

**Fichiers concernés** :
- `resources/js/Composables/User/useAssignmentForm.ts` (nouveau)

**Problème** : duplication submit logic entre WeekDrawer et Assignments/Index (rapport § B.5).

**Solution** : composable qui wrap `useForm` Inertia avec les champs attendus + validation synchrone pré-submit.

**Méthode** :
1. Utiliser `<Form>` ou `useForm` d'Inertia v3.
2. Retourne `{ form, submit, resetAfterSuccess }` typé sur `BulkCreateAssignmentsData`.

**Estimation** : 0.5 j/h
**Dépendances** : 8.1

---

### [8.6] `useFiscalYear`

**Fichiers concernés** :
- `resources/js/Composables/User/useFiscalYear.ts` (nouveau)
- Simplifie `UserLayout.vue:21-32`, `Assignments/Index.vue:29-30`.

**Problème** : le pattern `ref(currentYear.value) + watch()` est un code smell (rapport § B.6).

**Solution** : composable lecture/écriture de l'année via query param + shared props, + `daysInYear` exposé.

**Méthode** :
1. Pattern exactement celui de `composables-services-utils.md:49-80`.
2. Exposer `daysInYear: ComputedRef<365 | 366>` depuis `utils/date.ts`.

**Estimation** : 0.25 j/h
**Dépendances** : 0.3, 9.2

---

### [8.7] `useCurrentUser`

**Fichiers concernés** :
- `resources/js/Composables/User/useCurrentUser.ts` (nouveau)
- Simplifie `TopBar.vue:22-37`, `Welcome.vue:7-8`.

**Problème** : calculs `fullName`/`initials` répétés (rapport § B.7).

**Solution** : `{ user, isAuthenticated, fullName, initials }`.

**Méthode** : lecture `usePage().props.auth.user`, computed + test simple.

**Estimation** : 0.25 j/h
**Dépendances** : aucune

---

### [8.8] `useHeatmapDensity` (util pur → dans Utils)

**Fichiers concernés** :
- `resources/js/Utils/fiscal/heatmapDensity.ts` (nouveau)

**Problème** : `densityClass()` + `textContrastClass()` dans `Heatmap.vue:55-68` (rapport § B.8).

**Solution** : fonction pure (pas d'état → **pas un composable**, un util).

**Méthode** :
1. Fichier avec `densityClassFor(days: number): string` et `textContrastFor(days: number): string`.
2. Utiliser une `const DENSITY_THRESHOLDS` + `find`.
3. Test Vitest avec DataProvider 0..8.

**Estimation** : 0.25 j/h
**Dépendances** : 9.1

---

## Phase 9 — Utils, stores, constants

**Objectif** : plus aucune fonction utilitaire dupliquée, Pinia en place, constantes centralisées.

**Pourquoi** : `composables-services-utils.md` § Utils, `pinia-stores.md`.

### [9.1] Utils — `format.ts`, `date.ts`, `vehicle.ts`, `company.ts`

**Fichiers concernés** : à créer
- `resources/js/Utils/format/formatEuro.ts`
- `resources/js/Utils/format/formatDate.ts`
- `resources/js/Utils/format/formatLicensePlate.ts`
- `resources/js/Utils/format/formatSiren.ts`
- `resources/js/Utils/format/formatInteger.ts`
- `resources/js/Utils/date/daysInYear.ts` (fait 0.3)
- `resources/js/Utils/date/isoDate.ts`
- `resources/js/Utils/date/buildDateRange.ts`
- `resources/js/Utils/date/weekNumber.ts`
- `resources/js/Utils/date/weekLabels.ts` (jours + mois FR)
- `resources/js/Utils/vehicle/statusDotClass.ts`
- `resources/js/Utils/vehicle/userTypeBadgeClass.ts`
- `resources/js/Utils/company/isActiveClass.ts`
- `resources/js/Utils/validation/frenchLicensePlate.ts` (miroir backend)
- `resources/js/Utils/validation/siren.ts` (Luhn FR)
- `resources/js/Utils/validation/siret.ts`

**Problème** : `formatEur` dupliqué 6 fois, `formatDate` 1 fois avec pattern reproductible, `statusDotClass` inline, etc. (rapport § C).

**Solution** : 1 fichier par util, 1 fonction pure par fichier.

**Méthode** :
1. `formatEuro(amount: number, options?: { fractionDigits?: 0 | 2 }): string` avec `.replace(/[  ]/g, ' ')` commenté.
2. `formatDate(iso: string, format?: 'short' | 'long' | 'weekday'): string` basé sur `date-fns` + locale `fr`.
3. `buildDateRange(startIso: string, endIso: string): string[]` (inclusive).
4. `isoDate(d: Date): string` — locale-safe.
5. Tests Vitest pour chaque (minimum 3 cases).
6. Sweeper les 6 `formatEur` + 1 `formatDateFr` + 2 `statusDotClass` pour importer.

**Estimation** : 0.75 j/h
**Dépendances** : aucune

---

### [9.2] Pinia — install + stores

**Fichiers concernés** : nouveaux
- `resources/js/app.ts` (branchement)
- `resources/js/Stores/User/fiscalYearStore.ts`
- `resources/js/Stores/User/planningSelectionStore.ts`
- `resources/js/Stores/User/companiesCacheStore.ts`
- `resources/js/Stores/User/vehiclesCacheStore.ts`

**Problème** : Pinia absent (rapport § D).

**Solution** : 4 stores justifiés, persistés via `@pinia/plugin-persistedstate` pour la sélection multi-dates.

**Méthode** :
1. `npm install pinia @pinia/plugin-persistedstate`.
2. Dans `app.ts` :
   ```ts
   import { createPinia } from 'pinia';
   import piniaPersist from 'pinia-plugin-persistedstate';
   const pinia = createPinia();
   pinia.use(piniaPersist);
   app.use(pinia);
   ```
3. `fiscalYearStore` — année sélectionnée + `availableYears`, lecture depuis shared props + setter via `router.get`.
4. `planningSelectionStore` — `selectedDates: Set<string>` persisté (session storage) + `anchorDate`.
5. `companiesCacheStore`, `vehiclesCacheStore` — stale-while-revalidate avec timestamp.
6. Migrer `UserLayout.vue`, `Assignments/Index.vue`, `WeekDrawer.vue` pour utiliser les stores au lieu de `usePage().props.fiscal.*` direct.

**Estimation** : 1.5 j/h
**Dépendances** : 8.6

---

### [9.3] Constants par domaine + zIndex

**Fichiers concernés** : nouveaux
- `resources/js/Constants/fiscal.ts` (`LCD_THRESHOLD_DAYS`, `DEBOUNCE_FISCAL_PREVIEW_MS`, `POLLUTANT_TARIFFS_2024`)
- `resources/js/Constants/calendar.ts` (`DAY_LABELS`, `MONTH_LABELS`, `WEEKS_PER_YEAR`)
- `resources/js/Constants/heatmap.ts` (`CELL_WIDTH`, `DENSITY_THRESHOLDS`)
- `resources/js/Constants/zIndex.ts` (palette documentée)

**Problème** : magic numbers + magic strings éparpillés (rapport § F.1, F.9, F.12).

**Solution** : une source de vérité par domaine. Z-index palette centralisée, référencée via classes Tailwind arbitraires.

**Méthode** :
1. `zIndex.ts` comme écrit dans l'audit § F.9.
2. Recoder les classes `z-40`, `z-50`, `z-60` en `z-[var(--z-drawer)]` + injecter variables CSS dans `app.css`.
3. Collision `Modal.vue` (z-50) vs `Drawer.vue` (z-50) : Modal passe à `--z-modal: 60`, Drawer reste à 50.

**Estimation** : 0.25 j/h
**Dépendances** : aucune

---

## Phase 10 — Wayfinder partout + HTTP Inertia v3

**Objectif** : zéro URL hardcodée, `lib/http.ts` supprimé, toutes les requêtes JSON passent par `useHttp` d'Inertia v3.

**Pourquoi** : règle explicite `inertia-navigation.md` § Wayfinder. Aujourd'hui 21 URL en dur.

> **ADR à rédiger en parallèle** : `project-management/decisions/0013-navigation-wayfinder-vs-http-inertia-v3.md` — trancher le pattern unique pour les endpoints JSON (Wayfinder URL + `useHttp`).

### [10.1] Sweep Wayfinder sur les `<Link>` et `router.visit`

**Fichiers concernés** : 21 URL listées dans rapport § E.

**Problème** : 21 URL hardcodées `<Link href="/app/...">` et `router.visit('/app/...')`.

**Solution** : import Wayfinder systématique.

**Méthode** :
1. Activer `@laravel/vite-plugin-wayfinder` (déjà dans `package.json`).
2. Vérifier `.gitignore` : `actions/`, `routes/`, `wayfinder/` gitignored — confirmer.
3. Sweeper chaque URL :
   - `<Link href="/app/dashboard">` → `<Link :href="DashboardController().url">`.
   - `form.post('/app/vehicles')` → `form.submit(VehicleController.store())`.
   - `router.visit('/app/planning')` → `router.visit(planning.index().url)`.
4. Tests : `npm run build` doit passer, tests Vitest sur les composants affectés.

**Estimation** : 1 j/h
**Dépendances** : 2.5 (controllers stables), 11.x (après découpage)

---

### [10.2] Migration `lib/http.ts` → `useHttp` d'Inertia v3

**Fichiers concernés** :
- `resources/js/lib/http.ts` (à supprimer)
- `resources/js/Composables/User/useFiscalPreview.ts`
- `resources/js/Composables/User/usePlanningDrawer.ts`
- `resources/js/Composables/User/useVehicleBusyDates.ts`

**Problème** : `lib/http.ts` fait fetch natif, gère XSRF à la main, logique fragile (rapport § 3.1).

**Solution** : `useHttp` Inertia v3 gère XSRF + cancel natif + types, zero-dep maison.

**Méthode** :
1. Dans les 3 composables de phase 8, remplacer `postJson(...)` / `getJson(...)` par :
   ```ts
   const http = useHttp();
   const { data } = await http.post(PlanningController.previewTaxes().url, payload);
   ```
2. Supprimer `lib/http.ts` après migration.
3. Tests Vitest : mock `useHttp` via `@inertiajs/vue3`.

**Estimation** : 0.75 j/h
**Dépendances** : 10.1, 8.1-8.5

---

### [10.3] Stratégie fichiers Wayfinder générés

**Fichiers concernés** :
- `.gitignore`
- `composer.json` (post-install hook)
- `package.json` (scripts)

**Problème** : potentiellement confus si un dépôt cloné sans fichiers générés échoue à Vite.

**Solution** : génération automatique sur `composer install` + `npm install`.

**Méthode** :
1. Dans `composer.json::scripts::post-install-cmd` : `@php artisan wayfinder:generate`.
2. Dans `package.json::scripts::postinstall` : `php artisan wayfinder:generate && php artisan typescript:transform`.
3. Documenter dans README.md.
4. ADR-0013 formalise le choix de ne pas committer les fichiers générés.

**Estimation** : 0.25 j/h
**Dépendances** : 1.1

---

## Phase 11 — Refactor composants trop gros

**Objectif** : aucun composant ne dépasse 300 lignes, responsabilité unique, partials dans `Pages/.../Partials/`.

**Pourquoi** : règle dure `vue-composants.md` § Principes + `structure-fichiers.md` § Partials.

### [11.1] Découper `WeekDrawer.vue` (503L → 4 composants)

**Fichiers concernés** :
- `resources/js/Components/Features/Planning/WeekDrawer.vue` (503 L)
- Nouveaux :
  - `resources/js/Components/Features/Planning/WeekDrawer/WeekDrawer.vue` (<250 L, conteneur)
  - `resources/js/Components/Features/Planning/WeekDrawer/WeekDaysGrid.vue`
  - `resources/js/Components/Features/Planning/WeekDrawer/WeekCompaniesList.vue`
  - `resources/js/Components/Features/Planning/WeekDrawer/AssignmentInlineForm.vue`
  - `resources/js/Components/Features/Planning/WeekDrawer/FiscalPreviewCard.vue`

**Problème** : 503 L, 7 responsabilités mélangées (rapport § 3.5).

**Solution** : 1 conteneur + 4 partials, chacun < 200 L.

**Méthode** :
1. Créer le sous-dossier et les 4 composants.
2. Déplacer :
   - Template `<div class="grid grid-cols-7">` + logique toggleSlot → `WeekDaysGrid.vue`.
   - Liste entreprises affectées semaine → `WeekCompaniesList.vue`.
   - Formulaire attribution + submit → `AssignmentInlineForm.vue` (utilise `useAssignmentForm`).
   - Preview fiscal (lignes 400-490) → `FiscalPreviewCard.vue` (reçoit `FiscalPreviewData`).
3. Vérifier que les tests Vitest (phase 14) passent sur chaque.

**Estimation** : 1.5 j/h
**Dépendances** : 8.1, 8.2, 8.5

---

### [11.2] Découper `Assignments/Index.vue` (419L)

**Fichiers concernés** :
- `resources/js/pages/User/Assignments/Index.vue` → `resources/js/Pages/User/Assignments/Index/Index.vue`
- Nouveaux partials : `Partials/AssignmentsHeader.vue`, `AssignmentForm.vue` (réutilise celui de 11.1), `FiscalPreviewCard.vue` (réutilisé).

**Problème** : 419 L (rapport § 3.5).

**Solution** : page < 200 L + réutilisation des partials créés en 11.1.

**Méthode** :
1. Renommer `pages/User/Assignments/Index.vue` en `Pages/User/Assignments/Index/Index.vue` (convention `structure-fichiers.md:174`).
2. Extraire le formulaire en réutilisant `AssignmentInlineForm` de 11.1 si possible (partial → candidate à promotion vers `Components/Domain/Assignment/AssignmentForm.vue`).

**Estimation** : 0.75 j/h
**Dépendances** : 11.1

---

### [11.3] Découper `FiscalRules/Index.vue` (424L)

**Fichiers concernés** :
- `resources/js/pages/User/FiscalRules/Index.vue` → `Pages/User/FiscalRules/Index/Index.vue`
- Nouveaux partials : `Partials/RuleSectionCard.vue`, `BracketTable.vue`, `FlatBracketList.vue`, `LegalReferenceList.vue`.

**Problème** : 424 L, très dense (rapport § 3.5).

**Solution** : partials par section, chargement asynchrone de `fiscalRulesContent`.

**Méthode** :
1. Extraire chaque section de la page en partial.
2. Le `fiscalRulesContent.ts` (25 Ko) passe en `const data = await import('@/data/fiscalRulesContent')`.

**Estimation** : 0.75 j/h
**Dépendances** : 6.4

---

### [11.4] Normaliser le casing des dossiers

**Fichiers concernés** : tout `resources/js/pages/` → `resources/js/Pages/`, `resources/js/composables/` → `resources/js/Composables/`, etc.

**Problème** : incohérence (rapport § 2.8).

**Solution** : PascalCase partout d'après `structure-fichiers.md`.

**Méthode** :
1. Renommer en 2 temps sur Windows (cf. 0.4).
2. Mettre à jour `vite.config.ts` resolve alias si nécessaire.
3. Mettre à jour tous les imports.
4. Vérifier `npm run build`.

**Estimation** : 0.5 j/h
**Dépendances** : aucune, mais doit précéder toutes les autres tâches front pour éviter un double sweep

---

### [11.5] README par dossier Features

**Fichiers concernés** : nouveaux
- `resources/js/Components/Features/Planning/README.md`
- `resources/js/Components/Features/Company/README.md` (si existant)
- `resources/js/Components/Features/Vehicle/README.md` (si existant)

**Problème** : aucune doc sur l'interaction Heatmap ↔ WeekDrawer (rapport § F.16).

**Solution** : 30 L par README documentant la circulation des events.

**Méthode** :
1. Markdown avec diagramme ASCII des events.
2. Exemples d'usage des composants.

**Estimation** : 0.25 j/h
**Dépendances** : 11.1

---

## Phase 12 — Accessibilité & UX

**Objectif** : respecter WCAG 2.1 AA sur les composants critiques, UX cohérente (loading states, focus management, Escape, toasts d'erreur).

**Pourquoi** : règle `vue-composants.md` principe 6 (accessibilité obligatoire) + `performance-ui.md` § skeletons.

### [12.1] Focus trap + Escape dans les drawers

**Fichiers concernés** :
- `resources/js/Components/Features/Planning/WeekDrawer/WeekDrawer.vue`
- `resources/js/Components/Ui/Drawer/Drawer.vue`
- `resources/js/Components/Ui/Modal/Modal.vue`

**Problème** : pas de focus trap, Tab sort du drawer, pas d'Escape (rapport § F.6).

**Solution** : `focus-trap-vue` ou helper maison, écoute `keydown.esc`, restoration focus à la fermeture.

**Méthode** :
1. `npm install focus-trap-vue`.
2. Wrapper `Drawer.vue` et `Modal.vue` :
   ```vue
   <FocusTrap :active="isOpen">
     <div role="dialog" aria-modal="true" @keydown.esc="close">...</div>
   </FocusTrap>
   ```
3. Restoration focus : stocker `previousFocus = document.activeElement` à l'ouverture, appeler `previousFocus?.focus()` à la fermeture.

**Estimation** : 0.5 j/h
**Dépendances** : 11.1

---

### [12.2] Rôles ARIA grid + aria-label dates

**Fichiers concernés** :
- `resources/js/Components/Features/Planning/Heatmap.vue:189-205`
- `resources/js/Components/Features/Planning/MultiDatePicker.vue:213-237`

**Problème** : 520 boutons sans structure grid (rapport § F.6).

**Solution** : `role="grid"` + `role="row"` + `role="gridcell"` + `aria-rowindex` + `aria-colindex`. Dates au picker : `aria-label="Lundi 15 janvier 2024"` via `formatDate(iso, 'long')`.

**Méthode** :
1. Refondre le template Heatmap en tableau sémantique ou ARIA équivalent.
2. Dans `MultiDatePicker`, remplacer `title="{{ cell.day }}"` par `aria-label="{{ formatDate(cell.iso, 'long') }}"`.

**Estimation** : 0.5 j/h
**Dépendances** : 9.1

---

### [12.3] Skeletons + loading states

**Fichiers concernés** : nouveaux
- `resources/js/Components/Ui/Skeleton/VehicleRowSkeleton.vue`
- `resources/js/Components/Ui/Skeleton/HeatmapSkeleton.vue`
- `resources/js/Components/Ui/Skeleton/WeekDrawerSkeleton.vue`
- `resources/js/Components/Ui/Skeleton/KpiCardSkeleton.vue`

**Problème** : pas de feedback chargement sur Dashboard, Vehicles/Index, etc. (rapport § F.4).

**Solution** : skeletons + deferred props (Inertia v3).

**Méthode** :
1. Créer les 4 composants skeletons (animation pulse Tailwind).
2. Côté backend : utiliser `Inertia::defer(fn () => ...)` pour les props lourdes (ex: cumuls par véhicule).
3. Côté Vue : `<Suspense>` + skeleton en fallback.

**Estimation** : 0.5 j/h
**Dépendances** : 11.x

---

### [12.4] Respect strict des design tokens

**Fichiers concernés** :
- `resources/js/Components/Features/Planning/WeekDrawer/**/*.vue` (après 11.1)
- `resources/js/Components/Features/Planning/Heatmap.vue`
- `resources/js/pages/User/Assignments/Index/Index.vue`

**Problème** : 10 emplacements avec `bg-blue-600`, `text-blue-700` au lieu de tokens sémantiques (rapport § F.8).

**Solution** : mapper vers les tokens du Design System Floty (`project-management/design-system/`).

**Méthode** :
1. Relire le skill `.claude/skills/floty-design-system/SKILL.md` pour les tokens exacts (`bg-action-primary`, `text-accent-strong`, etc.).
2. Grep puis remplacer ciblé.

**Estimation** : 0.25 j/h
**Dépendances** : 11.x

---

### [12.5] Toasts d'erreur sur tous les catches

**Fichiers concernés** : couvert en 4.3.

**Problème** : rappel : les 4 `catch { /* silent */ }` doivent être éliminés.

**Estimation** : 0 (fait en 4.3)
**Dépendances** : 4.3

---

## Phase 13 — i18n : décision + préparation

**Objectif** : trancher la stratégie i18n et documenter.

**Pourquoi** : rapport § F.2 signale la présence exclusive de français en dur. Décision à prendre avant V1.

> **ADR à rédiger** : `project-management/decisions/0014-strategie-i18n-v1-fr-only.md`.

### [13.1] ADR stratégie i18n V1

**Fichiers concernés** :
- `project-management/decisions/0014-strategie-i18n-v1-fr-only.md` (nouveau)

**Problème** : ambiguïté décision.

**Solution** : ADR explicite, décision probablement « FR-only V1, pas de vue-i18n » compte tenu du CDC (Floty 100 % FR).

**Méthode** :
1. Contexte : CDC § Langue + CLAUDE.md impose FR.
2. Décision : FR-only V1, pas de lib i18n.
3. Conséquence : les libellés restent en dur dans les templates. Les messages d'erreur applicatifs en FR via `BaseAppException::userMessage`.
4. Conditions de réouverture : besoin client multi-langue en V2 (anglais, espagnol, …).

**Estimation** : 0.25 j/h
**Dépendances** : aucune

---

### [13.2] Extraction des libellés dupliqués

**Fichiers concernés** :
- `resources/js/Constants/labels.ts` (nouveau, minimaliste)

**Problème** : « Nouveau véhicule » répété 3x, etc.

**Solution** : même sans lib i18n, un fichier `labels.ts` centralise les libellés répétés.

**Méthode** :
1. `export const LABELS = { newVehicle: 'Nouveau véhicule', ... } as const;`.
2. Grep les répétitions, remplacer.

**Estimation** : 0.5 j/h
**Dépendances** : 13.1

---

## Phase 14 — Tests

**Objectif** : couverture fonctionnelle solide des couches refactorisées + setup Vitest + tests Vue représentatifs.

**Pourquoi** : sans tests, la remise aux normes des phases précédentes n'est pas vérifiable. Aujourd'hui 0 test Feature controller, 1 test Unit fiscal (partiel).

### [14.1] Tests Feature controllers (backend)

**Fichiers concernés** : nouveaux
- `tests/Feature/Http/Controllers/User/Auth/LoginControllerTest.php` (déjà à couvrir)
- `tests/Feature/Http/Controllers/User/Auth/LogoutControllerTest.php`
- `tests/Feature/Http/Controllers/User/Auth/ForcePasswordChangeControllerTest.php`
- `tests/Feature/Http/Controllers/User/Dashboard/DashboardControllerTest.php`
- `tests/Feature/Http/Controllers/User/Vehicle/VehicleControllerIndexTest.php` (+ Create, Store)
- `tests/Feature/Http/Controllers/User/Company/CompanyControllerIndexTest.php` (+ Create, Store)
- `tests/Feature/Http/Controllers/User/Assignment/AssignmentControllerIndexTest.php` (+ VehicleDates)
- `tests/Feature/Http/Controllers/User/Planning/PlanningControllerIndexTest.php` (+ Week, PreviewTaxes, StoreBulk)
- `tests/Feature/Http/Controllers/User/FiscalRule/FiscalRuleControllerIndexTest.php`

**Problème** : zéro test Feature (rapport § 4.1).

**Solution** : 1 test par endpoint, happy path + 1-2 edge cases par endpoint.

**Méthode** :
1. Patron :
   ```php
   final class VehicleControllerIndexTest extends TestCase
   {
       use RefreshDatabase;

       public function testItRendersVehicleListForAuthUser(): void
       {
           $user = User::factory()->create();
           Vehicle::factory(3)->create();

           $response = $this->actingAs($user)->get(route('user.vehicles.index'));

           $response->assertOk();
           $response->assertInertia(fn (Assert $page) => $page
               ->component('User/Vehicles/Index')
               ->has('vehicles', 3)
               ->has('fiscal.currentYear')
           );
       }
   }
   ```
2. Utiliser `->assertInertia()` de `inertiajs/inertia-laravel`.
3. 2-3 tests par endpoint, au minimum :
   - Happy path.
   - 401 si non-auth.
   - 422 si validation échoue (mutations).

**Estimation** : 3 j/h
**Dépendances** : 2.5, 3.1

---

### [14.2] Tests Unit services / actions / repositories

**Fichiers concernés** : nouveaux
- `tests/Unit/Services/User/Dashboard/DashboardSummaryServiceTest.php`
- `tests/Unit/Services/User/Planning/PlanningHeatmapServiceTest.php`
- `tests/Unit/Services/User/Planning/PlanningPreviewServiceTest.php`
- `tests/Unit/Services/User/Assignment/AssignmentBulkCreateServiceTest.php`
- `tests/Unit/Services/User/Vehicle/VehicleListProjectionServiceTest.php`
- `tests/Unit/Actions/User/Vehicle/CreateVehicleActionTest.php`
- `tests/Unit/Actions/User/Assignment/BulkCreateAssignmentsActionTest.php`
- `tests/Unit/Repositories/User/Assignment/AssignmentReadRepositoryTest.php`
- `tests/Unit/Services/Shared/Fiscal/FiscalCalculatorTest.php` (étendu de 0.5 + 5.7)

**Problème** : zéro test Unit sur la couche métier (rapport § 4.2).

**Solution** : 1 test par classe publique, mocks via interfaces.

**Méthode** :
1. Mocks des repositories via interfaces (phase 2.1).
2. Cas happy path + cas d'erreur (exception levée).
3. Pour les Repositories : `RefreshDatabase` + factories.

**Estimation** : 2 j/h
**Dépendances** : 2.2, 2.3, 2.4, 5.7

---

### [14.3] Setup Vitest + Testing Library

**Fichiers concernés** :
- `vitest.config.ts` (nouveau)
- `package.json` (ajout scripts + deps)
- `resources/js/test-setup.ts`

**Problème** : aucun test frontend (rapport § 4.3, F.17).

**Solution** : Vitest + Testing Library configurés comme dans `tests-frontend.md:44-80`.

**Méthode** :
1. `npm install -D vitest@^4.1 @vue/test-utils@^2.4 @testing-library/vue @pinia/testing happy-dom`.
2. Config Vitest comme dans la règle (happy-dom env).
3. Setup file avec Pinia testing plugin + mocks Wayfinder (`vi.mock('@/actions/App/Http/Controllers/User/VehicleController')`).
4. Scripts `package.json` : `"test": "vitest"`, `"test:coverage": "vitest run --coverage"`.

**Estimation** : 0.75 j/h
**Dépendances** : 9.2

---

### [14.4] Tests Vitest représentatifs

**Fichiers concernés** : nouveaux
- `resources/js/Composables/User/useFiscalPreview.spec.ts`
- `resources/js/Composables/User/useMultiDateSelection.spec.ts`
- `resources/js/Composables/Shared/useToasts.spec.ts`
- `resources/js/Utils/format/formatEuro.spec.ts`
- `resources/js/Utils/date/daysInYear.spec.ts`
- `resources/js/Utils/date/buildDateRange.spec.ts`
- `resources/js/Components/Features/Planning/Heatmap.spec.ts`
- `resources/js/Components/Features/Planning/MultiDatePicker.spec.ts`

**Problème** : zéro test UI (rapport § F.17).

**Solution** : 8 tests représentatifs (pas 100%, juste les cœurs critiques).

**Méthode** :
1. Pattern Testing Library pour `MultiDatePicker` (interaction shift+clic).
2. Mocks fetch via `vi.fn`.
3. Snapshots à éviter (fragiles).

**Estimation** : 1 j/h
**Dépendances** : 8.x, 9.x, 14.3

---

### [14.5] CI GitHub Actions

**Fichiers concernés** :
- `.github/workflows/ci.yml` (nouveau ou à compléter)

**Problème** : pas de CI visible.

**Solution** : pipeline `composer audit` + `npm audit` + PHPUnit + Vitest + Pint + ESLint.

**Méthode** :
1. Jobs parallèles : `backend`, `frontend`, `audit`.
2. Cache Composer + node_modules.
3. Matrix PHP 8.5 + Node 24.

**Estimation** : 0.25 j/h
**Dépendances** : 14.1, 14.4

---

## Phase 15 — DX & ops

**Objectif** : projet prêt à livrer (README à jour, déploiement documenté, monitoring, APP_DEBUG=false, secrets).

**Pourquoi** : conformité conditions de livraison V1 + ADR-0011 § Secrets + maintenabilité.

### [15.1] README racine à jour

**Fichiers concernés** :
- `README.md`

**Problème** : probablement README scaffold Laravel.

**Solution** : README qui couvre stack + setup dev + commandes + lien vers `project-management/`.

**Méthode** :
1. Sections : Stack / Prérequis (PHP 8.5, Node 24, MySQL 8) / Setup local (`composer install`, `npm install`, `php artisan migrate --seed`, `composer run dev`) / Tests / Déploiement (lien vers `project-management/plan-implementation/docs/deploiement-hostinger.md`).

**Estimation** : 0.25 j/h
**Dépendances** : aucune

---

### [15.2] Documentation déploiement

**Fichiers concernés** :
- `project-management/plan-implementation/docs/deploiement-hostinger.md` (probable déjà présent)

**Problème** : à vérifier qu'il est à jour avec les scripts `wayfinder:generate + typescript:transform + vite build`.

**Solution** : checklist de déploiement testée.

**Méthode** :
1. Relire le doc existant.
2. Ajouter la checklist « before push prod » : `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `composer audit`, rotation APP_KEY warning.

**Estimation** : 0.25 j/h
**Dépendances** : 15.1

---

### [15.3] Monitoring / Sentry (optionnel V1)

**Fichiers concernés** :
- `composer.json` (ajout `sentry/sentry-laravel`)
- `config/sentry.php`
- `.env.example`

**Problème** : pas de monitoring erreurs prod.

**Solution** : Sentry branché. Si non-autonome (plan client), skip et documenter.

**Méthode** :
1. `composer require sentry/sentry-laravel`.
2. Config DSN via env.
3. Capture `BaseAppException` avec contexte utilisateur.

**Estimation** : 0.5 j/h (si go Sentry)
**Dépendances** : 4.2

---

### [15.4] Backups DB + log rotation

**Fichiers concernés** :
- `config/logging.php` (rotation déjà posée phase 4)
- Documentation dans `deploiement-hostinger.md`

**Problème** : pas de stratégie.

**Solution** : logs daily 90j (déjà fait), backups mysqldump cron Hostinger documenté, stockage 30j, rétention ADR-0010.

**Méthode** :
1. Documenter la cron Hostinger dans `deploiement-hostinger.md`.

**Estimation** : 0.25 j/h
**Dépendances** : 4.2

---

### [15.5] Audit final APP_DEBUG / trusted proxies / cookies

**Fichiers concernés** :
- `.env.production.example` (nouveau)
- `bootstrap/app.php` (middleware TrustProxies)
- `config/session.php` (déjà fait en 3.4)

**Problème** : aucune checklist.

**Solution** : check-list finale + `.env.production.example` qui sert de référence.

**Méthode** :
1. Créer `.env.production.example` avec valeurs `APP_DEBUG=false`, `APP_ENV=production`, `SESSION_SECURE_COOKIE=true`, `LOG_CHANNEL=stack`, etc.
2. `bootstrap/app.php` : activer `TrustProxies` avec `'*'` si derrière LB Hostinger.
3. Ajouter test Feature `ProductionConfigTest` skippé hors prod.

**Estimation** : 0.75 j/h
**Dépendances** : 3.4, 4.2

---

## Gantt synthétique — dépendances et ordonnancement

```
Phase 0  ══════════════════════════> (bloquants, 2.5 j)
  ↓
Phase 1  ═══════════════════════════════════════════════════> (DTOs + TS, 4.5 j)
          │
          ├─────────────────> Phase 2  ═══════════════════════════════════════> (archi hex, 7 j)
          │                      ↓
          │                      ├──> Phase 3 (authz, 2.5 j, parallélisable)
          │                      │
          │                      ├──> Phase 4 ══════════════> (erreurs, 2.5 j)
          │                      │
          │                      ├──> Phase 5 ═══════════════════════════> (fiscal, 6 j)
          │                      │
          │                      └──> Phase 6 ═══════════> (perf, 3 j)
          │
          └─────────────> Phase 7  ═════> (types TS, 1.5 j)
                            ↓
                            Phase 11.4 (casing, 0.5 j, prérequis autres phases FE)
                            ↓
                            ├─> Phase 8  ═════════════> (composables, 3 j)
                            │     ↓
                            ├─> Phase 9 ═════════════> (utils/stores/constants, 2.5 j)
                            │     ↓
                            ├─> Phase 10 ════════> (Wayfinder + useHttp, 2 j)
                            │
                            ├─> Phase 11 ═══════════════> (refactor composants, 3 j)
                            │     ↓
                            ├─> Phase 12 ══════════════> (a11y + UX, 2 j)
                            │
                            └─> Phase 13 ══> (i18n, 0.75 j, parallélisable)
                                  ↓
                                  Phase 14 ═══════════════════════════> (tests, 7 j)
                                  ↓
                                  Phase 15 ═══════════> (DX/ops, 2 j)
```

**Chemin critique (longueur maximale)** : 0 → 1 → 2 → 5 → 14 → 15 = 2.5 + 4.5 + 7 + 6 + 7 + 2 = **29 j/h**.

**Parallélisation possible** :
- Phase 3 (authz) et Phase 6 (perf) peuvent démarrer dès que Phase 2.5 est faite.
- Phase 7 (types TS conso) peut démarrer en parallèle de la phase 2, dès que 1.x est clos.
- Phases 8 à 12 peuvent être découpées entre 2 devs front si nécessaire (à condition de figer l'ordre des commits).

**Total cumulé** : ~48.25 j/h en séquentiel, ~35 j/h avec 2 devs backend/frontend en parallèle.

---

## ADRs à rédiger en parallèle

| ID | Titre | Quand | Effort |
|---|---|---|---|
| 0013 | Navigation Wayfinder + `useHttp` Inertia v3 — pattern unique | Avant phase 10 | 0.25 j |
| 0014 | Stratégie i18n V1 — FR-only, pas de vue-i18n | Pendant phase 13 | 0.25 j |
| 0015 | DTOs Spatie Data — frontière PHP↔TS, génération auto | Pendant phase 1 | 0.25 j |
| 0016 | Cache d'agrégats fiscaux — tags et invalidation via événements | Pendant phase 5 | 0.25 j |
| 0017 | Pipeline de règles fiscales versionnées par année | Pendant phase 5 | 0.25 j |

**Total ADRs** : ~1.25 j/h (inclus dans les phases correspondantes).

---

## Checklist de conformité V1 — 80 points

À cocher au fil de la remise aux normes. L'objectif V1 est **100 %**.

### Backend — architecture (15)

- [ ] Aucun `Model::query()` dans un controller.
- [ ] Aucun `DB::table()` en dehors des migrations et seeders.
- [ ] Chaque méthode controller ≤ 20 lignes.
- [ ] Une Action par intention utilisateur transactionnelle (Vehicle, Company, Assignment).
- [ ] Un Service par domaine métier (6+ services user, 3+ services shared).
- [ ] Un Repository par entité avec interface dans `app/Contracts/`.
- [ ] Les Repositories retournent des Collections ou des primitives, jamais des `Builder`.
- [ ] `RepositoryServiceProvider` binde toutes les interfaces.
- [ ] `AuthServiceProvider` binde toutes les Policies.
- [ ] Les Services sont `final readonly class`.
- [ ] Les Actions sont `final readonly class`.
- [ ] `app/Services/Shared/Fiscal/` au bon endroit (pas `App\Services\Fiscal`).
- [ ] `FiscalYearContext` existe et remplace le `2024` en dur dans 6 endroits.
- [ ] Scopes Eloquent : `Vehicle::active()`, `Company::active()`, `Assignment::forYear`, `VehicleFiscalCharacteristics::current`.
- [ ] `DemoSeeder` < 50 lignes, délègue à `DemoScenarioBuilder`.

### Backend — DTOs Spatie Data (7)

- [ ] `spatie/laravel-data` et `spatie/laravel-typescript-transformer` installés.
- [ ] Dossier `app/Data/User/{Vehicle,Company,...}/` créé.
- [ ] Chaque `Inertia::render` reçoit un Data, jamais un `array`.
- [ ] `FiscalBreakdown` hérite `Data` + `#[TypeScript]`.
- [ ] `php artisan typescript:transform` génère `resources/js/types/generated/generated.d.ts`.
- [ ] Mutations reçoivent un Data typé au lieu d'un FormRequest seul.
- [ ] `generated/` gitignored, regénéré via hook `postinstall`.

### Backend — sécurité (8)

- [ ] `app/Policies/` existe avec 4+ policies.
- [ ] Tous les `authorize()` appellent `$this->user()->can(...)`.
- [ ] Rate-limiter `user-api` (60/min) + `user-mutations` (20/min) branchés.
- [ ] Credentials démo : mot de passe aléatoire + `must_change_password=true`.
- [ ] Middleware `EnsurePasswordChanged` en place.
- [ ] `SessionCookie` en `secure+lax` en prod.
- [ ] Middleware `SecureHeaders` en prod avec HSTS + CSP.
- [ ] Aucun `admin@floty.test / password` en clair hors dev.

### Backend — gestion d'erreurs (6)

- [ ] 10+ exceptions typées héritant de `BaseAppException`.
- [ ] Aucun `InvalidArgumentException` ou `abort(400)` dans le code métier.
- [ ] Canaux de log `fiscal`, `vehicles`, `companies`, `assignments`, `auth`, `security` configurés.
- [ ] Handler global map `BaseAppException` → canal + réponse Inertia/HTTP appropriée.
- [ ] Rétention logs conforme ADR-0010 (90 j min).
- [ ] Aucune `Throwable` brute ne remonte au navigateur utilisateur.

### Backend — moteur fiscal (7)

- [ ] R-2024-017 (hybride conditionnelle) implémenté + testé.
- [ ] R-2024-013 (catégorisation polluants) calculé, pas lu depuis DB.
- [ ] Conversion NEDC→WLTP implémentée quand applicable.
- [ ] Arrondis half-up commercial au cent (R-2024-003).
- [ ] `FiscalRulePipeline` versionné par année via `config/fiscal.php`.
- [ ] Cache via `CacheTagsManager` sur les agrégats (Dashboard, Heatmap, VehicleList).
- [ ] 100+ scénarios testés dans `FiscalCalculatorTest.php`.

### Backend — performance (4)

- [ ] Aucun `Vehicle::find()` en boucle → `whereIn` + `keyBy`.
- [ ] Pagination sur Vehicles/Index, Companies/Index.
- [ ] Index composés ajoutés sur `assignments(company_id, date, deleted_at)` + `vehicle_fiscal_characteristics(vehicle_id, effective_from DESC, effective_to)`.
- [ ] Tests Feature asserts ≤ 3 requêtes SQL pour les listes principales.

### Frontend — types (5)

- [ ] Zéro type redéclaré inline dans les pages/composants Vue.
- [ ] Chaque import `import type { XxxData } from '@/types'`.
- [ ] Enums PHP disponibles comme unions TS (via EnumTransformer ou miroir manuel).
- [ ] `global.d.ts` resserré (pas de `[key: string]: any`).
- [ ] `tsconfig.json` conforme `typescript-dto.md:33-80` (strict, noUncheckedIndexedAccess, exactOptionalPropertyTypes).

### Frontend — composables (8)

- [ ] `Composables/Shared/useToasts.ts` (déplacé + type de retour explicite).
- [ ] `Composables/User/useFiscalPreview.ts`.
- [ ] `Composables/User/useMultiDateSelection.ts`.
- [ ] `Composables/User/usePlanningDrawer.ts`.
- [ ] `Composables/User/useVehicleBusyDates.ts`.
- [ ] `Composables/User/useAssignmentForm.ts`.
- [ ] `Composables/User/useFiscalYear.ts`.
- [ ] `Composables/User/useCurrentUser.ts`.

### Frontend — utils, stores, constants (6)

- [ ] `Utils/format/formatEuro.ts` centralisé, 6 duplications supprimées.
- [ ] `Utils/date/*` (`daysInYear`, `isoDate`, `buildDateRange`, `weekNumber`).
- [ ] `Utils/vehicle/*` + `Utils/company/*` pour les statusDotClass.
- [ ] Pinia installé + 4 stores (`fiscalYearStore`, `planningSelectionStore`, `companiesCacheStore`, `vehiclesCacheStore`).
- [ ] `Constants/fiscal.ts`, `Constants/calendar.ts`, `Constants/zIndex.ts`.
- [ ] Aucun magic number `366`, `200` (debounce), etc. en dur dans les templates.

### Frontend — Wayfinder & HTTP (5)

- [ ] Zéro URL `/app/...` hardcodée dans les `.vue`.
- [ ] Zéro `href="/..."` littéral, tous via Wayfinder `XxxController.yyy().url`.
- [ ] `lib/http.ts` supprimé, remplacé par `useHttp` Inertia v3.
- [ ] Hook `postinstall` génère les fichiers Wayfinder.
- [ ] ADR-0013 rédigé et accepté.

### Frontend — composants (5)

- [ ] Aucun composant > 300 lignes (`WeekDrawer`, `Assignments/Index`, `FiscalRules/Index` découpés).
- [ ] Structure `Pages/{Domaine}/{PageName}/Index.vue + Partials/` pour les pages complexes.
- [ ] Casing dossiers conforme `structure-fichiers.md` (`Pages/`, `Composables/`, `Utils/`, `Stores/`).
- [ ] README.md dans chaque dossier `Components/Features/*`.
- [ ] Ordre des imports canonique (externes → @/ → relatifs → types).

### Frontend — accessibilité & UX (6)

- [ ] Focus trap dans `Drawer` et `Modal` (focus-trap-vue).
- [ ] Handler `Escape` sur tous les overlays.
- [ ] `role="grid"` + `gridcell` sur la heatmap, aria-rowindex / aria-colindex.
- [ ] `aria-label` complet sur chaque jour du picker (« Lundi 15 janvier 2024 »).
- [ ] Skeletons sur Dashboard / Vehicles/Index / Companies/Index pendant reload.
- [ ] Toasts d'erreur branchés sur tous les catches (aucun `catch {}` silencieux).

### Tests (6)

- [ ] 20+ tests Feature couvrant les 6 controllers User + Auth.
- [ ] 10+ tests Unit sur les Services / Actions / Repositories.
- [ ] 100+ scénarios dans `FiscalCalculatorTest`.
- [ ] Vitest + Testing Library installés et configurés.
- [ ] 8 tests Vitest représentatifs (composables + utils + 2 composants).
- [ ] CI GitHub Actions passe : PHPUnit + Vitest + Pint + ESLint + composer audit + npm audit.

### DX & ops (5)

- [ ] README.md à jour avec stack + setup + commandes.
- [ ] Guide déploiement Hostinger à jour dans `project-management/plan-implementation/docs/`.
- [ ] ADR-0013, 0014, 0015, 0016, 0017 rédigés.
- [ ] `.env.production.example` en place.
- [ ] Sentry (optionnel) ou monitoring documenté.

### i18n (1)

- [ ] ADR-0014 rédigé : décision FR-only V1 explicite.

### Conformité ADRs existants (7)

- [ ] ADR-0001 (fiscalité comme donnée) : les règles fiscales sont calculables, pas lues crûment.
- [ ] ADR-0003 (PDF snapshots immuables) : V1 si implémenté.
- [ ] ADR-0005 (calcul jour par jour) : respecté dans le pipeline.
- [ ] ADR-0008 (stack V1) : PHP 8.5, Laravel 13, Inertia v3, Vue 3.5.
- [ ] ADR-0009 (versioning règles fiscales) : pipeline par année.
- [ ] ADR-0011 (sécurité V1) : HTTPS, bcrypt, throttle, CSRF, CSP, Policies.
- [ ] ADR-0012 (auth gestion comptes) : flow first-login, politique password 12+ chars.

---

## Récapitulatif — chiffres clés

- **Phases** : 16 (de 0 à 15).
- **Items** : 66.
- **Durée totale en séquentiel** : ~48.25 j/h.
- **Durée en parallèle 2 devs** : ~35 j/h (environ 7 semaines avec 1 senior + 1 mid).
- **ADRs à produire** : 5 (0013 à 0017).
- **Nouveaux fichiers PHP** : ~90 (Data, Actions, Services, Repositories, Interfaces, Exceptions, Policies).
- **Nouveaux fichiers TS/Vue** : ~35 (composables, utils, stores, partials, skeletons).
- **Nouveaux tests** : ~40.
- **Lignes de code supprimées** : ~2000 (duplications, types inline, `abort(400)`, `catch {}`, etc.).

**Après cette remise aux normes**, le projet pourra reprendre la roadmap V1 existante (phases 03 à 13 dans `project-management/plan-implementation/tasks/`) sur une base solide, testable, et conforme à 100 % des règles d'implémentation.
