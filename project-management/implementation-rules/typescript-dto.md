# TypeScript et DTO Spatie Data — frontière typée PHP ↔ Vue

> **Stack référence** : Laravel 13, Spatie Laravel Data 4.22, Spatie TypeScript Transformer 3.1, Inertia v3, Vue 3.5, TypeScript 6, PHP 8.5.
> **Niveau d'exigence** : senior +. Aucun `any` toléré. Aucune duplication manuelle de type entre PHP et TS.
> **Documents liés** : `architecture-solid.md`, `conventions-nommage.md`, `vue-composants.md`, `composables-services-utils.md`, `gestion-erreurs.md`.

---

## Pourquoi cette règle existe

Le risque numéro un d'une SPA Inertia + Vue avec backend Laravel est la **divergence silencieuse entre les types PHP et les types TypeScript**. Un champ renommé côté PHP qui n'est pas synchronisé côté TS produit un `undefined` au runtime sans erreur de compilation. Sur un projet de la taille de Floty, c'est inévitable si on maintient deux représentations à la main.

La solution Floty repose sur une **chaîne de génération automatique** qui fait du PHP la source unique de vérité :

```
Classe PHP Spatie\LaravelData\Data
    ↓ (annotation #[TypeScript])
Spatie TypeScript Transformer
    ↓ (commande php artisan typescript:transform)
resources/js/types/generated.d.ts
    ↓ (import dans les composants Vue)
Composant Vue typé strictement
```

**Conséquence directe** : on ne réécrit **jamais** un type TS qui correspond à un DTO PHP. La générgération auto est la frontière contractuelle. Toute violation de cette règle est une dette technique immédiate.

---

## Configuration TypeScript stricte

### `tsconfig.json` — règles strictes obligatoires

```json
{
  "compilerOptions": {
    "target": "ES2022",
    "module": "ESNext",
    "moduleResolution": "bundler",
    "lib": ["ES2022", "DOM", "DOM.Iterable"],
    "jsx": "preserve",

    "strict": true,
    "noImplicitAny": true,
    "strictNullChecks": true,
    "strictFunctionTypes": true,
    "strictBindCallApply": true,
    "strictPropertyInitialization": true,
    "noImplicitThis": true,
    "alwaysStrict": true,

    "noUnusedLocals": true,
    "noUnusedParameters": true,
    "noImplicitReturns": true,
    "noFallthroughCasesInSwitch": true,
    "noUncheckedIndexedAccess": true,
    "exactOptionalPropertyTypes": true,
    "noImplicitOverride": true,

    "esModuleInterop": true,
    "forceConsistentCasingInFileNames": true,
    "skipLibCheck": true,
    "isolatedModules": true,
    "resolveJsonModule": true,
    "allowSyntheticDefaultImports": true,

    "baseUrl": ".",
    "paths": {
      "@/*": ["resources/js/*"],
      "@css/*": ["resources/css/*"]
    },

    "types": ["vite/client", "node"]
  },
  "include": [
    "resources/js/**/*.ts",
    "resources/js/**/*.vue",
    "resources/js/**/*.d.ts"
  ],
  "exclude": ["node_modules", "public", "vendor"]
}
```

### Justification des options strictes

| Option | Pourquoi obligatoire en Floty |
|---|---|
| `strict: true` | Active toutes les vérifications strictes par défaut. Base non négociable. |
| `noUncheckedIndexedAccess` | Force `T[n]` à retourner `T \| undefined`. Élimine les accès tableau aveugles, source numéro un de bugs en SPA. |
| `exactOptionalPropertyTypes` | Distingue `prop?: string` (peut être absent) de `prop: string \| undefined` (présent mais undefined). Important pour Inertia : un champ Spatie Data optionnel ne doit pas être confondu avec une valeur null. |
| `noImplicitOverride` | Force `override` explicite (PHP 8.3+ a son équivalent `#[\Override]`). Cohérence cross-langages. |
| `noUnusedLocals` / `noUnusedParameters` | Évite les variables mortes qui polluent la lecture. |
| `noFallthroughCasesInSwitch` | Garantit l'exhaustivité côté TS (équivalent du `match` exhaustif PHP). |

