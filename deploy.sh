#!/bin/bash

# Script de déploiement — Floty
# Usage: ./deploy.sh
#
# Les assets (public/build/) doivent être compilés localement
# avec `npm run build` et commités avant le déploiement.

set -e

REPO_URL="git@github.com:MichaMegretDeveloppementWeb/floty.git"

# Garantir la désactivation du mode maintenance même en cas d'erreur
# Le flag MAINTENANCE_ACTIVATED evite d'appeler `artisan up` si `artisan down`
# n'a jamais ete lance avec succes (ex: echec au remote fix avant l'etape 2).
MAINTENANCE_ACTIVATED=0
cleanup() {
    if [ "$MAINTENANCE_ACTIVATED" -eq 1 ]; then
        echo "→ Désactivation du mode maintenance..."
        php artisan up 2>/dev/null || true
    fi
}
trap cleanup EXIT

echo "============================================"
echo "  Déploiement Floty"
echo "============================================"

# 1. Vérifier le remote origin
CURRENT_REMOTE=$(git remote get-url origin 2>/dev/null || echo "")
if [ "$CURRENT_REMOTE" != "$REPO_URL" ]; then
    echo "→ Correction du remote origin..."
    git remote set-url origin "$REPO_URL" 2>/dev/null || git remote add origin "$REPO_URL"
fi

# 2. Activer le mode maintenance
echo "→ Activation du mode maintenance..."
if php artisan down --retry=60; then
    MAINTENANCE_ACTIVATED=1
fi

# 3. Récupérer les dernières modifications
echo "→ Mise à jour du dépôt Git..."
git fetch origin
git reset --hard origin/main

# 4. Installer les dépendances PHP
echo "→ Installation des dépendances PHP..."
if command -v composer &>/dev/null; then
  composer install --no-dev --optimize-autoloader --no-interaction
else
  php composer.phar install --no-dev --optimize-autoloader --no-interaction
fi

# 5. Compiler les assets si npm est disponible (optionnel)
if command -v npm &>/dev/null; then
    echo "→ Installation des dépendances Node..."
    npm ci --production=false
    echo "→ Compilation des assets (Vite)..."
    npm run build
else
    echo "→ npm non disponible, les assets commités seront utilisés."
fi

# 6. Exécuter les migrations
echo "→ Exécution des migrations..."
php artisan migrate --force

# 7. Lien symbolique storage
echo "→ Vérification du lien storage..."
php artisan storage:link 2>/dev/null || true

# 8. Vider tous les caches
echo "→ Nettoyage des caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear

# 9. Optimiser les caches pour la production
echo "→ Optimisation des caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan icons:cache

# 10. Permissions
echo "→ Correction des permissions..."
chmod -R 755 storage bootstrap/cache
find storage -type d -exec chmod 755 {} \;
find storage -type f -exec chmod 644 {} \;
find bootstrap/cache -type d -exec chmod 755 {} \;
find bootstrap/cache -type f -exec chmod 644 {} \;

echo ""
echo "============================================"
echo "  Déploiement terminé avec succès !"
echo "============================================"
echo ""
echo "Version déployée : $(git rev-parse --short HEAD)"
echo "Branche : $(git branch --show-current)"
echo "Date : $(date '+%Y-%m-%d %H:%M:%S')"
