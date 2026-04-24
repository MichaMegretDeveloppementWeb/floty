# Phase 03 — Authentification custom

## Objectif de la phase

Implémenter l'authentification Floty V1 **sans starter kit auth** (cf. ADR-0008 : pas de Breeze, pas de Jetstream). Login simple email + mot de passe, pas de rôles V1, pas de reset libre-service, création de comptes uniquement par seeders.

## Dépendances

Phase 01 + 02 terminées.

## Tâches

| N° | Tâche | Statut |
|---|---|---|
| 03.01 | [Migration `users` (email, password, first_name, last_name, email_verified_at, remember_token, created_at/updated_at/deleted_at)](01-migration-users.md) | À faire |
| 03.02 | [Model `User` (HasApiTokens non nécessaire V1, SoftDeletes, hidden password)](02-user-model.md) | À faire |
| 03.03 | [DTO `CurrentUserData` (Spatie Data, id + first_name + last_name + email)](03-current-user-data.md) | À faire |
| 03.04 | [FormRequest `LoginRequest` (validation email/password)](04-login-request.md) | À faire |
| 03.05 | [Action `LoginAction` (tentative auth, rate limit, throw `InvalidCredentialsException`)](05-login-action.md) | À faire |
| 03.06 | [Service `LoginAttemptService` (gestion des tentatives + rate limiting)](06-login-attempt-service.md) | À faire |
| 03.07 | [Exception `InvalidCredentialsException` (message français)](07-invalid-credentials-exception.md) | À faire |
| 03.08 | [Controller `Web/Auth/LoginController` (show, store, destroy)](08-login-controller.md) | À faire |
| 03.09 | [Page Inertia `Pages/Web/Auth/Login/Login.vue` + Partials (formulaire)](09-login-page.md) | À faire |
| 03.10 | [Routes `routes/auth.php` (GET /login, POST /login, POST /logout)](10-auth-routes.md) | À faire |
| 03.11 | [Middleware groups + redirection par défaut post-login (/app/dashboard)](11-auth-middleware.md) | À faire |
| 03.12 | [Seeder `DemoUserSeeder` (crée un user admin pour tester)](12-demo-user-seeder.md) | À faire |
| 03.13 | [Tests Feature (login réussi, login échoué, logout, redirection)](13-auth-tests.md) | À faire |

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
