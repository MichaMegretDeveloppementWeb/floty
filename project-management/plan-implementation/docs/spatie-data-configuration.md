# Fiche — Configuration Spatie Laravel Data + TypeScript Transformer

> **Tâche associée** : `tasks/phase-00-init/02-install-spatie-data.md`
> **Objet** : la config exacte de Spatie Data + TS Transformer pour Floty.
> **Références** : `implementation-rules/typescript-dto.md`

---

## Rappel des composants

| Package | Rôle |
|---|---|
| `spatie/laravel-data` | Classes PHP `Data` (DTO immuables) |
| `spatie/typescript-transformer` | Moteur de génération TS |
| `spatie/laravel-typescript-transformer` | Pont Laravel pour TS Transformer |

## Installation

```bash
composer require spatie/laravel-data
composer require --dev spatie/typescript-transformer
composer require --dev spatie/laravel-typescript-transformer
```

Les versions attendues (ADR-0008) : Laravel Data **4.22+**, TypeScript Transformer **3.1+**.

## Config Laravel Data

```bash
php artisan vendor:publish --provider="Spatie\LaravelData\LaravelDataServiceProvider" --tag="data-config"
```

Ajustements `config/data.php` (notamment) :

```php
return [
    // Nom des attributs dans le JSON envoyé au front : camelCase
    'name_mapping_strategy' => [
        'input' => \Spatie\LaravelData\Mappers\SnakeCaseMapper::class,   // BDD snake_case → PHP
        'output' => null,                                                  // PHP camelCase → JSON camelCase
    ],

    // Règles par défaut : validation automatique depuis les types Data (non activée V1)
    'validation_strategy' => 'disabled',

    // Normalisation des dates : toujours ISO 8601 UTC dans le JSON
    'date_format' => DATE_ATOM,

    // Inclure les types propriétés en sortie (utile pour Spatie TS Transformer)
    'features' => [
        'cast_and_transform_iterables' => true,
    ],

    // autres options... (laisser les défauts Laravel Data 4.22)
];
```

## Config TS Transformer

```bash
php artisan vendor:publish --tag="typescript-transformer-config"
```

Contenu `config/typescript-transformer.php` pour Floty :

```php
use Spatie\LaravelData\Support\TypeScriptTransformer\DataTypeScriptCollector;
use Spatie\LaravelData\Support\TypeScriptTransformer\DataTypeScriptTransformer;
use Spatie\TypeScriptTransformer\Collectors\DefaultCollector;
use Spatie\TypeScriptTransformer\Collectors\EnumCollector;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\Writers\TypeDefinitionWriter;

return [
    // Dossiers scannés pour découvrir les classes annotées #[TypeScript]
    'auto_discover_types' => [
        app_path('Data'),
        app_path('Enums'),
    ],

    // Collecteurs : découvrent les classes candidates
    'collectors' => [
        DataTypeScriptCollector::class,
        EnumCollector::class,
        DefaultCollector::class,
    ],

    // Transformers : convertissent en types TS
    'transformers' => [
        DataTypeScriptTransformer::class,
        EnumTransformer::class,
    ],

    // Fichier de sortie unique
    'output_file' => resource_path('js/types/generated.d.ts'),

    // Writer : format du fichier produit
    'writer' => TypeDefinitionWriter::class,

    // Format des noms TS
    'format' => [
        'nullable_property_as_union' => true,   // `string | null` plutôt que `string?`
    ],
];
```

## Fichiers côté front à créer

### `resources/js/types/index.ts` (manuel, ré-export)

```ts
// resources/js/types/index.ts
// Ré-export pour usage simple dans l'app (évite les chemins verbeux App.Data.User.Vehicle.XxxData)

// Quand on créera des Data en phase 03+, on ré-exportera ici :
// export type CurrentUserData = App.Data.Shared.CurrentUserData
// export type VehicleData = App.Data.User.Vehicle.VehicleData
// etc.

// Vide pour l'instant — les ré-exports seront ajoutés au fil des phases.
export {}
```

### `resources/js/types/inertia.d.ts`

```ts
// resources/js/types/inertia.d.ts
// Typage des shared props Inertia (cf. typescript-dto.md § « Typage des shared props Inertia »)

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

// (sera complété au fur et à mesure avec les shared props Floty)
```

### `resources/js/types/env.d.ts`

```ts
/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_APP_NAME: string
  // plus à ajouter au fil du projet
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
```

## Scripts `package.json`

```json
{
  "scripts": {
    "types:generate": "php artisan typescript:transform",
    "types:check": "vue-tsc --noEmit",
    "dev": "concurrently \"php artisan typescript:transform --watch\" \"vite\"",
    "build": "npm run types:generate && vite build"
  }
}
```

Si `concurrently` n'est pas installé :
```bash
npm install -D concurrently
```

(Il est en fait déjà installé par le starter.)

## Validation post-install

1. Lancer `php artisan typescript:transform` → pas d'erreur, crée `resources/js/types/generated.d.ts` (peut-être vide ou avec un commentaire `// Auto-generated...`).
2. Vérifier que `resources/js/types/generated.d.ts` est bien commité (pas dans `.gitignore`).
3. Lancer `npm run build` → Wayfinder génère + Vite build OK.
4. Lancer `npm run types:check` → pas d'erreur TS.

## Notes

- Le fichier `generated.d.ts` est **commité** (pas dans `.gitignore`). Motif : cohérence avec Wayfinder, et permet à la CI de builder sans PHP. Cf. `typescript-dto.md` § « Le fichier `generated.d.ts` est commité ».
- **Ne jamais éditer `generated.d.ts` manuellement** — il sera écrasé à la prochaine génération.
- Le ré-export dans `types/index.ts` est un geste **manuel** (pas auto) pour chaque nouveau Data significatif — ça donne un chemin d'import propre `@/types`.