### Exhaustivité d'enum côté TS — pattern à utiliser

```ts
type DeclarationStatus = 'draft' | 'verified' | 'generated' | 'sent'

function statusLabel(status: DeclarationStatus): string {
  switch (status) {
    case 'draft': return 'Brouillon'
    case 'verified': return 'Vérifiée'
    case 'generated': return 'Générée'
    case 'sent': return 'Envoyée'
    default: {
      const exhaustive: never = status
      throw new Error(`Status non géré : ${exhaustive}`)
    }
  }
}
```

Si on ajoute `'archived'` à l'enum côté PHP, le `default` détecte que `status` n'est plus exhaustivement `never` → erreur de compilation TS qui force la mise à jour. C'est exactement le filet de sécurité qu'on veut.

---

## Spatie Laravel Data — le DTO PHP

### Anatomie d'une classe Data

Toutes les classes Data **héritent de `Spatie\LaravelData\Data`**, sont **`final readonly class`** (immuables, non héritables), et sont **annotées `#[TypeScript]`** si exposées au front.

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
        public readonly string $immatriculation,
        public readonly string $marque,
        public readonly string $modele,
        public readonly VehicleUserType $vehicleUserType,
        public readonly EnergySource $energySource,
        public readonly ?string $photoUrl,
        public readonly bool $isActive,
        public readonly ?int $currentYearAttributionsCount,
    ) {}
}
```

### Type TypeScript généré automatiquement

```ts
// resources/js/types/generated.d.ts (NE PAS ÉDITER — auto-généré)
declare namespace App.Data.User.Vehicle {
  export type VehicleListItemData = {
    id: number
    immatriculation: string
    marque: string
    modele: string
    vehicleUserType: App.Enums.Vehicle.VehicleUserType
    energySource: App.Enums.Vehicle.EnergySource
    photoUrl: string | null
    isActive: boolean
    currentYearAttributionsCount: number | null
  }
}
```

### Convention d'import côté Vue

Le fichier `resources/js/types/generated.d.ts` déclare les types dans des **namespaces** miroirs des namespaces PHP. Pour faciliter l'usage, on configure un alias dans `app.ts` :

```ts
// resources/js/types/index.ts (manuel, ré-exporte les types pour usage simple)
export type VehicleListItemData = App.Data.User.Vehicle.VehicleListItemData
export type VehicleData = App.Data.User.Vehicle.VehicleData
export type VehicleFormData = App.Data.User.Vehicle.VehicleFormData
export type VehicleFiscalCharacteristicsData = App.Data.User.Vehicle.VehicleFiscalCharacteristicsData

export type CompanyData = App.Data.User.Company.CompanyData
// ... etc.

// Re-export des enums pour symétrie
export type VehicleUserType = App.Enums.Vehicle.VehicleUserType
export type EnergySource = App.Enums.Vehicle.EnergySource
export type DeclarationStatus = App.Enums.Declaration.DeclarationStatus
```

```vue
<!-- resources/js/Pages/User/Vehicles/Index/Index.vue -->
<script setup lang="ts">
import type { VehicleListItemData } from '@/types'

