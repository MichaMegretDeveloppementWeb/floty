# Phase 03 — Authentification custom

> **Statut** : ✅ Terminée le 28/04/2026
> **Commits** : `bf44eb3` (refonte ADR-0013) + `ef8e75e` (CreateVehicleAction extraction) + `6901a29` (chaîne stricte O1/O2/O3)
> **Couverture tests** : 165/165 PHP, 21/21 Vitest

## Objectif de la phase

Implémenter l'authentification Floty V1 **sans starter kit auth** (cf. ADR-0008 : pas de Breeze, pas de Jetstream). Login simple email + mot de passe, pas de rôles V1, pas de reset libre-service, création de comptes uniquement par seeders.

## Dépendances

Phase 01 + 02 terminées.

## Tâches

| N° | Tâche | Statut |
|---|---|---|
| 03.01 | Migration `users` (email, password, first_name, last_name, email_verified_at, remember_token, created_at/updated_at/deleted_at + extras `must_change_password`, `last_login_at`) | ✅ Phase 1.bis |
| 03.02 | Model `User` (SoftDeletes, hidden password, accessor `fullName`) | ✅ Phase 1.bis |
| 03.03 | DTO `CurrentUserData` (Spatie Data, id + firstName + lastName + fullName + email) | ✅ Phase 1.2 |
| 03.04 | FormRequest `LoginRequest` (slim — validation email/password uniquement) | ✅ 03.bis |
| 03.05 | Action `LoginAction` (rate-limit → Auth::attempt → trace last_login_at, throw `InvalidCredentialsException` ou `TooManyLoginAttemptsException`) | ✅ 03.bis |
| 03.06 | Service `LoginAttemptService` (double rate-limit ADR-0011, 5/email+IP/15min, 50/IP/15min) | ✅ 03.bis |
| 03.07 | Exception `InvalidCredentialsException` + `TooManyLoginAttemptsException` (typées `BaseAppException`, factories statiques, messages français) | ✅ 03.bis |
| 03.08 | Controller `Auth/LoginController` (show, store, destroy) — DI Action + try/catch en `ValidationException` pour UX field-level | ✅ 03.bis |
| 03.09 | Page Inertia `Pages/Auth/Login/Index.vue` + `Partials/LoginForm.vue` | ✅ 03.bis |
| 03.10 | Routes `routes/auth.php` (GET /login, POST /login, POST /logout) | ✅ MVP démo |
| 03.11 | Middleware groups (auth/guest correctement séparés) + redirect post-login `/app/dashboard` | ✅ MVP démo |
| 03.12 | Seeder `UserSeeder` (admin@floty.test pour tests/démo) | ✅ MVP démo |
| 03.13 | Tests Feature (LoginFlowTest 7 tests : login OK, login KO, rate-limit email, rate-limit IP, logout, middleware guest, last_login_at) + Unit (LoginAttemptServiceTest 4 tests + LoginActionTest 6 tests) | ✅ 03.bis |

## Critère de complétion de la phase

- Accès à `/login` affiche la page de connexion.
- Connexion avec `DemoUserSeeder` crée une session valide et redirige vers `/app/dashboard` (page vide pour l'instant).
- Connexion échouée retourne l'erreur validation sur le champ.
- Rate limiting fonctionne (5 tentatives max par IP/email / 1 min).
- `/logout` détruit la session et redirige vers `/`.
- `usePage().props.auth.user` retourne le `CurrentUserData` typé côté Vue.
- Tests feature auth tous verts.

## Documents liés

- [`docs/auth-flow.md`](../../docs/auth-flow.md) — flux d'authentification Floty (login/logout, rate limit, middleware).
- [`docs/login-page.md`](../../docs/login-page.md) — UX de la page Login (formulaire, erreurs, lien mentions légales).

## Références

- `implementation-rules/architecture-solid.md` (4-layer)
- `implementation-rules/gestion-erreurs.md` (exceptions + FormRequest)
- `implementation-rules/inertia-navigation.md` (useForm + routes Wayfinder)
- `modele-de-donnees/01-schema-metier.md` § 1 (table `users`)
- ADR-0007 (pas de rôles V1)
