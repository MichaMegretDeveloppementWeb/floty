# Task 00.05 — Installer Spatie Laravel Data + TypeScript Transformer

> **Phase** : 00 — Init
> **Statut** : à faire
> **Dépendances** : 00.04
> **Estimation** : 1h
> **Fiche projet** : [`docs/spatie-data-configuration.md`](../../docs/spatie-data-configuration.md)
> **Références règles** : `typescript-dto.md` (doc principal), `architecture-solid.md` § 7

---

## Objectif

Mettre en place la chaîne complète **Spatie Laravel Data** (DTO PHP) + **Spatie TypeScript Transformer** (génération auto des types TS) qui garantit la **type-safety end-to-end** PHP ↔ TypeScript pour Floty.

## Périmètre

- Installation des deux packages Spatie via Composer.
- Configuration de `config/typescript-transformer.php` pour scanner `app/Data/` et `app/Enums/`.
- Sortie : `resources/js/types/generated.d.ts`.
- Création du fichier de ré-export `resources/js/types/index.ts` pour usage simple.
- Génération initiale (vide pour l'instant — on n'a pas encore de Data).
- Intégration dans `package.json` scripts (`build`, `dev` → régénèrent avant de tourner).

## Méthode

1. Installer :
   ```bash
   composer require spatie/laravel-data
   composer require --dev spatie/typescript-transformer
   composer require --dev spatie/laravel-typescript-transformer
   ```
2. Publier la config Laravel Data :
   ```bash
   php artisan vendor:publish --provider="Spatie\LaravelData\LaravelDataServiceProvider" --tag="data-config"
   ```
3. Publier la config TypeScript Transformer :
   ```bash
   php artisan vendor:publish --tag="typescript-transformer-config"
   ```
4. Modifier `config/typescript-transformer.php` selon `docs/spatie-data-configuration.md` :
   - `auto_discover_types` : `[app_path('Data'), app_path('Enums')]`
   - `output_file` : `resource_path('js/types/generated.d.ts')`
   - Collecteurs et transformers Spatie Data + Enum.
5. Créer le dossier `app/Data/` avec un fichier `.gitkeep`.
6. Créer `resources/js/types/index.ts` (pour l'instant vide, prêt pour ré-exports).
7. Créer `resources/js/types/inertia.d.ts` (à compléter en phase 01).
8. Lancer `php artisan typescript:transform` pour générer `generated.d.ts` (vide ou minimal).
9. Ajouter dans `package.json` :
   ```json
   "scripts": {
     "types:generate": "php artisan typescript:transform",
     "build": "npm run types:generate && vite build",
     "dev": "concurrently \"php artisan typescript:transform --watch\" \"vite\""
   }
   ```
10. Commit.

## Critères de validation

- [ ] `composer show spatie/laravel-data` confirme l'install.
- [ ] `composer show --dev spatie/typescript-transformer` confirme l'install.
- [ ] `config/typescript-transformer.php` configuré avec `output_file` pointant `resources/js/types/generated.d.ts`.
- [ ] `php artisan typescript:transform` exécute sans erreur (même si génère un fichier vide).
- [ ] `resources/js/types/generated.d.ts` existe.
- [ ] `resources/js/types/index.ts` existe.
- [ ] `npm run types:generate` fonctionne.
- [ ] `npm run build` régénère les types puis build.

## Pièges identifiés

- **Versions** : forcer `spatie/laravel-data ^4.22` et `spatie/typescript-transformer ^3.1` (cf. ADR-0008).
- **Fichier généré commité** : `generated.d.ts` est commité (pas dans `.gitignore`) — c'est volontaire (cf. `typescript-dto.md` § « Le fichier `generated.d.ts` est commité »).
- **Watcher en dev** : `--watch` peut ne pas fonctionner sur certains environnements Windows/WSL. Si problème, on relance manuellement à chaque modif Data.
- **Casse de noms** : Spatie utilise par défaut `case_attribute_name` (snake_case). Floty veut `camelCase` côté TS — vérifier que `app_naming_strategy` est bien sur `camelCase` dans la config.

## Références

- `implementation-rules/typescript-dto.md` — documentation complète
- `docs/spatie-data-configuration.md` — config exacte Floty
- ADR-0008 (Spatie Data + TS Transformer dans la stack)