defineProps<{
  vehicles: VehicleListItemData[]
  fiscalYear: number
}>()
</script>
```

> **Pourquoi un fichier `index.ts` qui ré-exporte** : éviter dans le code applicatif les chemins verbeux `App.Data.User.Vehicle.VehicleListItemData`. On importe simplement depuis `@/types`. Cohérent avec les standards des projets seniors.

---

## Composition de Data — types imbriqués et collections

### Embarquer un autre Data

```php
#[TypeScript]
final class DeclarationData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly CompanyData $company,
        public readonly int $fiscalYear,
        public readonly DeclarationStatus $status,
        public readonly ?int $totalTaxeCo2,
        public readonly ?int $totalTaxePolluants,
        public readonly bool $isInvalidated,
    ) {}
}
```

→ Type TS généré :

```ts
declare namespace App.Data.User.Declaration {
  export type DeclarationData = {
    id: number
    company: App.Data.User.Company.CompanyData
    fiscalYear: number
    status: App.Enums.Declaration.DeclarationStatus
    totalTaxeCo2: number | null
    totalTaxePolluants: number | null
    isInvalidated: boolean
  }
}
```

### Collection typée

PHP n'ayant pas de génériques natifs, le typage de collection passe par un **PHPDoc explicite** que Spatie lit pour générer le bon type TS.

```php
#[TypeScript]
final class DeclarationData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly CompanyData $company,
        /** @var DeclarationPdfData[] */
        public readonly array $pdfs,
        /** @var array<string, int> */
        public readonly array $totalsByCategory,
    ) {}
}
```

→ Type TS généré :

```ts
declare namespace App.Data.User.Declaration {
  export type DeclarationData = {
    id: number
    company: App.Data.User.Company.CompanyData
    pdfs: App.Data.User.Declaration.DeclarationPdfData[]
    totalsByCategory: { [key: string]: number }
  }
}
```

> **Règle stricte** : tout `array` PHP exposé au front **doit** avoir un PHPDoc `@var` typé. Sans cela, le type TS généré sera `any[]`, ce qui contredit `noImplicitAny`.

### Dates et types complexes

| Type PHP | Type TS généré (par défaut) | Comment forcer |
|---|---|---|
| `\DateTimeInterface` / `Carbon\Carbon` | `string` (ISO 8601) | Convertir explicitement à la lecture côté Vue (`new Date(props.date)`) |
| `\BackedEnum` | Type union de strings (`'a' \| 'b'`) | Auto |
| `Illuminate\Support\Collection` | Tableau du type sous-jacent | PHPDoc `@var Type[]` recommandé |
| `Spatie\LaravelData\DataCollection<T>` | `T[]` | Auto si T est un Data annoté |
| `Money\Money` (si lib money) | Configurer un transformer custom | Via `spatie/typescript-transformer` config |

---

## Variantes de Data — séparer les usages

Une même entité métier a souvent **plusieurs représentations DTO** selon le contexte d'usage. On ne doit **jamais** réutiliser un Data « complet » pour des usages où un sous-ensemble suffit.

### Pattern Floty — les 4 variantes types

| Variante | Suffixe | Usage | Exemple |
|---|---|---|---|
| **Représentation complète** | `{Entité}Data` | Vue détail (`Show.vue`), références imbriquées | `VehicleData` (tous les champs + relations) |
| **Liste légère** | `{Entité}ListItemData` | Vue liste (`Index.vue`), heatmap, sélecteur | `VehicleListItemData` (champs minimaux pour l'affichage) |
| **Formulaire** | `{Entité}FormData` | Création / édition | `VehicleFormData` (subset modifiable) |
| **DTO d'entrée** (validation) | `{Entité}{Verbe}Data` | Reçu en POST/PUT | `VehicleStoreData`, `VehicleUpdateData` |

### Exemple Floty complet — Véhicule

```php
// 1. Représentation complète — Show vue, références
#[TypeScript]
final class VehicleData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $immatriculation,
        public readonly string $marque,
        public readonly string $modele,
        public readonly VehicleUserType $vehicleUserType,
        public readonly BodyType $bodyType,
        public readonly int $seatsCount,
        public readonly string $firstFrenchRegistrationDate,
        public readonly string $firstOriginRegistrationDate,
        public readonly string $firstEconomicUseDate,
        public readonly string $acquisitionDate,
        public readonly ?string $exitDate,
        public readonly ?string $exitReason,
        public readonly string $currentStatus,
        public readonly ?int $mileageCurrent,
        public readonly ?string $vin,
        public readonly ?string $couleur,
        public readonly ?string $photoUrl,
        public readonly ?string $notes,
        public readonly VehicleFiscalCharacteristicsData $currentFiscalCharacteristics,
        /** @var VehicleFiscalCharacteristicsData[] */
        public readonly array $fiscalCharacteristicsHistory,
    ) {}
}

