# 🚀 Guide Démarrage Local

## Configuration Simple pour Développement Local

Ce guide vous permet de démarrer l'application rapidement en local avec SQLite (pas besoin de PostgreSQL) et une configuration minimale.

---

## 1. Prérequis

- **PHP** : 8.2 ou supérieur
- **Composer** : Installé
- **Node.js** : 18+ (pour compilation assets)
- **Git** : Pour cloner le projet

**Optionnel pour développement** :
- PostgreSQL (si vous préférez)
- Redis (si vous voulez tester la queue)

---

## 2. Installation Rapide

### 2.1 Cloner et Installer

```bash
# Cloner le projet (ou si déjà cloné, aller dans le dossier)
cd parapente

# Installer les dépendances PHP
composer install

# Installer les dépendances Node.js
npm install
```

### 2.2 Configuration Environnement Local

Créer un fichier `.env` à partir de `.env.example` :

```bash
# Si .env.example existe
cp .env.example .env

# Sinon, créer .env manuellement
```

Configurer `.env` pour le développement local :

```env
# Application
APP_NAME="Parapente Local"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=Europe/Paris

# Base de données SQLite (SIMPLE pour local)
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

# Si vous voulez utiliser PostgreSQL localement :
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=parapente_local
# DB_USERNAME=postgres
# DB_PASSWORD=

# Queue en mode sync (pas besoin de Redis pour dev)
QUEUE_CONNECTION=sync

# Cache en fichier (pas besoin de Redis)
CACHE_DRIVER=file
SESSION_DRIVER=file

# Mail (utiliser Mailtrap ou log pour tests)
MAIL_MAILER=log
MAIL_FROM_ADDRESS=noreply@parapente.local
MAIL_FROM_NAME="${APP_NAME}"

# Stripe (utiliser les clés TEST)
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_test_...

# Twilio (optionnel, peut être vide)
TWILIO_SID=
TWILIO_TOKEN=
TWILIO_FROM=

# Logs
LOG_CHANNEL=daily
LOG_LEVEL=debug

# Session
SESSION_LIFETIME=120
```

### 2.3 Générer la Clé Application

```bash
php artisan key:generate
```

### 2.4 Créer la Base de Données SQLite

```bash
# Créer le fichier SQLite
touch database/database.sqlite

# Assurer les permissions
chmod 664 database/database.sqlite
```

### 2.5 Exécuter les Migrations

```bash
php artisan migrate
```

### 2.6 Optionnel : Seed les Données de Test

```bash
php artisan db:seed
```

---

## 3. Compiler les Assets

```bash
# Mode développement (avec hot reload)
npm run dev

# Ou en mode watch
npm run watch

# Ou une seule compilation
npm run build
```

---

## 4. Démarrer le Serveur

### 4.1 Serveur de Développement Laravel

```bash
php artisan serve
```

L'application sera accessible sur : `http://localhost:8000`

### 4.2 Avec Hot Reload (Vite)

Dans un terminal séparé :

```bash
npm run dev
```

Cela démarre Vite sur `http://localhost:5173` avec hot reload pour les assets.

---

## 5. Configuration Simplifiée pour Local

### 5.1 Queue Workers (Optionnel)

Pour le développement local, la queue est en mode `sync` par défaut (pas besoin de worker).

Si vous voulez tester avec Redis :

```bash
# Installer Redis (Ubuntu/Debian)
sudo apt install redis-server

# Démarrer Redis
sudo systemctl start redis

# Dans .env, changer :
QUEUE_CONNECTION=redis

# Démarrer le worker
php artisan queue:work
```

### 5.2 Scheduler (Optionnel)

Pour tester le scheduler localement :

```bash
# Exécuter manuellement
php artisan schedule:run

# Ou pour voir les tâches planifiées
php artisan schedule:list
```

---

## 6. Configuration Stripe (Test Mode)

### 6.1 Obtenir les Clés Test

1. Aller sur https://dashboard.stripe.com/test/apikeys
2. Copier `Publishable key` → `STRIPE_KEY`
3. Copier `Secret key` → `STRIPE_SECRET`

### 6.2 Webhook Local avec Stripe CLI

Pour tester les webhooks localement :

```bash
# Installer Stripe CLI
# https://stripe.com/docs/stripe-cli

# Forwarder les webhooks vers localhost
stripe listen --forward-to localhost:8000/api/webhooks/stripe
```

Cela affichera un `webhook signing secret` à mettre dans `.env` :

```env
STRIPE_WEBHOOK_SECRET=whsec_...
```

---

## 7. Scripts de Démarrage Rapide

### 7.1 Script Bash (Linux/Mac)

Créer `start-local.sh` :

```bash
#!/bin/bash

echo "🚀 Démarrage application locale..."

# Vérifier que .env existe
if [ ! -f .env ]; then
    echo "⚠️  Fichier .env manquant. Création..."
    cp .env.example .env 2>/dev/null || echo "APP_ENV=local" > .env
    php artisan key:generate
fi

# Créer la base SQLite si nécessaire
if [ ! -f database/database.sqlite ]; then
    echo "📦 Création base SQLite..."
    touch database/database.sqlite
    chmod 664 database/database.sqlite
fi

# Exécuter les migrations
echo "🔄 Exécution migrations..."
php artisan migrate --force

# Compiler les assets
echo "📦 Compilation assets..."
npm run build

# Démarrer le serveur
echo "✅ Démarrage serveur sur http://localhost:8000"
php artisan serve
```

