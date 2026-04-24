# Fiche — Arborescence `app/` Floty

> **Tâche associée** : `tasks/phase-01-fondations-backend/01-create-app-structure.md`
> **Références** : `implementation-rules/structure-fichiers.md`, `implementation-rules/architecture-solid.md`, `implementation-rules/conventions-nommage.md`
> **Convention d'identifiants** : E1 strict — tout est en anglais sauf les libellés d'affichage utilisateur et les codes administratifs français préservés (VP, VU, CI, BB, CTTE, BE, HB, WLTP, NEDC, PA, SIREN, SIRET, VIN).

---

## Vue d'ensemble

```
app/
├── Actions/              — orchestrateurs métier (un cas d'usage = une action)
│   └── {Espace}/{Domaine}/
│       ├── CreateXxxAction.php
│       ├── UpdateXxxAction.php
│       └── DeleteXxxAction.php
│
├── Contracts/            — interfaces (essentiellement Repositories)
│   └── Repositories/
│       └── {Espace}/{Entité}/
│           └── XxxReadRepositoryInterface.php
│
├── Data/                 — DTO Spatie Data (frontière PHP↔TypeScript)
│   └── {Espace}/{Domaine}/
│       ├── XxxData.php
│       ├── XxxListItemData.php
│       ├── XxxFormData.php
│       └── XxxStoreData.php
│
├── Enums/                — énumérations PHP backed (transverses par domaine)
│   └── {Domaine}/
│       └── XxxType.php
│
├── Exceptions/           — exceptions métier typées (héritent BaseAppException)
│   ├── BaseAppException.php
│   └── {Domaine}/
│       └── XxxNotFoundException.php
│
├── Http/
│   ├── Controllers/      — points d'entrée HTTP + Inertia
│   │   └── {Espace}/{Domaine}/
│   │       └── XxxController.php
│   ├── Middleware/       — middleware Laravel (plat)
│   └── Requests/         — FormRequests (validation contextuelle)
│       └── {Espace}/{Domaine}/
│           └── StoreXxxRequest.php
│
├── Models/               — Eloquent (plat, un modèle par entité)
│   └── Xxx.php
│
├── Policies/             — autorisations Laravel (plat, une policy par modèle)
│   └── XxxPolicy.php
│
├── Providers/            — service providers
│   ├── AppServiceProvider.php
│   └── RepositoryServiceProvider.php
│
├── Repositories/         — accès BDD complexe (miroir de Contracts/Repositories/)
│   └── {Espace}/{Entité}/
│       └── XxxReadRepository.php
│
└── Services/             — logique métier pure
    └── {Espace}/{Domaine}/
        └── XxxService.php
```

## Règles de segmentation

| Couche | Segmentation | Motif |
|---|---|---|
| `Actions/` | `{Espace}/{Domaine}/` | Une action exprime une intention utilisateur dans un contexte précis |
| `Contracts/Repositories/` | Miroir de `Repositories/` | Contrat au même chemin que l'implémentation |
| `Data/` | `{Espace}/{Domaine}/` | Le DTO est exposé à un contexte UI donné |
| `Enums/` | `{Domaine}/` | Conceptuellement transverse à l'app (un enum peut être réutilisé entre espaces) |
| `Exceptions/` | `{Domaine}/` | Idem |
| `Http/Controllers/` | `{Espace}/{Domaine}/` | Présentation contextuelle |
| `Http/Requests/` | `{Espace}/{Domaine}/` | Validation contextuelle (règles diffèrent entre espaces) |
| `Http/Middleware/` | **Plat** | Middleware globaux ou de groupe |
| `Models/` | **Plat, par entité** | Un modèle Eloquent sert tous les espaces |
| `Policies/` | **Plat, par entité** | Une policy par modèle |
| `Providers/` | **Plat** | Service providers globaux |
| `Repositories/` | `{Espace}/{Entité}/` ou `Shared/{Entité}/` | Requêtes par espace, ou transverses |
| `Services/` | `{Espace}/{Domaine}/` ou `Shared/{Domaine}/` | Logique par espace, ou transverse |

## Les trois **espaces** V1

- **`Web/`** — surfaces publiques (landing, mentions légales). Aucune authentification requise.
- **`Auth/`** — surfaces d'authentification (login, reset password, changement mot de passe forcé).
- **`User/`** — zone connectée, gestionnaire flotte. Toutes les opérations métier V1.