// 2. Liste — Index vue, heatmap
#[TypeScript]
final class VehicleListItemData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $immatriculation,
        public readonly string $marque,
        public readonly string $modele,
        public readonly VehicleUserType $vehicleUserType,
        public readonly EnergySource $energySource,
        public readonly ?string $photoUrl,
        public readonly bool $isActive,
    ) {}
}

// 3. Formulaire — Create/Edit
#[TypeScript]
final class VehicleFormData extends Data
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $immatriculation,
        public readonly string $marque,
        public readonly string $modele,
        public readonly VehicleUserType $vehicleUserType,
        public readonly BodyType $bodyType,
        public readonly int $seatsCount,
        public readonly string $firstFrenchRegistrationDate,
        public readonly string $firstOriginRegistrationDate,
        public readonly string $firstEconomicUseDate,
        public readonly string $acquisitionDate,
        public readonly ?string $vin,
        public readonly ?string $couleur,
        public readonly ?string $notes,
        public readonly VehicleFiscalCharacteristicsFormData $fiscalCharacteristics,
    ) {}
}

// 4. DTO d'entrée — pour les Actions
#[TypeScript]
final class VehicleStoreData extends Data
{
    public function __construct(
        public readonly string $immatriculation,
        public readonly string $marque,
        public readonly string $modele,
        public readonly VehicleUserType $vehicleUserType,
        public readonly BodyType $bodyType,
        public readonly int $seatsCount,
        public readonly string $firstFrenchRegistrationDate,
        public readonly string $firstOriginRegistrationDate,
        public readonly string $firstEconomicUseDate,
        public readonly string $acquisitionDate,
        public readonly ?string $vin,
        public readonly ?string $couleur,
        public readonly ?string $notes,
        public readonly VehicleFiscalCharacteristicsData $fiscalCharacteristics,
    ) {}
}
```

### Pourquoi 4 variantes et pas une seule

Un reviewer senior repère immédiatement les problèmes du modèle « DTO unique pour tout faire » :

1. **Sur-fetching** : on charge en BDD des relations qui ne servent qu'à `Show` mais qu'on envoie aussi sur `Index` → bundle plus lourd, requête SQL plus chère.
2. **Couplage** : un changement sur `VehicleData` (ajout d'un champ relation) impacte toutes les vues, alors que la plupart n'en ont rien à faire.
3. **Sécurité** : le DTO complet expose des champs qui peuvent ne pas être à exposer au format formulaire (ex: `currentStatus` calculé en backend).
4. **Validation** : un DTO d'entrée a des règles de validation différentes d'un DTO de sortie.

La **séparation des variantes** est un investissement faible (4 classes au lieu de 1) qui paie sur tout le cycle de vie du projet.

---

## Coercion et transformation des entrées

### `from()` — point d'entrée standardisé

Spatie Data offre `Data::from(...)` qui transforme une source (array, Eloquent model, JSON) en instance Data typée avec coercion automatique des types.

```php
// Depuis un array (ex: $request->validated())
$data = VehicleStoreData::from($request->validated());

// Depuis un modèle Eloquent (mapping automatique des colonnes)
$data = VehicleData::from($vehicle);

// Depuis une collection
$collection = VehicleListItemData::collect($vehicles); // retourne DataCollection
```

### Cas d'usage Floty

```php
// Controller
public function store(StoreVehicleRequest $request, CreateVehicleAction $action): RedirectResponse
{
    $data = VehicleStoreData::from($request->validated());
    $vehicle = $action->execute($data);

    return redirect()->route('user.vehicles.show', ['vehicle' => $vehicle->id]);
}

