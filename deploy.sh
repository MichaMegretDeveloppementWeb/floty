#!/bin/bash

# Script de déploiement — Floty
# Usage:        ./deploy.sh
# PHP custom :  PHP_BIN=/opt/alt/php85/usr/bin/php ./deploy.sh
#
# Les assets (public/build/) doivent être compilés localement
# avec `npm run build` et commités avant le déploiement.

set -e

REPO_URL="git@github.com:MichaMegretDeveloppementWeb/floty.git"

# ─── Détection du binaire PHP 8.5 ───────────────────────────────────
# Hostinger (CloudLinux) bascule la version PHP web via le panel mais
# laisse la CLI sur PHP 8.4 par défaut. Composer / artisan / migrations
# doivent impérativement tourner sur PHP 8.5.
#
# Ordre de résolution :
#   1. Variable d'environnement PHP_BIN (override explicite)
#   2. Auto-détection sur les chemins Hostinger / cPanel courants
#   3. `php` du PATH (fallback — risque PHP 8.4)
PHP_CANDIDATES=(
    "/opt/alt/php85/usr/bin/php"
    "/opt/cpanel/ea-php85/root/usr/bin/php"
    "/usr/bin/php8.5"
    "/usr/bin/php85"
)

if [ -z "${PHP_BIN:-}" ]; then
    for candidate in "${PHP_CANDIDATES[@]}"; do
        if [ -x "$candidate" ]; then
            PHP_BIN="$candidate"
            break
        fi
    done
fi

if [ -z "${PHP_BIN:-}" ]; then
    PHP_BIN="$(command -v php || true)"
fi

if [ -z "$PHP_BIN" ] || [ ! -x "$PHP_BIN" ]; then
    echo "✗ Aucun binaire PHP utilisable trouvé." >&2
    echo "  Définir PHP_BIN=/chemin/vers/php (cible PHP 8.5) puis relancer." >&2
    exit 1
fi

PHP_VERSION=$("$PHP_BIN" -r 'echo PHP_VERSION;')
echo "→ PHP utilisé : $PHP_BIN ($PHP_VERSION)"

# Composer doit utiliser le même PHP que la CLI
COMPOSER_RUNNER=("$PHP_BIN")
if command -v composer &>/dev/null; then
    COMPOSER_RUNNER+=("$(command -v composer)")
elif [ -f composer.phar ]; then
    COMPOSER_RUNNER+=("composer.phar")
else
    echo "✗ composer introuvable (ni dans le PATH ni en local)." >&2
    exit 1
fi

# Garantir la désactivation du mode maintenance même en cas d'erreur
# Le flag MAINTENANCE_ACTIVATED evite d'appeler `artisan up` si `artisan down`
# n'a jamais ete lance avec succes (ex: echec au remote fix avant l'etape 2).
MAINTENANCE_ACTIVATED=0
cleanup() {
    if [ "$MAINTENANCE_ACTIVATED" -eq 1 ]; then
        echo "→ Désactivation du mode maintenance..."
        "$PHP_BIN" artisan up 2>/dev/null || true
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
if "$PHP_BIN" artisan down --retry=60; then
    MAINTENANCE_ACTIVATED=1
fi

# 3. Récupérer les dernières modifications
echo "→ Mise à jour du dépôt Git..."
git fetch origin
git reset --hard origin/main

# 4. Installer les dépendances PHP
echo "→ Installation des dépendances PHP..."
"${COMPOSER_RUNNER[@]}" install --no-dev --optimize-autoloader --no-interaction

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
"$PHP_BIN" artisan migrate --force

# 7. Lien symbolique storage
echo "→ Vérification du lien storage..."
"$PHP_BIN" artisan storage:link 2>/dev/null || true

# 8. Vider tous les caches
echo "→ Nettoyage des caches..."
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan cache:clear
"$PHP_BIN" artisan route:clear
"$PHP_BIN" artisan view:clear
"$PHP_BIN" artisan event:clear

# 9. Optimiser les caches pour la production
echo "→ Optimisation des caches..."
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan event:cache

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
