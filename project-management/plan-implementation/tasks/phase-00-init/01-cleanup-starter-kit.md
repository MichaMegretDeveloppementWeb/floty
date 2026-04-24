# Task 00.04 — Nettoyer le starter kit Vue Laravel

> **Phase** : 00 — Init
> **Statut** : à faire
> **Dépendances** : 00.01 (Laravel installé), 00.02 (env local), 00.03 (build OK)
> **Estimation** : 1h
> **Fiche projet** : [`docs/starter-kit-cleanup.md`](../../docs/starter-kit-cleanup.md)
> **Références règles** : `architecture-solid.md`, `structure-fichiers.md`, `assets-vite.md`

---

## Objectif

Le starter kit Laravel Vue installé en 00.01 contient des éléments génériques qui ne correspondent pas à Floty (auth Breeze-like, settings utilisateur, page Welcome standard, components shadcn-vue). On les retire pour repartir sur une base propre alignée avec nos conventions.

## Périmètre

**On garde** :

- `app/Http/Middleware/HandleInertiaRequests.php` (à adapter en phase 01)
- `resources/js/app.ts` (entry point — à customiser)
- `resources/css/app.css` (entry CSS — sera réécrit en phase 02)
- `vite.config.ts` (à compléter pour Wayfinder + Tailwind plugin déjà OK)
- `tsconfig.json` (à durcir en 00.09)
- `routes/web.php` (mais on va vider et restructurer en phase 01)

**On supprime / vide** :

- Toutes les pages Vue du starter (`resources/js/pages/auth/*`, `settings/*`, `Dashboard.vue`, `Welcome.vue`)
- Tous les composants UI shadcn-vue copiés (`resources/js/components/ui/*`)
- Composants spécifiques starter (`AppLogo*`, `NavMain.vue`, `UserInfo.vue`, etc.)
- Layouts Inertia générés par le starter (à recréer en phase 02 selon nos conventions)
- Les contrôleurs Auth/Settings du starter
- Migrations starter non utiles V1 (`add_two_factor_columns_to_users_table` si présente)
- Tests Pest générés par le starter (on utilise PHPUnit cf. ADR-0008)

## Méthode

1. **Lister** tout ce que le starter a généré dans `resources/js/`, `app/Http/Controllers/`, `routes/`, `tests/`.
2. **Supprimer** les pages Vue, layouts, composants shadcn-vue.
3. **Supprimer** les contrôleurs Auth/Settings du starter (on refera notre propre auth en phase 03).
4. **Vider** les routes (`routes/web.php`, `routes/auth.php`, `routes/settings.php`) — on garde les fichiers vides, on les remplira en phase 01.
5. **Supprimer** les tests générés (Pest) — on créera notre suite en PHPUnit.
6. **Vérifier** que `php artisan serve` ne lève pas d'erreur 500 (route `/` peut renvoyer 404 c'est OK).
7. **Vérifier** que `npm run build` passe encore (le bundle sera quasi vide, c'est attendu).
8. **Commit** : `chore: clean starter kit before custom Floty implementation`.

## Critères de validation

- [ ] Aucun fichier shadcn-vue restant dans `resources/js/components/`.
- [ ] Aucune page Vue starter dans `resources/js/pages/`.
- [ ] Aucun controller Auth/Settings du starter dans `app/Http/Controllers/`.
- [ ] Routes vidées (`routes/web.php` minimal).
- [ ] `php artisan route:list` ne montre plus de routes du starter.
- [ ] Les tests Pest sont supprimés.
- [ ] `php artisan serve` démarre sans erreur fatale.
- [ ] `npm run build` passe.
- [ ] Commit propre poussé.

## Pièges identifiés

- **Pas tout supprimer** : `app/Http/Middleware/HandleInertiaRequests.php` est utile (à customiser en phase 01).
- **`vite.config.ts`** : ne pas casser la config Wayfinder + Tailwind plugin déjà installés.
- **Tests directories** : conserver `tests/Feature/` et `tests/Unit/` même vides (Laravel les attend).
- **`composer.json`** : ne pas retirer les dépendances dev `laravel/pint`, `laravel/pail`, `laravel/sail` (Sail non utilisé mais inoffensif).

## Références

- `implementation-rules/structure-fichiers.md`
- ADR-0008 (pas de starter kit pour l'auth — on refait custom)
- `docs/starter-kit-cleanup.md` pour la liste exhaustive