// Action
public function execute(VehicleStoreData $data): Vehicle
{
    return DB::transaction(function () use ($data): Vehicle {
        // ... orchestration
    });
}
```

### Règle stricte sur le `from()`

- `Data::from()` reçoit toujours **des données déjà validées** (par `FormRequest`) ou **un modèle Eloquent**.
- **Jamais** `Data::from($_POST)` ou `Data::from($request->all())` directement — c'est contourner la validation.
- La coercion ne valide pas, elle transforme. La validation se fait par `FormRequest` ou par règles Data si on choisit cette approche (Floty utilise FormRequest cf. `gestion-erreurs.md`).

---

## Génération automatique des types TS

### Configuration `config/typescript-transformer.php`

```php
return [
    'collectors' => [
        Spatie\LaravelData\Support\TypeScriptTransformer\DataTypeScriptCollector::class,
        Spatie\TypeScriptTransformer\Collectors\DefaultCollector::class,
        Spatie\TypeScriptTransformer\Collectors\EnumCollector::class,
    ],

    'transformers' => [
        Spatie\LaravelData\Support\TypeScriptTransformer\DataTypeScriptTransformer::class,
        Spatie\TypeScriptTransformer\Transformers\EnumTransformer::class,
    ],

    // Dossiers scannés
    'auto_discover_types' => [
        app_path('Data'),
        app_path('Enums'),
    ],

    // Fichier de sortie unique
    'output_file' => resource_path('js/types/generated.d.ts'),

    // Format
    'writer' => Spatie\TypeScriptTransformer\Writers\TypeDefinitionWriter::class,
];
```

### Commande

```bash
php artisan typescript:transform
```

Cette commande **doit être exécutée** :

- Après chaque modification d'une classe Data ou d'un Enum exposé.
- Avant chaque commit modifiant ces classes (idéalement automatisé via Git hook).
- Avant chaque build de production (intégrer au workflow CI/CD).

### Intégration dans le workflow

#### Option A — Git pre-commit hook (recommandé pour Floty)

```bash
# .githooks/pre-commit
#!/bin/sh
php artisan typescript:transform
git add resources/js/types/generated.d.ts
```

#### Option B — Script npm dans `package.json`

```json
{
  "scripts": {
    "types:generate": "php artisan typescript:transform",
    "build": "npm run types:generate && vite build",
    "dev": "npm run types:generate && vite"
  }
}
```

#### Option C — Watcher en dev (Spatie Data le supporte)

```bash
php artisan typescript:transform --watch
```

À lancer en parallèle de `npm run dev` pendant le développement.

### Le fichier `generated.d.ts` est commité

Bien qu'il soit auto-généré, **on le commit dans Git**. Justifications :

1. Diffs lisibles dans les pull requests : un reviewer voit immédiatement quels types ont changé.
2. CI : le build TS ne dépend pas d'avoir PHP exécutable dans le runner front.
3. Onboarding : un nouveau développeur n'a pas besoin de lancer `php artisan typescript:transform` pour avoir une compilation TS valide.

### Règle absolue — interdiction d'éditer manuellement

Le fichier `resources/js/types/generated.d.ts` porte un commentaire d'avertissement en tête (ajouté par le writer Spatie). **Aucune édition manuelle** n'y est tolérée. Si un type doit être modifié, c'est dans le DTO PHP source.

---

## Types TypeScript locaux (non générés)

Tous les types ne viennent pas de Spatie Data. Certains sont **purement applicatifs côté front** :

- État local d'un composant complexe (ex: `WeeklyEntrySelection`).
- Variantes de composants UI Kit (ex: `ButtonVariant`).
- Configuration de hooks/composables (ex: options d'un `useToast`).

### Emplacement et organisation

```
resources/js/types/
├── generated.d.ts                  ← AUTO-GÉNÉRÉ (Spatie TS Transformer)
├── index.ts                        ← ré-export des types Spatie pour usage simple
├── inertia.d.ts                    ← typage des shared props Inertia
├── env.d.ts                        ← typage import.meta.env
├── ui.ts                           ← types des composants UI Kit
└── domain/                         ← types métier locaux non issus de Spatie
    ├── planning.ts                 ← WeeklyEntrySelection, HeatmapFilter, etc.
    └── form.ts                     ← types des composables de formulaire
