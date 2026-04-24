# Phase 01 — Fondations backend

## Objectif de la phase

Poser les **fondations structurelles** du backend Laravel Floty : arborescence des dossiers `app/` selon `architecture-solid.md`, classes de base (`BaseAppException`), `RepositoryServiceProvider` pour le binding des interfaces, conventions Eloquent (UUID/ID, timestamps, soft deletes).

## Dépendances

Phase 00 terminée.

## Tâches

| N° | Tâche | Statut |
|---|---|---|
| 01.01 | [Créer l'arborescence `app/` (Actions, Services, Repositories, Data, Contracts, Enums, Exceptions, Policies)](01-create-app-structure.md) | À faire |
| 01.02 | [Créer `app/Exceptions/BaseAppException.php` (classe abstraite racine)](02-base-app-exception.md) | À faire |
| 01.03 | [Créer `app/Providers/RepositoryServiceProvider.php` + enregistrement](03-repository-service-provider.md) | À faire |
| 01.04 | [Configurer les conventions Eloquent par défaut (timestamps UTC, strict mode)](04-eloquent-conventions.md) | À faire |
| 01.05 | [Créer les Enums transverses (`RuleType`, `TaxType`)](05-transverse-enums.md) | À faire |
| 01.06 | [Définir la segmentation routes (`routes/web.php`, `auth.php`, `user.php`) + middleware groups](06-routes-segmentation.md) | À faire |
| 01.07 | [Créer `app/Http/Middleware/HandleInertiaRequests.php` — shared props Floty (flash + auth.user + appName)](07-handle-inertia-requests.md) | À faire |
| 01.08 | [Créer `resources/js/types/inertia.d.ts` (déclare PageProps Floty)](08-inertia-types.md) | À faire |

## Critère de complétion de la phase

- L'arborescence `app/` est créée et conforme à `architecture-solid.md`.
- `BaseAppException` disponible pour les domaines suivants.
- `RepositoryServiceProvider` enregistré dans `bootstrap/providers.php`.
- Routes segmentées : `/` (Web), `/login` (Auth), `/app/*` (User) — squelettes vides mais groupe de middleware corrects.
- `Inertia::render` d'une page vide de test fonctionne avec flash messages et auth.user typés côté TS.

## Documents liés

- [`docs/app-structure.md`](../../docs/app-structure.md) — description précise de l'arborescence Floty.
- [`docs/base-app-exception.md`](../../docs/base-app-exception.md) — classe exception root.
- [`docs/routes-segmentation.md`](../../docs/routes-segmentation.md) — fichiers routes + middleware groupes.

## Références

- `implementation-rules/architecture-solid.md`
- `implementation-rules/structure-fichiers.md`
- `implementation-rules/gestion-erreurs.md`
- `implementation-rules/conventions-nommage.md`
