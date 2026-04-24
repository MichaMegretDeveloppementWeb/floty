# Task 00.07 — Vérifier la configuration Wayfinder

> **Phase** : 00 — Init
> **Statut** : à faire
> **Dépendances** : 00.04
> **Estimation** : 20 min
> **Références règles** : `inertia-navigation.md` § « Laravel Wayfinder »

---

## Objectif

Wayfinder est installé par défaut par le starter kit Vue Laravel 13. Cette tâche **vérifie** que la configuration est correcte pour Floty et génère les premiers types (même vides).

## Méthode

1. Vérifier que `@laravel/vite-plugin-wayfinder` est dans `package.json` (devDependencies).
2. Vérifier que `vite.config.ts` inclut bien :
   ```ts
   import { wayfinder } from '@laravel/vite-plugin-wayfinder'
   // ...
   plugins: [
     // ...
     wayfinder(),
   ]
   ```
3. Vérifier que `composer.json` contient `laravel/wayfinder`.
4. Lancer `php artisan wayfinder:generate` manuellement — doit créer les dossiers :
   - `resources/js/actions/App/Http/Controllers/...`
   - `resources/js/routes/...`
   - `resources/js/wayfinder/`
5. Vérifier que ces dossiers sont **exclus** de `.gitignore` (ils sont auto-générés mais on les commit pour avoir CI sans PHP côté front).
6. **Alternative** : si on préfère ne pas les commiter, il faut que CI lance `php artisan wayfinder:generate` avant le build. **Décision Floty : on les commit** (cohérent avec ce qu'on fait pour `generated.d.ts`).
7. Commit.

## Critères de validation

- [ ] `npm run build` affiche dans les logs : `[plugin @laravel/vite-plugin-wayfinder] Types generated for actions, routes, form variants`.
- [ ] `resources/js/actions/`, `resources/js/routes/`, `resources/js/wayfinder/` existent.
- [ ] Ces dossiers sont bien commités (pas dans `.gitignore`).

## Pièges identifiés

- **Pas de Ziggy** : Floty n'a pas besoin de Ziggy. Si `tightenco/ziggy` apparaît dans `composer.json` (inattendu), on peut le retirer — cf. ADR-0008.
- **Régénération auto en dev** : le plugin `wayfinder()` Vite régénère à chaque changement de route. Pas besoin de lancer manuellement.

## Références

- `implementation-rules/inertia-navigation.md` § « Laravel Wayfinder »
- ADR-0008 § « Wayfinder »
- Skill `wayfinder-development`