```

### Convention

- Types **dérivés d'un DTO Spatie** : ré-export dans `index.ts`, ne jamais redéfinir.
- Types **purement front** : fichier `.ts` dans `types/` (ou dans `types/domain/` si métier-spécifique).
- Types **hyper-locaux** (utilisés dans un seul composant) : déclarés dans le composant lui-même.

```ts
// resources/js/types/ui.ts
export type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger'
export type ButtonSize = 'sm' | 'md' | 'lg'

export type ToastVariant = 'success' | 'error' | 'warning' | 'info'

export type ToastPayload = {
  variant: ToastVariant
  message: string
  duration?: number
}
```

```ts
// resources/js/types/domain/planning.ts
import type { VehicleListItemData, CompanyData } from '@/types'

export type WeeklyEntrySelection = {
  vehicleIds: number[]
  dateRange: { start: string; end: string }
}

export type HeatmapFilter = {
  vehicleUserType?: VehicleListItemData['vehicleUserType']
  entreprise?: CompanyData['id']
}
```

> **Astuce TypeScript** : utiliser `VehicleListItemData['vehicleUserType']` plutôt que de redéclarer le type permet de **suivre automatiquement** les évolutions du DTO source. Si on renomme l'enum, le filtre suit.

---

## Typage des shared props Inertia

Inertia v3 expose des **shared props** disponibles sur toutes les pages (auth, flash, etc.). Il faut les typer explicitement pour que `usePage().props` soit correctement inferred dans les composants.

```ts
// resources/js/types/inertia.d.ts
import type { CurrentUserData } from '@/types'

declare module '@inertiajs/core' {
  interface PageProps {
    flash: {
      success: string | null
      error: string | null
      warning: string | null
      info: string | null
    }
    auth: {
      user: CurrentUserData | null
    }
    appName: string
  }
}
```

### Usage typé dans un composable

```ts
// resources/js/Composables/User/useCurrentUser.ts
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import type { CurrentUserData } from '@/types'

export function useCurrentUser() {
  const page = usePage()

  const user = computed<CurrentUserData | null>(() => page.props.auth.user)
  const isAuthenticated = computed(() => user.value !== null)

  return { user, isAuthenticated }
}
```

> Le composable est **fortement typé** parce que `page.props.auth.user` est typé via `inertia.d.ts`. Pas besoin de `any` ou de cast.

---

## Pattern — typage d'un `useForm` Inertia

### Le réflexe naïf — type lâche

```ts
const form = useForm({
  immatriculation: '',
  marque: '',
  modele: '',
})
// form.data() est typé { immatriculation: string; marque: string; modele: string }
// mais on a perdu le lien avec VehicleFormData côté backend
```

### Le bon pattern — type rattaché au DTO

```ts
import { useForm } from '@inertiajs/vue3'
import type { VehicleFormData } from '@/types'

// Type des champs initiaux pour useForm — sous-ensemble du VehicleFormData
type VehicleFormFields = Omit<VehicleFormData, 'id'>

