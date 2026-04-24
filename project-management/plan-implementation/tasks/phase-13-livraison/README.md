# Phase 13 — Finitions et livraison

## Objectif de la phase

**Finaliser Floty V1** pour la validation client : pages transversales manquantes (Dashboard, recherche globale, pages publiques, pages d'erreur), audit qualité (Lighthouse, tests complets), déploiement effectif sur Hostinger Business.

## Dépendances

Phases 00 à 12 terminées.

## Tâches

### 13.A — Pages transversales manquantes

| N° | Tâche | Statut |
|---|---|---|
| 13.01 | [Page `Pages/User/Dashboard/Dashboard.vue` + Partials (KpiCards, TaxesEstimateChart, RecentActivity)](01-page-dashboard.md) | À faire |
| 13.02 | [Barre de recherche globale dans TopBar (recherche simple : vehicles par immatriculation, companies par raison_sociale, drivers par nom)](02-global-search-bar.md) | À faire |
| 13.03 | [Page `Pages/Web/Home/Home.vue` (accueil public minimal)](03-page-home.md) | À faire |
| 13.04 | [Page `Pages/Web/MentionsLegales/MentionsLegales.vue` (trame CNIL complétée par les infos client)](04-page-mentions-legales.md) | À faire |
| 13.05 | [Pages d'erreur Inertia : `Pages/Errors/Error.vue` (404, 500, 503)](05-pages-errors.md) | À faire |
| 13.06 | [Page « Corbeille » (éléments soft-deleted avec bouton Restaurer — cf. 03-strategie-suppression.md § 4)](06-page-trash.md) | À faire |

### 13.B — Config transverse

| N° | Tâche | Statut |
|---|---|---|
| 13.07 | [Gestion 419 CSRF (handler `bootstrap/app.php` qui redirige avec toast-warning — cf. gestion-erreurs.md)](07-csrf-419-handler.md) | À faire |
| 13.08 | [Canaux de log thématiques Floty (config/logging.php : auth, vehicles, assignments, fiscal, declarations, pdf, cache)](08-log-channels.md) | À faire |
| 13.09 | [Configuration cache driver `database` + tags émulés](09-cache-driver-database.md) | À faire |

### 13.C — Audit qualité

| N° | Tâche | Statut |
|---|---|---|
| 13.10 | [Audit Lighthouse sur toutes les pages principales (cibles : FCP < 1.5s, LCP < 2.5s, CLS < 0.1)](10-lighthouse-audit.md) | À faire |
| 13.11 | [Audit accessibilité (navigation clavier, lecteurs d'écran, ARIA, contrastes)](11-a11y-audit.md) | À faire |
| 13.12 | [Audit performance heatmap (Vue DevTools profiler, temps de render < 200ms avec 100 véhicules)](12-perf-audit-heatmap.md) | À faire |
| 13.13 | [Run complet tests back (`php artisan test`) + front (`npm run test`) + coverage](13-tests-complets.md) | À faire |

### 13.D — Déploiement Hostinger

| N° | Tâche | Statut |
|---|---|---|
| 13.14 | [Préparer `.env.production` (APP_URL, DB credentials Hostinger, APP_KEY, etc.)](14-env-production.md) | À faire |
| 13.15 | [Configurer CI GitHub Actions pour build auto + push vers Hostinger via SSH](15-ci-cd-deploy.md) | À faire |
| 13.16 | [Migrer la BDD production + seeders fiscaux 2024](16-deploy-migrations-seeders.md) | À faire |
| 13.17 | [Créer user admin initial en production (via Artisan command protégée)](17-create-admin-user-prod.md) | À faire |
| 13.18 | [Smoke test production : login, créer véhicule, attribuer, générer PDF](18-smoke-test-prod.md) | À faire |

### 13.E — Livraison client

| N° | Tâche | Statut |
|---|---|---|
| 13.19 | [Documentation utilisateur V1 (guide court : comment créer une company, un véhicule, attribuer, générer une déclaration)](19-user-documentation.md) | À faire |
| 13.20 | [Livraison client : présentation, formation courte, retour des crédentials admin](20-client-delivery.md) | À faire |
| 13.21 | [Checklist finale validation client](21-final-checklist.md) | À faire |

## Critère de complétion (= V1 livrée)

- Toutes les pages principales existent et fonctionnent.
- Lighthouse vert sur les pages critiques.
- Tous les tests passent en CI.
- Déploiement effectif sur Hostinger avec smoke test validé.
- Client a accès à l'application et peut la tester de bout en bout.
- Documentation utilisateur remise.
- Checklist finale cochée et signée.

## Documents liés

- [`docs/deployment-hostinger-procedure.md`](../../docs/deployment-hostinger-procedure.md) — procédure exacte de déploiement (SSH, composer2, artisan commands).
- [`docs/production-env-checklist.md`](../../docs/production-env-checklist.md) — check des variables `.env` production critiques.
- [`docs/v1-user-quickstart.md`](../../docs/v1-user-quickstart.md) — guide utilisateur court à remettre au client.
- [`docs/v1-delivery-checklist.md`](../../docs/v1-delivery-checklist.md) — checklist finale complète.

## Références

- ADR-0007 (périmètre V1)
- ADR-0008 (stack + déploiement Hostinger)
- `implementation-rules/performance-ui.md` (métriques cibles Lighthouse)
- `implementation-rules/gestion-erreurs.md` (419, canaux logs, pages erreur)
- CDC § 3.2 (dashboard)