Rendre exécutable :
```bash
chmod +x start-local.sh
./start-local.sh
```

### 7.2 Script PowerShell (Windows)

Créer `start-local.ps1` :

```powershell
Write-Host "🚀 Démarrage application locale..." -ForegroundColor Green

# Vérifier que .env existe
if (-not (Test-Path .env)) {
    Write-Host "⚠️  Fichier .env manquant. Création..." -ForegroundColor Yellow
    if (Test-Path .env.example) {
        Copy-Item .env.example .env
    } else {
        "APP_ENV=local" | Out-File -FilePath .env -Encoding UTF8
    }
    php artisan key:generate
}

# Créer la base SQLite si nécessaire
if (-not (Test-Path database/database.sqlite)) {
    Write-Host "📦 Création base SQLite..." -ForegroundColor Cyan
    New-Item -ItemType File -Path database/database.sqlite -Force
}

# Exécuter les migrations
Write-Host "🔄 Exécution migrations..." -ForegroundColor Cyan
php artisan migrate --force

# Compiler les assets
Write-Host "📦 Compilation assets..." -ForegroundColor Cyan
npm run build

# Démarrer le serveur
Write-Host "✅ Démarrage serveur sur http://localhost:8000" -ForegroundColor Green
php artisan serve
```

Exécuter :
```powershell
.\start-local.ps1
```

---

## 8. URLs et Endpoints

### 8.1 URLs Principales

- **Application** : http://localhost:8000
- **API** : http://localhost:8000/api/v1/...
- **Webhooks Stripe** : http://localhost:8000/api/webhooks/stripe

### 8.2 Endpoints Test

```bash
# Test création réservation
POST http://localhost:8000/api/v1/reservations

# Test liste réservations
GET http://localhost:8000/api/v1/admin/reservations
```

---

## 9. Dépannage

### Problème : "SQLite database not found"

```bash
# Créer manuellement
touch database/database.sqlite
chmod 664 database/database.sqlite
php artisan migrate
```

### Problème : "Permission denied" sur storage

```bash
# Linux/Mac
chmod -R 775 storage bootstrap/cache

# Windows : Utiliser l'explorateur pour donner les permissions
```

### Problème : "Class not found" ou erreurs autoload

```bash
composer dump-autoload
php artisan config:clear
php artisan cache:clear
```

### Problème : Assets ne se chargent pas

```bash
# Recompiler les assets
npm run build

# Ou en mode dev
npm run dev
```

---

## 10. Données de Test

### Créer un Utilisateur Admin

```bash
php artisan tinker
```

Puis dans tinker :
```php
$admin = \App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@parapente.local',
    'password' => bcrypt('password'),
    'role' => 'admin',
]);
```

### Créer un Biplaceur

```php
$user = \App\Models\User::create([
    'name' => 'Biplaceur Test',
    'email' => 'biplaceur@parapente.local',
    'password' => bcrypt('password'),
    'role' => 'biplaceur',
]);

$biplaceur = \App\Models\Biplaceur::create([
    'user_id' => $user->id,
    'license_number' => 'TEST123',
    'max_flights_per_day' => 5,
    'is_active' => true,
]);
```

---

## 11. Configuration Email (Local)

Pour tester les emails en local, utilisez `MAIL_MAILER=log` :

```env
MAIL_MAILER=log
```

Les emails seront écrits dans `storage/logs/laravel.log` au lieu d'être envoyés.

Alternative : Utiliser **Mailtrap** (https://mailtrap.io) pour voir les emails dans un inbox de test.

---

## 12. Commandes Utiles

```bash
# Voir les routes
php artisan route:list

# Voir les migrations
php artisan migrate:status

# Créer un contrôleur
php artisan make:controller NomController

# Créer un modèle
php artisan make:model NomModel

# Lancer les tests
php artisan test

# Nettoyer les caches
php artisan optimize:clear
```

---

## 13. Structure Projet

```
parapente/
├── app/                    # Code application
├── database/
│   ├── database.sqlite    # Base SQLite (créé automatiquement)
│   └── migrations/        # Migrations
├── public/                # Point d'entrée web
├── resources/             # Views, assets
├── routes/                # Routes
├── storage/               # Logs, cache, uploads
├── tests/                 # Tests
├── .env                   # Configuration (à créer)
└── composer.json          # Dépendances PHP
```

---

## 14. Checklist Démarrage

- [ ] PHP 8.2+ installé
- [ ] Composer installé
- [ ] Node.js installé
- [ ] Dépendances installées (`composer install`, `npm install`)
- [ ] Fichier `.env` créé et configuré
- [ ] Clé application générée (`php artisan key:generate`)
- [ ] Base SQLite créée (`database/database.sqlite`)
- [ ] Migrations exécutées (`php artisan migrate`)
- [ ] Assets compilés (`npm run build`)
- [ ] Serveur démarré (`php artisan serve`)

---

**C'est prêt !** 🎉 Votre application tourne en local sur http://localhost:8000