Convention routes : `web.php` pour Web/, `auth.php` pour Auth/, `user.php` pour User/ avec middleware `auth` au niveau du groupe.

## L'espace `Shared/`

Apparaît **uniquement** dans `Services/` et `Repositories/`. Isole ce qui est utilisé par plusieurs espaces ou qui est conceptuellement transverse :

- `Services/Shared/Fiscal/` — moteur de règles fiscales (phase 10+).
- `Services/Shared/Pdf/` — rendu PDF (phase 12).
- `Services/Shared/Storage/` — abstractions filesystem.
- `Services/Shared/Cache/` — gestion des tags d'invalidation.

**Règle stricte** : ne pas créer un `Shared/` par défaut. Un service vit dans son espace tant qu'il n'est utilisé que là. Promotion dans `Shared/` seulement quand un second espace en a besoin réellement (pas par anticipation).

## Exemples concrets Floty

### Domaine `Vehicle` (phase 04)

```
app/
├── Http/
│   ├── Controllers/User/Vehicle/
│   │   └── VehicleController.php
│   └── Requests/User/Vehicle/
│       ├── StoreVehicleRequest.php
│       └── UpdateVehicleRequest.php
├── Actions/User/Vehicle/
│   ├── CreateVehicleAction.php
│   ├── UpdateVehicleAction.php
│   └── DeleteVehicleAction.php
├── Services/User/Vehicle/
│   ├── VehicleCreationService.php
│   └── VehicleFiscalCharacteristicsService.php
├── Repositories/User/Vehicle/
│   ├── VehicleListReadRepository.php
│   └── VehicleFiscalCharacteristicsWriteRepository.php
├── Contracts/Repositories/User/Vehicle/
│   ├── VehicleListReadRepositoryInterface.php
│   └── VehicleFiscalCharacteristicsWriteRepositoryInterface.php
├── Data/User/Vehicle/
│   ├── VehicleData.php
│   ├── VehicleListItemData.php
│   ├── VehicleFormData.php
│   └── VehicleStoreData.php
├── Enums/Vehicle/
│   ├── VehicleUserType.php
│   ├── EnergySource.php
│   ├── HomologationMethod.php
│   └── ...
├── Exceptions/Vehicle/
│   ├── VehicleNotFoundException.php
│   └── VehicleFiscalCharacteristicsValidationException.php
├── Models/
│   ├── Vehicle.php
│   └── VehicleFiscalCharacteristics.php
└── Policies/
    └── VehiclePolicy.php
```

### Domaine transverse `Fiscal` (phase 10)

```
app/
├── Services/Shared/Fiscal/
│   ├── RulesEngine.php
│   ├── Pipeline.php
│   └── Rules/2024/
│       ├── WltpTariff2024Rule.php
│       ├── NedcTariff2024Rule.php
│       └── ...
├── Enums/Fiscal/
│   ├── RuleType.php
│   └── TaxType.php
└── Data/Shared/Fiscal/
    └── RuleContextData.php
```

## Création des dossiers

**Règle** : création **juste-à-temps**. On ne crée pas un dossier tant qu'on n'y dépose pas de fichier. Les dossiers vides avec `.gitkeep` ne sont pas souhaités — ils encombrent l'arborescence et suggèrent de l'intention sans substance.

Les rares dossiers créés en phase 01 :

- `app/Enums/Fiscal/` — pour `RuleType`, `TaxType` (tâche 01.05)
- `app/Exceptions/` — pour `BaseAppException` (tâche 01.02)
- `app/Services/Shared/Cache/` — pour `CacheTagsManager` (tâche 01.10)

Tous les autres apparaissent au fil des phases 03-13 lorsque les domaines concernés sont implémentés.

## Conventions croisées

- **Nommage** : cf. `implementation-rules/conventions-nommage.md` (PascalCase classes, camelCase méthodes, snake_case BDD).
- **Tests** : `tests/Feature/{Espace}/{Domaine}/` pour les tests intégrés, `tests/Unit/{Espace}/{Domaine}/` pour les tests unitaires. Miroir strict de `app/`.
- **Types TS générés** : `resources/js/types/generated.d.ts` (auto, cf. Spatie TS Transformer).
