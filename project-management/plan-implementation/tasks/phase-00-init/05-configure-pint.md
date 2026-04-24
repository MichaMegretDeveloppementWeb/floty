# Task 00.08 — Configurer Laravel Pint (formatter PHP)

> **Phase** : 00 — Init
> **Statut** : à faire
> **Dépendances** : 00.04
> **Estimation** : 20 min

---

## Objectif

Configurer Pint pour formater le code PHP selon les conventions Floty : PSR-12 + customs alignées sur nos règles (`conventions-nommage.md`, `architecture-solid.md`).

## Méthode

1. Pint est déjà installé via le starter. Vérifier : `composer show laravel/pint`.
2. Créer `pint.json` à la racine (déjà présent peut-être — vérifier) avec une config Floty :
   ```json
   {
     "preset": "laravel",
     "rules": {
       "declare_strict_types": true,
       "final_class": false,
       "final_internal_class": true,
       "ordered_imports": { "sort_algorithm": "alpha" },
       "trailing_comma_in_multiline": { "elements": ["arrays", "arguments", "parameters"] }
     }
   }
   ```
   *Config à affiner selon revue réelle.*
3. Lancer `vendor/bin/pint` → formate tout le code PHP du projet.
4. Lancer `vendor/bin/pint --test` → dry-run, retourne exit 1 si violations.
5. Ajouter `composer.json` → scripts :
   ```json
   "scripts": {
     "format": "vendor/bin/pint",
     "format:check": "vendor/bin/pint --test"
   }
   ```
6. Commit (avec un message clair : `chore: configure laravel pint`).

## Critères de validation

- [ ] `pint.json` existe et reflète les conventions Floty.
- [ ] `vendor/bin/pint --test` passe sans violations après formatage initial.
- [ ] `composer run format:check` fonctionne.

## Pièges identifiés

- **`declare_strict_types`** : on l'active globalement. Si l'ajout crée des bugs sur des fichiers legacy du starter, on règle au cas par cas.
- **`final_class` false** : ne **pas** forcer `final` sur toutes les classes via Pint (on décide quelles classes sont `final readonly` ou pas via les règles d'archi).

## Références

- ADR-0008 (Pint dans la stack)
- `implementation-rules/conventions-nommage.md`
