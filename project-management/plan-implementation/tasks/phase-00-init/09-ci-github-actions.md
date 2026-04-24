# Task 00.12 — Pipeline GitHub Actions (lint + tests + build)

> **Phase** : 00 — Init
> **Statut** : à faire
> **Dépendances** : 00.05 à 00.11 (tous les outils installés)
> **Estimation** : 1h

---

## Objectif

Mettre en place un pipeline CI automatique à chaque push/PR qui valide : lint PHP (Pint), lint JS (ESLint), format (Prettier), TypeScript (vue-tsc), tests back (PHPUnit), tests front (Vitest), build Vite.

## Méthode

1. Créer `.github/workflows/ci.yml` :
   ```yaml
   name: CI

   on:
     push:
       branches: [main, develop]
     pull_request:

   jobs:
     ci:
       runs-on: ubuntu-latest

       services:
         mysql:
           image: mysql:8
           env:
             MYSQL_ROOT_PASSWORD: root
             MYSQL_DATABASE: floty_test
           ports: ['3306:3306']
           options: >-
             --health-cmd="mysqladmin ping"
             --health-interval=10s
             --health-timeout=5s
             --health-retries=3

       steps:
         - uses: actions/checkout@v4

         - name: Setup PHP 8.5
           uses: shivammathur/setup-php@v2
           with:
             php-version: '8.5'
             extensions: mbstring, pdo_mysql, zip, gd
             coverage: none

         - name: Setup Node 22
           uses: actions/setup-node@v4
           with:
             node-version: '22'
             cache: 'npm'

         - name: Install PHP deps
           run: composer install --no-interaction --no-progress --prefer-dist

         - name: Install JS deps
           run: npm ci

         - name: Copy .env
           run: cp .env.ci .env

         - name: Generate app key
           run: php artisan key:generate

         - name: Run migrations (test DB)
           run: php artisan migrate --force
           env:
             DB_CONNECTION: mysql
             DB_HOST: 127.0.0.1
             DB_PORT: 3306
             DB_DATABASE: floty_test
             DB_USERNAME: root
             DB_PASSWORD: root

         - name: Generate types TS
           run: php artisan typescript:transform

         - name: Lint PHP (Pint)
           run: vendor/bin/pint --test

         - name: Lint JS (ESLint)
           run: npm run lint:check

         - name: Format check (Prettier)
           run: npm run format:check

         - name: TypeScript check
           run: npm run types:check

         - name: Tests PHPUnit
           run: php artisan test --compact

         - name: Tests Vitest
           run: npm run test:ci

         - name: Build Vite
           run: npm run build
   ```
2. Créer `.env.ci` — copie simplifiée de `.env.example` avec valeurs de test (DB_CONNECTION=mysql, APP_ENV=testing, etc.).
3. Push et vérifier que le workflow tourne vert sur GitHub.

## Critères de validation

- [ ] Premier push → workflow CI tourne vert.
- [ ] Toute erreur Pint, ESLint, format, TS, test ou build fait échouer le workflow.
- [ ] Badge CI vert sur le README du repo (optionnel).

## Pièges identifiés

- **MySQL 8 en service GitHub** : attendre que MySQL soit ready (options `health-cmd`).
- **`.env.ci`** : ne pas commiter de secrets. Valeurs de test only.
- **`php artisan key:generate`** : exécuté à chaque run (c'est OK).
- **Cache npm** : `actions/setup-node@v4` cache automatiquement `node_modules/`.
- **Pas de cache `vendor/`** : on peut l'ajouter plus tard si les temps CI deviennent longs.

## Références

- Stack ADR-0008
- Convention CI/CD Laravel standard
