# Task 00.13 — Configurer le déploiement Hostinger (préparation)

> **Phase** : 00 — Init
> **Statut** : à faire
> **Dépendances** : 00.12 (pipeline CI qui build les assets)
> **Estimation** : 1-2h

---

## Objectif

**Préparer** la procédure de déploiement vers Hostinger Business. Le déploiement **effectif** de Floty V1 aura lieu en phase 13 (livraison), mais la mécanique est posée maintenant :

- Workflow GitHub Actions déclenché sur push `main` (après CI vert) qui pousse les fichiers + lance les commandes Artisan via SSH.
- Commandes Artisan standard production (composer install no-dev, config cache, etc.).
- Gestion du `storage/app/declarations/` non commit mais présent en production.

## Méthode

1. **Obtenir les crédentials SSH Hostinger** (via hPanel : Avancé → Accès SSH). Vérifier que `ssh user@server.host` fonctionne en local.
2. Créer un couple **clé SSH dédiée GitHub Actions** (ne pas utiliser ta clé perso) :
   ```bash
   ssh-keygen -t ed25519 -C "github-actions-floty" -f ~/.ssh/floty_deploy
   ```
3. Ajouter la clé publique (`floty_deploy.pub`) dans les `authorized_keys` du compte SSH Hostinger.
4. Ajouter la clé privée (`floty_deploy`) comme secret GitHub Actions : `SSH_PRIVATE_KEY_HOSTINGER`.
5. Ajouter dans les secrets GitHub : `SSH_HOST`, `SSH_USER`, `SSH_PATH_APP` (chemin du projet sur Hostinger, ex: `~/public_html`).
6. Créer `.github/workflows/deploy.yml` — déclenché manuellement au début (push ciblé en phase 13) :
   ```yaml
   name: Deploy to Hostinger

   on:
     workflow_dispatch:
     # (plus tard, on pourra activer : push: branches: [main])

   jobs:
     deploy:
       runs-on: ubuntu-latest
       steps:
         - uses: actions/checkout@v4

         - name: Setup PHP 8.5
           uses: shivammathur/setup-php@v2
           with: { php-version: '8.5' }

         - name: Setup Node 22
           uses: actions/setup-node@v4
           with: { node-version: '22' }

         - name: Install PHP deps (prod)
           run: composer install --no-dev --optimize-autoloader --no-interaction

         - name: Install JS deps
           run: npm ci

         - name: Generate types TS
           run: php artisan typescript:transform

         - name: Build assets
           run: npm run build

         - name: Deploy via rsync over SSH
           uses: easingthemes/ssh-deploy@main
           with:
             SSH_PRIVATE_KEY: ${{ secrets.SSH_PRIVATE_KEY_HOSTINGER }}
             REMOTE_HOST: ${{ secrets.SSH_HOST }}
             REMOTE_USER: ${{ secrets.SSH_USER }}
             TARGET: ${{ secrets.SSH_PATH_APP }}
             EXCLUDE: "/node_modules/, /tests/, /.git/, /.env"
             ARGS: "-avz --delete-excluded"

         - name: Post-deploy Artisan commands
           uses: appleboy/ssh-action@v1
           with:
             host: ${{ secrets.SSH_HOST }}
             username: ${{ secrets.SSH_USER }}
             key: ${{ secrets.SSH_PRIVATE_KEY_HOSTINGER }}
             script: |
               cd ${{ secrets.SSH_PATH_APP }}
               php composer2 install --no-dev --optimize-autoloader
               php artisan migrate --force
               php artisan config:cache
               php artisan route:cache
               php artisan view:cache
               php artisan cache:clear
   ```
7. **Ne pas exécuter le workflow maintenant** — on se contente de le valider syntaxiquement en commit.
8. Documenter dans `docs/deployment-hostinger-procedure.md` la procédure manuelle de secours (si le workflow échoue).

## Critères de validation

- [ ] Clé SSH dédiée créée et authentifiée sur Hostinger.
- [ ] Secrets GitHub Actions configurés.
- [ ] `.github/workflows/deploy.yml` commité (pas encore déclenché).
- [ ] `docs/deployment-hostinger-procedure.md` documente la procédure manuelle.

## Pièges identifiés

- **`php composer2`** : Hostinger Business utilise `composer2` comme commande (pas `composer`). Ne pas oublier dans les commandes post-deploy.
- **`.env` en production** : **ne JAMAIS** le déployer via rsync. Il est saisi manuellement une fois sur le serveur (cf. phase 13).
- **`storage/app/declarations/`** : créer le dossier sur le serveur avant le premier déploiement. Le rsync préserve son contenu (ne pas mettre dans EXCLUDE mais dans un sous-dossier ignoré par rsync `--exclude=storage/app/declarations`).
- **Cache de config** : `php artisan config:cache` nécessite que `.env` soit déjà présent sur le serveur.

## Références

- ADR-0008 (Hostinger Business)
- `docs/deployment-hostinger-procedure.md` à créer