const form = useForm<VehicleFormFields>({
  immatriculation: '',
  marque: '',
  modele: '',
  vehicleUserType: 'VP',
  bodyType: 'CI',
  seatsCount: 5,
  firstFrenchRegistrationDate: '',
  firstOriginRegistrationDate: '',
  firstEconomicUseDate: '',
  acquisitionDate: '',
  vin: null,
  couleur: null,
  notes: null,
  fiscalCharacteristics: { /* ... */ },
})
```

→ Si on ajoute un champ à `VehicleFormData` côté PHP, on aura une **erreur TS** sur le composable Vue qui force la mise à jour. Filet de sécurité.

> Le détail des stratégies de formulaire sera couvert dans `inertia-navigation.md`.

---

## Anti-patterns à proscrire (repérés en revue senior)

### Sur les types TS

| Anti-pattern | Pourquoi c'est mauvais | Correction |
|---|---|---|
| Type TS écrit à la main qui dupliquerait un DTO PHP | Divergence garantie au fil du temps, source de bugs silencieux | Toujours auto-générer via Spatie TS Transformer |
| `any` ou `unknown` non justifié | Perte du bénéfice TypeScript, propagation virale | Utiliser le type précis ; si vraiment générique, contraindre via génériques |
| Cast forcé `value as VehicleData` | Cache un problème de typage, peut masquer un bug runtime | Refactor pour que le type soit correct dès l'origine |
| Type union d'objets sans champ discriminant | Impossible de narrower correctement | Ajouter un champ `type` (ex: `{ type: 'success'; data: T }`) |
| `Object`, `Function` (capitalized) | Types trop larges, anti-pattern bien connu | Utiliser `object`, `(...args: never[]) => unknown` selon besoin |
| Interface au lieu de type sans raison | TypeScript moderne préfère `type` (sauf déclaration ouverte) | `type` par défaut, `interface` si besoin d'extension |
| Énumération native `enum` | Tree-shaking imparfait, runtime overhead, semantics étranges | Type union de strings : `type Status = 'a' \| 'b' \| 'c'` |
| Type `Vehicle` redéfini dans chaque composant qui l'utilise | Duplication, drift inévitable | Centralisé dans `types/` et importé |

### Sur les DTO Spatie Data

| Anti-pattern | Correction |
|---|---|
| Classe Data **non `final readonly`** | Toujours `final readonly class extends Data` (immutabilité, pas d'héritage) |
| Propriétés **non `readonly`** | Toutes les propriétés du constructeur sont `public readonly` |
| Annotation `#[TypeScript]` oubliée sur un Data exposé au front | Le type n'est pas généré → divergence garantie. Ajouter systématiquement. |
| `array` PHP sans PHPDoc `@var` typé | Le type TS généré est `any[]` → contredit `noImplicitAny`. Toujours typer. |
| Logique métier dans la classe Data (méthodes calculées qui touchent la BDD) | Les Data sont des transports immuables. La logique vit dans Service. |
| DTO unique réutilisé pour Index, Show, Form | Sur-fetching + couplage. Créer 4 variantes (`Data`, `ListItemData`, `FormData`, `StoreData`). |
| `Data::from($request->all())` | Contourne la validation. Toujours via `FormRequest` puis `Data::from($request->validated())`. |
| Modèle Eloquent passé directement à `Inertia::render` | Fuite de données, pas de typage TS. Toujours via Data. |
| Édition manuelle de `generated.d.ts` | Sera écrasée à la prochaine génération. Modifier la classe Data PHP. |

---

## Workflow type-safe complet — exemple Floty

Cycle de vie complet d'une donnée Floty, du backend au composant Vue :

### 1. Définir le DTO PHP

```php
namespace App\Data\User\Vehicle;

#[TypeScript]
final class VehicleListItemData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $immatriculation,
        public readonly string $marque,
        public readonly string $modele,
        public readonly VehicleUserType $vehicleUserType,
        public readonly bool $isActive,
    ) {}
}
```

### 2. Générer les types TS

```bash
php artisan typescript:transform
```

```ts
// resources/js/types/generated.d.ts (auto)
declare namespace App.Data.User.Vehicle {
  export type VehicleListItemData = {
    id: number
    immatriculation: string
    marque: string
    modele: string
    vehicleUserType: App.Enums.Vehicle.VehicleUserType
    isActive: boolean
  }
}
```

### 3. Ré-exporter pour usage simple

```ts
// resources/js/types/index.ts
export type VehicleListItemData = App.Data.User.Vehicle.VehicleListItemData
```

### 4. Controller Laravel renvoie le DTO

```php
public function index(VehicleListReadRepository $repository): Response
{
    $vehicles = $repository->listActive();

    return Inertia::render('User/Vehicles/Index/Index', [
        'vehicles' => VehicleListItemData::collect($vehicles),
        'fiscalYear' => current_fiscal_year(),
    ]);
}
```

