# ADR-0013 — Architecture applicative (règles strictes V1)

**Statut** : Acceptée le 2026-04-27 (après chantier de durcissement 1.5).

**Contexte** : suite à la livraison du MVP démo + au chantier
1.5 (durcissement architecture vers V1), un audit a révélé
plusieurs règles flottantes ("ça dépend", seuil de lignes,
conventions modulables) qui produisaient de la gymnastique
mentale inutile. Cet ADR formalise les règles strictes adoptées
pour tout le reste du projet.

L'objectif est double :
- **Prédictibilité** : chaque dev sait toujours où chercher /
  où placer chaque type d'élément, sans réflexion conditionnelle.
- **Défendable senior** : aligné avec les patterns reconnus en
  Laravel (Spatie, Beyond CRUD) et Vue 3 (cohésion par feature
  / vertical slice), sans suivre les modes au détriment de la
  cohérence.

**ADR connexes** : 0006 (architecture moteur de règles),
0008 (stack technique), 0011 (sécurité), 0012 (auth).

---

## Principes directeurs

### P1 — Strictness > « ça dépend »
Les règles ci-dessous sont **strictes**, pas indicatives. Toute
exception doit être documentée explicitement dans le code via
un commentaire `// Exception RXX : <raison>`. Pas d'exception
silencieuse, pas de seuil arbitraire (« > 30 lignes »).

### P2 — Cohésion par sujet métier
Tout ce qui change ensemble vit ensemble (feature folder /
vertical slice). Pas de découpage horizontal par type de
fichier qui force à naviguer entre 3 dossiers pour modifier
une feature.

### P3 — Mapping mental Back ↔ Front
| Backend | Frontend |
|---|---|
| Controller HTTP | Page Vue (orchestrateur) |
| Action (orchestration) | Composable (orchestration) |
| Service (logique métier) | Composable (logique réactive) |
| Repository (queries) | useApi / composable de fetch |
| DTO (Data + DTO) | Type généré (App.Data.*) |

---

## Règles backend

### R1 — Controller minimal
- Zéro Eloquent dans les controllers (`::query`, `::find`,
  `::findOrFail`, `DB::table`)
- < 30 lignes par méthode (orchestration + Inertia::render
  uniquement)
- Validation déléguée aux DTOs Spatie Data (pas de
  FormRequest custom hors `Auth/`)

### R2 — Action vs Service direct
- **Si 1 controller method appelle 1 seul service trivial** :
  controller appelle le service direct, pas d'Action.
- **Si 1 controller method orchestre 2+ services**, ou comporte
  une logique transactionnelle complexe : Action obligatoire
  (`__invoke()` non, **convention `execute()`** pour la
  méthode publique). Une Action = un cas d'usage.

### R3 — Service = logique métier pure d'un domaine
- Méthodes liées à un domaine cohérent (ex.
  `VehicleQueryService::listForFleetView`,
  `FiscalCalculator::calculate`)
- **Pas de query BDD** (sauf trivial — voir R3-bis)
- Pas d'orchestration multi-domaines (c'est le rôle de
  l'Action)

### R3-bis — Exception « Service trivial »
Un service peut contenir une requête Eloquent **uniquement
si TOUTES** ces 5 conditions sont remplies :

1. **Une seule requête** (pas de query composées)
2. **Pas de joins** (`->with()` simple OK, mais pas
   `whereHas`, pas de subquery, pas d'eager-load conditionnel)
3. **Pas d'agrégation** (`groupBy`, `selectRaw COUNT/SUM`,
   `having`)
4. **Pas de eager-loading conditionnel** (`with(['rel' => fn
   ($q) => $q->where(...)])` interdit ici)
5. **Mapping DTO direct** sans transformation conditionnelle
   (juste `->map(fn (X $x) => new XData(...))`)

Si **une seule** condition tombe → **Repository obligatoire**.

Exemples :
- ✅ OK dans le service :
  `FiscalRule::query()->where('fiscal_year', $year)->orderBy('display_order')->get()->map(fn (...) => new XxxData(...))`
- ❌ Repository obligatoire :
  `Vehicle::query()->with(['fiscalCharacteristics' => fn ($q) => $q->whereNull('effective_to')])->whereIn('id', $ids)->get()->keyBy('id')`

