# Task 00.14 — Vérification initiale et clôture phase 00

> **Phase** : 00 — Init
> **Statut** : à faire
> **Dépendances** : toutes les tâches 00.01 à 00.13
> **Estimation** : 30 min

---

## Objectif

Vérifier que **l'ensemble de l'environnement init** est opérationnel avant d'ouvrir la phase 01.

## Checklist de sortie de phase 00

### Environnement local

- [ ] `php -v` retourne **PHP 8.5.x** (via Herd).
- [ ] `node -v` retourne **v22.x.x** (via Herd nvm, pas le MSI).
- [ ] `composer --version` fonctionne.
- [ ] `npm --version` fonctionne.
- [ ] `mysql --version` fonctionne (ou accessible via Herd).

### Projet Laravel

- [ ] `php artisan serve` démarre sans erreur.
- [ ] `php artisan tinker` s'ouvre sans erreur.
- [ ] `php artisan route:list` renvoie la liste (minimale après cleanup).
- [ ] `.env` configuré avec DB locale Herd.
- [ ] `php artisan migrate` exécute les migrations du starter (users, sessions, cache, jobs).

### Outils

- [ ] `vendor/bin/pint --test` passe (ou après un `vendor/bin/pint` initial).
- [ ] `npm run types:generate` génère `resources/js/types/generated.d.ts`.
- [ ] `npm run types:check` passe.
- [ ] `npm run lint:check` passe.
- [ ] `npm run format:check` passe.
- [ ] `npm run test:ci` passe (smoke test Vitest).
- [ ] `php artisan test --compact` passe (tests Laravel de base).
- [ ] `npm run build` passe (Wayfinder génère + Vite build).

### Git & CI

- [ ] Repo Git initialisé, distant GitHub configuré.
- [ ] `.gitignore` sain (pas de node_modules, pas de vendor, pas de .env commit).
- [ ] Workflow `.github/workflows/ci.yml` vert sur le dernier push.
- [ ] Workflow `.github/workflows/deploy.yml` présent (sans être déclenché).

### Hostinger (préparation)

- [ ] Connexion SSH Hostinger fonctionne en local.
- [ ] Clé SSH dédiée GitHub Actions configurée.
- [ ] Secrets GitHub configurés.

### Hooks

- [ ] Pre-commit hook actif : un commit avec code non formaté se fait reformater.

### Skills Claude

- [ ] Laravel Boost MCP accessible (tools `mcp__laravel-boost__*` disponibles).
- [ ] Skills `inertia-vue-development`, `laravel-best-practices`, `wayfinder-development`, `tailwindcss-development` activables.

## Action de clôture

1. Mettre à jour le statut `terminée` sur toutes les tâches de la phase 00.
2. Commit de clôture : `chore: phase 00 init — environment ready for feature development`.
3. Ouvrir la phase 01 (`tasks/phase-01-fondations-backend/README.md`).

## Références

- Toutes les tâches 00.01 à 00.13
