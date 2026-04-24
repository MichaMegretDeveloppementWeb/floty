# Task 00.11 — Pre-commit hooks (format + lint + type-check)

> **Phase** : 00 — Init
> **Statut** : à faire
> **Dépendances** : 00.08, 00.09, 00.10
> **Estimation** : 30 min

---

## Objectif

Empêcher l'arrivée de code non formatée / avec erreurs type dans le dépôt Git, via un pre-commit hook léger.

## Méthode

Options possibles — choix Floty : **`husky` + `lint-staged`** (standard JS, connu, léger).

1. Installer :
   ```bash
   npm install -D husky lint-staged
   npx husky init
   ```
2. `.husky/pre-commit` contient :
   ```bash
   #!/usr/bin/env sh
   npx lint-staged
   ```
3. Ajouter dans `package.json` :
   ```json
   "lint-staged": {
     "*.{ts,vue}": [
       "eslint --fix",
       "prettier --write"
     ],
     "*.php": [
       "vendor/bin/pint"
     ]
   }
   ```
4. Commit.
5. Tester : modifier un fichier `.ts` et `.php`, `git add`, `git commit` → doit lancer ESLint + Pint automatiquement.

## Critères de validation

- [ ] `husky` installé (`node_modules/husky` présent).
- [ ] `.husky/pre-commit` exécute `lint-staged`.
- [ ] Un commit avec fichier mal formaté est auto-formaté avant d'être enregistré.
- [ ] Un commit avec erreur TS stricte reste bloqué.

## Pièges identifiés

- **Windows / WSL / Git Bash** : husky peut avoir des soucis avec les fins de ligne ou les permissions. Si blocage : skip husky temporairement, utiliser juste le CI pour vérifier.
- **Hooks ne doivent pas lancer Vitest** (trop long). On réserve Vitest pour le CI.
- **TypeScript type-check** : trop lourd en pre-commit (vue-tsc prend 5-10s). On le laisse au CI et au build. Pas en pre-commit.

## Références

- Standard `husky` + `lint-staged`
