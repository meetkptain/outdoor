#!/bin/bash

echo "🚀 Démarrage application locale..."

# Vérifier que .env existe
if [ ! -f .env ]; then
    echo "⚠️  Fichier .env manquant. Création..."
    if [ -f .env.example ]; then
        cp .env.example .env
    else
        echo "APP_ENV=local" > .env
    fi
    php artisan key:generate
    echo "✅ Fichier .env créé et clé générée"
fi

# Créer la base SQLite si nécessaire
if [ ! -f database/database.sqlite ]; then
    echo "📦 Création base SQLite..."
    touch database/database.sqlite
    chmod 664 database/database.sqlite
    echo "✅ Base SQLite créée"
fi

# Vérifier les permissions storage
echo "🔐 Vérification permissions..."
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Exécuter les migrations
echo "🔄 Exécution migrations..."
php artisan migrate --force

# Compiler les assets
echo "📦 Compilation assets..."
npm run build 2>/dev/null || echo "⚠️  npm run build a échoué, continuons quand même..."

# Nettoyer les caches
echo "🧹 Nettoyage caches..."
php artisan optimize:clear

# Démarrer le serveur
echo ""
echo "✅ Application prête !"
echo "🌐 Serveur démarré sur http://localhost:8000"
echo ""
echo "📝 Commandes utiles :"
echo "   - Voir les routes : php artisan route:list"
echo "   - Lancer les tests : php artisan test"
echo "   - Tinker : php artisan tinker"
echo ""
php artisan serve