### 5. Composant Vue consomme le type

```vue
<!-- resources/js/Pages/User/Vehicles/Index/Index.vue -->
<script setup lang="ts">
import type { VehicleListItemData } from '@/types'
import VehicleTable from './Partials/VehicleTable.vue'
import VehicleListHeader from './Partials/VehicleListHeader.vue'

defineProps<{
  vehicles: VehicleListItemData[]
  fiscalYear: number
}>()
</script>

<template>
  <div>
    <VehicleListHeader :fiscal-year="fiscalYear" />
    <VehicleTable :vehicles="vehicles" />
  </div>
</template>
```

### 6. Le partial reçoit le type via prop

```vue
<!-- resources/js/Pages/User/Vehicles/Index/Partials/VehicleTable.vue -->
<script setup lang="ts">
import type { VehicleListItemData } from '@/types'

defineProps<{
  vehicles: VehicleListItemData[]
}>()
</script>
```

### 7. Si l'on ajoute un champ côté PHP

```php
// VehicleListItemData.php — ajout d'un champ
public function __construct(
    // ... existant
    public readonly ?int $currentYearAttributionsCount,
) {}
```

→ `php artisan typescript:transform` régénère.
→ Tous les composants Vue qui utilisent `VehicleListItemData` voient automatiquement le nouveau champ.
→ Si un composant accède à un champ qui n'existe plus, **erreur de compilation TS** au build suivant. Filet absolu.

---

## Checklist — avant de considérer un nouveau DTO comme « terminé »

- [ ] La classe est `final readonly class` et hérite de `Spatie\LaravelData\Data`.
- [ ] Toutes les propriétés du constructeur sont `public readonly` typées.
- [ ] Les annotations `#[TypeScript]` sont présentes si exposé au front.
- [ ] Tous les `array` ont un PHPDoc `@var` typé.
- [ ] La classe est dans `app/Data/{Espace}/{Domaine}/`.
- [ ] Le nom suit la convention (`{Entité}{Usage?}Data`).
- [ ] `php artisan typescript:transform` génère sans erreur.
- [ ] Le type est ré-exporté dans `resources/js/types/index.ts`.
- [ ] Aucun composant Vue ne redéfinit ce type manuellement.
- [ ] Si DTO d'entrée (validation), il est aligné avec un `FormRequest` correspondant.
- [ ] Le DTO ne porte aucune logique métier (juste construction et coercion).
- [ ] Le `generated.d.ts` est commité.

---

## Cohérence avec les autres règles

- **Architecture en couches** (Resource = 5ᵉ couche, qui est exactement ce DTO) : voir `architecture-solid.md`.
- **Conventions de nommage** (`{Entité}{Usage?}Data`, types TS PascalCase) : voir `conventions-nommage.md`.
- **Structure des fichiers** (emplacement `app/Data/{Espace}/{Domaine}/`) : voir `structure-fichiers.md`.
- **Composants Vue** (consommation des types via props typées) : voir `vue-composants.md`.
- **Composables, services, utils** (typage des composables, ré-export) : voir `composables-services-utils.md`.
- **Stores Pinia** (typage des stores) : voir `pinia-stores.md`.
- **Gestion des erreurs** (typage des erreurs validation Inertia) : voir `gestion-erreurs.md`.
- **Tests frontend** (fixtures typées) : voir `tests-frontend.md`.

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 1.0 | 24/04/2026 | Micha MEGRET | Rédaction initiale — règles TypeScript strict, DTO Spatie Data + génération auto via Spatie TypeScript Transformer, 4 variantes types par entité (Data/ListItemData/FormData/StoreData), pattern PHPDoc `@var` pour collections, typage des shared props Inertia, typage `useForm`, anti-patterns repérés en revue senior, exemples Floty (vehicle, declaration), workflow type-safe complet, checklist. |