### R4 — Repository = queries BDD
- Toutes les requêtes Eloquent non-triviales (R3-bis)
- Retourne soit des modèles Eloquent, soit des DTOs internes
- **Aucune logique métier** (pas de calculs, pas
  d'orchestration)
- Interface obligatoire + binding dans `RepositoryServiceProvider`

### R5 — DTOs : exposés vs internes
- **`app/Data/`** : DTOs Spatie Data **exposés au front** via
  Inertia ou JSON. Marqueur `#[TypeScript]` obligatoire.
  Génère automatiquement les types TS sous
  `resources/js/types/generated/`.
- **`app/DTO/`** : DTOs **internes métier** (`final readonly`
  classes simples), retournés par les services/repositories,
  jamais exposés au front. Pas de Spatie, pas de TS.
- **`app/Services/.../Dto/`** : interdit (mélange
  responsabilités).

### R6 — Tests PHP
- `tests/Unit/` : services, calculator, value objects (rapide,
  pas de DB)
- `tests/Feature/` : controllers, middleware, JSON endpoints
  (avec DB via RefreshDatabase)
- Miroir de la structure de `app/`

### R6-bis — Couverture exigée
- Tout nouveau controller : test feature `AssertableInertia`
  avec assertion sur la shape du payload
- Tout nouveau service : test unitaire des cas principaux
- Tout nouveau Repository : test unitaire avec DB en mémoire si
  trivial, ou Feature si query complexe

---

## Règles frontend

### R7 — Page Vue = orchestrateur pur
- `defineProps<>()` typé (DTO généré)
- Instanciation de composables
- Connexion entre composables et template
  (`@submit="handleSubmit"`)
- Données strictement statiques (config arrays, labels
  constants)
- **Aucune logique réactive** (pas de fetch, pas de watch
  complexe, pas de computed dérivé non trivial)

Si la page contient l'un de ces patterns → c'est une violation.
La logique part dans un composable.

### R7-bis — Structure stricte « 1 page = 1 dossier »
Chaque page vit dans son **propre dossier** dédié, **toujours**
(même sans partials actuels). Structure :

```
pages/{Zone}/{Domaine}/{NomPage}/
├── Index.vue          ← orchestrateur de la page (~50-80 lignes)
├── forms.ts           ← schéma TS du form (si applicable)
├── partials/          ← partials de cette page uniquement
│   ├── HeaderSection.vue
│   ├── FleetTable.vue
│   └── ...
└── (autres fichiers auxiliaires liés)
```

L'appel `Inertia::render('User/Vehicles/Index/Index')`
référence le `Index.vue` du dossier `Index/` (pas confondre
avec une "page index" du dossier parent).

### R7-ter — Localisation des fichiers auxiliaires
Pour `forms.ts`, `composables.ts` privés, `utils.ts` locaux,
etc., la règle dépend du nombre de consommateurs :

| Cas | Emplacement |
|---|---|
| **1 page seulement** | Dans le dossier de cette page |
| **N pages d'un même domaine** | Racine du domaine, dossier `_shared/` (ex. `pages/User/Vehicles/_shared/forms.ts`) |
| **Cross-domaine** | Dossier dédié racine (ex. `resources/js/forms/...`) |

### R7-quater — Segmentation systématique en partials
Toute page **bien segmentée** a au moins 2-3 partials
représentant des **sections logiquement cohérentes** (header
avec son CTA principal, table avec ses actions de ligne,
toolbar de filtres, empty state, etc.).

**Anti-pattern** : un partial = un bouton seul, un titre seul,
une icône. Un partial doit représenter une **unité fonctionnelle
identifiable** par un nom métier.

### R8 — Composables par domaine métier
Organisation : `Composables/{Shared, Fiscal, Planning, Vehicle,
Company, Assignment, ...}/`. **Tout** ce qui n'est pas
strictement statique vit dans un composable :
- Tout fetch async (avec loading/data/error)
- Tout state + computed/watch dépendant
- Toute logique réutilisable

### R9 — Partial = présentation pure
- `defineProps<>()` + `defineEmits<>()`
- Markup + interactions triviales
- **Pas de logique business**, pas de fetch, pas de state
  global
- Vit toujours dans `partials/` à côté du parent

### R10 — Util = fonction pure sans état
- `Utils/{date, format, math, ...}/` selon la nature
- Pas de réactivité, pas de side-effect
- Tests Vitest obligatoires (au moins les cas principaux)

### R11 — Types
- **`types/generated/`** : auto Spatie + Enums, intouchable
- **`types/ui/`** : types maison composants (DataTableColumn,
  ButtonVariant…)
- **`types/inertia.d.ts`** : declare module @inertiajs PageProps
- Le namespace global `App.Data.*` / `App.Enums.*` est utilisé
  partout (pas d'import nécessaire)

### R12 — URLs : Wayfinder uniquement
- Toujours via `import { x } from '@/routes/...'` puis
  `x.url()`
- **Jamais** d'URL hardcodée `/app/...`
- **Jamais** d'usage de `@/actions/...` (auto-généré, ignoré
  par convention — voir R12-bis)

### R12-bis — Wayfinder : `@/routes/` only
Wayfinder génère par défaut **deux** dossiers (redondants)
avec les mêmes URLs : `routes/` (organisé par préfixe URL) et
`actions/` (organisé par chemin du controller PHP). On
n'utilise **que `routes/`**. `actions/` est ignoré dans ESLint
+ tsconfig pour ne pas polluer.

### R13 — Tests JS
- Sous **`tests/js/`** (séparé du code source, miroir de
  `resources/js/`)
- **Pas de colocalisation** `.test.ts` à côté du code (cohérence
  avec PHPUnit + lisibilité de la couverture)
- Vitest + happy-dom + `@vue/test-utils`

---

## Règles transversales

### R14 — Pas d'exception silencieuse
Si une règle s'applique, on la respecte. Si on doit déroger,
**commentaire explicite** dans le code :
```php
// Exception R3 : la query DB est ici car… (raison concrète)
```
Pas d'exception "parce que c'était plus simple".

### ~~R15 — Application progressive pour l'existant~~ (RETIRÉE en 1.7)

**Retrait** : R15 a servi de porte de sortie pour différer la
création des Repositories sur l'existant des phases 0-1.5. C'est
en contradiction directe avec P1 (« Strictness > ça dépend ») et
avec le principe « irréprochable senior » exigé par le client.
Les règles s'appliquent immédiatement et partout, y compris à
l'existant. Le chantier 1.7 a appliqué R3-bis/R4 à tous les
services existants.

---

## Conséquences

### Positives
- Prédictibilité maximale : chaque type d'élément a UNE place
- Scalable : jamais de dossier > 30 fichiers, jamais de
  fichier > 200 lignes
- Défendable devant un senior PHP comme devant un senior Vue
- Mapping mental Back ↔ Front facilité

### Coûts assumés
- Plus de fichiers (un dossier par page, partials systématiques)
- Plus de classes (Action + Service + Repository pour un
  domaine non trivial)
- Convention divergente du "mainstream Vue" sur 2 points :
  tests séparés (vs colocalisés) et structure stricte
  (vs flexible)

Le coût est négligeable face au gain de prédictibilité.

---

## Validation par le chantier 1.6

Cet ADR est rédigé en parallèle du chantier 1.6 qui applique
ces règles à l'existant :
- Suppression `types/api/` placeholder
- Migration tests Vitest vers `tests/js/`
- `@/actions/` ignoré, `@/routes/` only
- Extraction `useWeekDetail` (R7 violée par
  `Planning/Index.vue::openWeek`)
- Restructuration des 6 pages User selon R7-bis
- Adaptation des chaînes `Inertia::render` côté controllers

Tous les checks doivent rester verts (Pint, ESLint, vue-tsc,
PHPUnit 85/85, Vitest 19/19, build).

---

## Références

- ADR-0006 (architecture moteur de règles)
- ADR-0008 (stack technique V1)
- ADR-0011 (sécurité applicative)
- `project-management/implementation-rules/architecture-solid.md`
- `project-management/implementation-rules/composables-services-utils.md`
- `project-management/implementation-rules/structure-fichiers.md`
