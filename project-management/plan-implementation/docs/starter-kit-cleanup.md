# Fiche — Nettoyage du starter kit Vue Laravel

> **Tâche associée** : `tasks/phase-00-init/01-cleanup-starter-kit.md`
> **Objet** : lister précisément ce qu'on garde et ce qu'on supprime du starter kit Vue installé par `laravel new --vue`.

---

## Contexte

Le starter kit Laravel Vue (v1.x) installe par défaut un scaffold auth/settings complet avec shadcn-vue, UI components, dashboards, pages de paramètres utilisateur, etc. Pour Floty V1 (ADR-0008 : pas de starter kit pour l'auth, UI Kit custom), on repart d'une base épurée pour ne pas trainer de code mort ni de conventions qui ne sont pas les nôtres.

## Liste exhaustive à supprimer

### Backend

- **Controllers Auth du starter** : tout dans `app/Http/Controllers/Auth/`.
- **Controllers Settings** : tout dans `app/Http/Controllers/Settings/`.
- **Middleware** Starter spécifique (si existant, ex: `HandleAppearance.php`). Garder `HandleInertiaRequests.php`.
- **Requests** : `app/Http/Requests/Settings/*`, `app/Http/Requests/Auth/*`.
- **Migrations starter** non utiles V1 : `add_two_factor_columns_to_users_table` si présente (on ne fait pas 2FA V1).
- **Factories / Seeders** du starter si personnalisés (on gardera `DatabaseSeeder.php`).
- **Tests Pest** générés : on utilise PHPUnit (cf. ADR-0008). Supprimer tout `.php` dans `tests/Feature/Auth/`, `tests/Feature/Settings/`.

### Frontend (`resources/js/`)

- **Pages starter** :
  - `pages/auth/*` (Login, Register, ForgotPassword, ResetPassword, VerifyEmail, ConfirmPassword)
  - `pages/settings/*` (Profile, Password, Appearance)
  - `pages/Dashboard.vue` (on refera le nôtre en phase 13)
  - `pages/Welcome.vue` (on refera en phase 13)
- **Layouts starter** :
  - `layouts/AppLayout.vue`, `layouts/AuthLayout.vue`, `layouts/GuestLayout.vue`
  - Tout ce qui est dans `layouts/app/`, `layouts/auth/`
- **Composants starter** :
  - `components/NavMain.vue`, `NavUser.vue`, `NavFooter.vue`, `NavDropdown.vue`
  - `components/AppHeader.vue`, `AppSidebar.vue`, `AppSidebarHeader.vue`, `AppContent.vue`, `AppShell.vue`
  - `components/Breadcrumbs.vue`
  - `components/UserInfo.vue`, `UserMenuContent.vue`
  - `components/AppearanceTabs.vue`, `ApplicationLogo.vue`, `AppLogoIcon.vue`
  - `components/InputError.vue` (on refera le nôtre custom)
  - Tout `components/ui/` (shadcn-vue copié — on refait nous-mêmes en phase 02)
- **Composables starter** : `composables/useAppearance.ts`, `composables/useInitials.ts`, etc.
- **Types starter** : vérifier `types/index.d.ts` — garder ce qui est utile (ex: types Inertia de base), supprimer le reste.

### Routes

- **Vider** `routes/web.php`, `routes/auth.php`, `routes/settings.php`.
  - Garder le **squelette** (imports, closures vides).
  - Laisser les fichiers présents pour les restructurer en phase 01 selon nos conventions (`routes/web.php` pour public, `routes/auth.php` pour actions login/logout, `routes/user.php` pour zone connectée).

## Liste à garder

- `app/Http/Middleware/HandleInertiaRequests.php` (à adapter en phase 01 pour Floty shared props).
- `app/Providers/AppServiceProvider.php`, `VoltServiceProvider.php` (si présent), `RouteServiceProvider.php`.
- `resources/js/app.ts` (entry point Inertia).
- `resources/css/app.css` (sera réécrit en phase 02 avec tokens Floty).
- `vite.config.ts` (contient déjà Wayfinder + Tailwind plugin — OK).
- `tsconfig.json` (sera durci en 00.09).
- `eslint.config.js`, `.prettierrc` (seront affinés en 00.10).
- `composer.json` et toutes ses dépendances (Pint, Pail, Boost, Wayfinder, Inertia, Spatie TS Transformer à ajouter en 00.05).
- `package.json` et ses dépendances (Vue 3, Vite 8, Tailwind 4, Inertia, etc.).
- `.env.example`, `.env`.

## Procédure rapide

```bash
# Pages Vue starter
rm -rf resources/js/pages/auth
rm -rf resources/js/pages/settings
rm -f resources/js/pages/Dashboard.vue
rm -f resources/js/pages/Welcome.vue

# Layouts + composants starter
rm -rf resources/js/layouts
rm -rf resources/js/components/ui
rm -f resources/js/components/NavMain.vue resources/js/components/NavUser.vue resources/js/components/NavFooter.vue
# ... (liste complète à vérifier au cas par cas)

# Controllers starter
rm -rf app/Http/Controllers/Auth
rm -rf app/Http/Controllers/Settings
rm -rf app/Http/Requests/Auth
rm -rf app/Http/Requests/Settings

# Tests Pest générés
rm -rf tests/Feature/Auth
rm -rf tests/Feature/Settings
rm -f tests/Pest.php  # si on préfère PHPUnit pur
```

Puis éditer manuellement `routes/web.php` et `routes/auth.php` pour ne garder que les closures vides.

## Validation

- `php artisan serve` démarre sans erreur (page d'accueil en 404 acceptable, on refera en phase 13).
- `npm run build` passe (bundle quasi vide).
- `php artisan route:list` ne montre plus aucune route Auth/Settings du starter.
