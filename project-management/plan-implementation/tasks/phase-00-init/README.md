# Phase 00 — Init (installation Laravel + tooling + CI/CD)

## Objectif de la phase

Mettre en place l'environnement de développement et les bases techniques du projet Laravel Floty V1 : installation Laravel 13 avec Vue Starter Kit Inertia, configuration du tooling (Spatie Data + TypeScript Transformer, Vitest, Wayfinder, Pint, Pail, Boost), configuration de Git, pipeline CI/CD minimal.

## Dépendances

Aucune (c'est la première phase).

## Tâches

| N° | Tâche | Statut |
|---|---|---|
| 00.01 | Installer Laravel 13 + Vue Starter Kit via `laravel new` | ✅ Terminé |
| 00.02 | Configurer environnement local (Herd PHP 8.5, MySQL, .env) | ✅ Terminé |
| 00.03 | Vérifier `npm install` + `npm run build` fonctionnels | ✅ Terminé (Node 22 LTS via Herd) |
| 00.04 | [Nettoyer le starter kit (retirer éléments non utiles V1)](01-cleanup-starter-kit.md) | À faire |
| 00.05 | [Installer Spatie Laravel Data + TypeScript Transformer](02-install-spatie-data.md) | À faire |
| 00.06 | [Configurer Vitest + Vue Test Utils + Testing Library](03-install-vitest.md) | À faire |
| 00.07 | [Vérifier config Wayfinder (déjà installé par starter)](04-verify-wayfinder.md) | À faire |
| 00.08 | [Configurer Laravel Pint (pint.json)](05-configure-pint.md) | À faire |
| 00.09 | [Configurer TypeScript strict (tsconfig.json)](06-configure-typescript-strict.md) | À faire |
| 00.10 | [Configurer ESLint + Prettier](07-configure-eslint-prettier.md) | À faire |
| 00.11 | [Mettre en place pre-commit hooks](08-pre-commit-hooks.md) | À faire |
| 00.12 | [Créer pipeline GitHub Actions (lint + tests + build)](09-ci-github-actions.md) | À faire |
| 00.13 | [Configurer déploiement Hostinger (SSH + git pull + commandes artisan)](10-deploy-hostinger.md) | À faire (déploiement effectif en phase 13) |
| 00.14 | [Vérification initiale et récap](11-initial-check.md) | À faire (clôture phase) |

## Critère de complétion de la phase

- `php artisan serve` démarre sans erreur et sert la page d'accueil.
- `npm run build` compile sans erreur.
- `php artisan typescript:transform` génère des types TS (même vide au début).
- `php artisan test` passe (pas encore de tests métier mais les tests du starter passent).
- `npm run test` passe (Vitest configuré même sans tests).
- Pre-commit hook lance Pint + ESLint.
- Le repo Git est initialisé, premier commit propre.

## Documents liés

- [`docs/starter-kit-cleanup.md`](../../docs/starter-kit-cleanup.md) — quoi garder, quoi supprimer du starter Vue.
- [`docs/spatie-data-configuration.md`](../../docs/spatie-data-configuration.md) — config Floty de Spatie Data + TS Transformer.
- [`docs/vitest-configuration.md`](../../docs/vitest-configuration.md) — config Vitest Floty.

## Références

- ADR-0008 — Stack technique V1
- `implementation-rules/architecture-solid.md`
- `implementation-rules/typescript-dto.md`
- `implementation-rules/tests-frontend.md`
- `implementation-rules/assets-vite.md`
