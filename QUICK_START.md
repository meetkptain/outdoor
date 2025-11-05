# 🚀 Démarrage Rapide - Local

## ⚡ Installation en 5 Étapes

### 1. Installer les Dépendances PHP

```powershell
composer install
```

**Note** : Cette commande peut prendre quelques minutes. Attendez qu'elle se termine complètement.

### 2. Créer le Fichier .env

Si vous n'avez pas encore de fichier `.env`, créez-le avec ce contenu minimal :

```env
APP_NAME="Parapente Local"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

# SQLite (simple pour local)
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

# Queue sync (pas besoin de Redis)
QUEUE_CONNECTION=sync

# Cache file
CACHE_DRIVER=file
SESSION_DRIVER=file

# Mail log (pour voir les emails dans les logs)
MAIL_MAILER=log

# Stripe TEST (obtenir sur https://dashboard.stripe.com/test/apikeys)
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_test_...
```

### 3. Générer la Clé Application

```powershell
php artisan key:generate
```

### 4. Créer la Base SQLite et Migrer

```powershell
# Créer le fichier SQLite
New-Item -ItemType File -Path database/database.sqlite -Force

# Exécuter les migrations
php artisan migrate
```

### 5. Démarrer le Serveur

```powershell
php artisan serve
```

L'application sera accessible sur : **http://localhost:8000**

---

## 🔧 Si `composer install` ne fonctionne pas

Si vous avez des erreurs, essayez :

```powershell
# Nettoyer et réinstaller
Remove-Item -Recurse -Force vendor -ErrorAction SilentlyContinue
Remove-Item composer.lock -ErrorAction SilentlyContinue
composer install
```

---

## ✅ Vérification

Pour vérifier que tout fonctionne :

```powershell
# Vérifier la version Laravel
php artisan --version

# Voir les routes
php artisan route:list

# Lancer les tests
php artisan test
```

---

## 📝 Notes Importantes

1. **SQLite** : La base de données sera créée automatiquement dans `database/database.sqlite`
2. **Pas besoin de Redis** : En mode `sync`, les queues sont traitées immédiatement
3. **Pas besoin de PostgreSQL** : SQLite suffit pour le développement local
4. **Emails** : Avec `MAIL_MAILER=log`, les emails sont écrits dans `storage/logs/laravel.log`

---

## 🆘 Problèmes Courants

### "vendor/autoload.php not found"
```powershell
composer install
```

### "artisan not found"
Le fichier `artisan` devrait être créé automatiquement. Si ce n'est pas le cas, vérifiez que vous êtes dans le bon répertoire.

### "Could not open input file: artisan"
Assurez-vous d'être dans le répertoire racine du projet (là où se trouve `composer.json`).

---

## 🎯 Prochaines Étapes

Une fois l'application démarrée :
1. Visitez http://localhost:8000
2. Testez les endpoints API
3. Consultez la documentation dans `/docs`

---

**Besoin d'aide ?** Consultez `docs/LOCAL_SETUP.md` pour plus de détails.

