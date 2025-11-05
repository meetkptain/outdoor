# 🚀 Guide de Déploiement Production

## 📋 Vue d'Ensemble

Ce guide détaille toutes les étapes nécessaires pour déployer le système de gestion parapente en production.

---

## 1. Prérequis

### Serveur
- **PHP** : 8.2 ou supérieur
- **PostgreSQL** : 14 ou supérieur
- **Redis** : 6.0 ou supérieur (pour queue et cache)
- **Composer** : 2.x
- **Node.js** : 18+ (pour compilation assets)
- **Nginx** ou **Apache** avec mod_rewrite
- **SSL/TLS** : Certificat valide (Let's Encrypt recommandé)

### Services Externes
- **Stripe** : Compte avec clés API production
- **Mailgun** : Compte pour envoi emails
- **Twilio** : Compte pour SMS (optionnel)
- **S3** : Bucket pour stockage fichiers (optionnel)

---

## 2. Configuration Variables d'Environnement

### Fichier `.env` Production

Créer un fichier `.env` à partir de `.env.example` et configurer :

```env
# Application
APP_NAME="Parapente Club"
APP_ENV=production
APP_KEY=base64:...  # Générer avec: php artisan key:generate
APP_DEBUG=false
APP_URL=https://votre-domaine.com
APP_TIMEZONE=Europe/Paris

# Base de données
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=parapente_prod
DB_USERNAME=parapente_user
DB_PASSWORD=CHANGEZ_MOI_MOT_DE_PASSE_FORT

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0

# Queue
QUEUE_CONNECTION=redis
QUEUE_DEFAULT_QUEUE=default

# Cache
CACHE_DRIVER=redis
SESSION_DRIVER=redis

# Stripe (Production)
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Mailgun
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=votre-domaine.com
MAILGUN_SECRET=key-...
MAILGUN_ENDPOINT=api.mailgun.net

# Twilio (Optionnel)
TWILIO_SID=AC...
TWILIO_TOKEN=...
TWILIO_FROM=+33612345678

# S3 (Optionnel)
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=eu-west-1
AWS_BUCKET=parapente-uploads

# Logs
LOG_CHANNEL=daily
LOG_LEVEL=error  # En production, utiliser 'error' ou 'warning'
LOG_DEPRECATIONS_CHANNEL=null

# Sécurité
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

### Variables Importantes

#### Sécurité
- `APP_DEBUG=false` : **TOUJOURS** en production
- `APP_ENV=production` : **OBLIGATOIRE**
- `SESSION_SECURE_COOKIE=true` : Pour HTTPS uniquement
- `APP_KEY` : Générer avec `php artisan key:generate`

#### Stripe
- Utiliser les clés **LIVE** (`pk_live_` et `sk_live_`)
- Configurer le webhook dans le dashboard Stripe :
  - URL : `https://votre-domaine.com/api/webhooks/stripe`
  - Événements : Tous les événements de paiement

---

## 3. Installation et Configuration

### 3.1 Installation des Dépendances

```bash
# Cloner le repository (si pas déjà fait)
git clone https://github.com/votre-repo/parapente.git
cd parapente

# Installer les dépendances PHP
composer install --no-dev --optimize-autoloader

# Installer les dépendances Node.js
npm ci --production

# Compiler les assets
npm run build
```

### 3.2 Configuration Base de Données

```bash
# Créer la base de données PostgreSQL
sudo -u postgres psql
CREATE DATABASE parapente_prod;
CREATE USER parapente_user WITH PASSWORD 'CHANGEZ_MOI_MOT_DE_PASSE_FORT';
GRANT ALL PRIVILEGES ON DATABASE parapente_prod TO parapente_user;
\q

# Exécuter les migrations
php artisan migrate --force

# Optionnel : Seed les données initiales
php artisan db:seed --class=ProductionSeeder
```

### 3.3 Optimisation Laravel

```bash
# Cache de configuration
php artisan config:cache

# Cache des routes
php artisan route:cache

# Cache des vues
php artisan view:cache

# Cache des événements
php artisan event:cache

# Optimisation autoloader
composer dump-autoload --optimize --classmap-authoritative
```

### 3.4 Permissions

```bash
# Définir les permissions correctes
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Créer les répertoires si nécessaire
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p storage/logs
```

---

## 4. Configuration Serveur Web

### 4.1 Nginx Configuration

Créer `/etc/nginx/sites-available/parapente` :

```nginx
server {
    listen 80;
    server_name votre-domaine.com www.votre-domaine.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name votre-domaine.com www.votre-domaine.com;
    root /var/www/parapente/public;

    ssl_certificate /etc/letsencrypt/live/votre-domaine.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/votre-domaine.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Augmenter la taille max pour uploads
    client_max_body_size 10M;
}
```

Activer le site :
```bash
sudo ln -s /etc/nginx/sites-available/parapente /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 4.2 Apache Configuration

Si vous utilisez Apache, créer `/etc/apache2/sites-available/parapente.conf` :

```apache
<VirtualHost *:80>
    ServerName votre-domaine.com
    Redirect permanent / https://votre-domaine.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName votre-domaine.com
    DocumentRoot /var/www/parapente/public

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/votre-domaine.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/votre-domaine.com/privkey.pem

    <Directory /var/www/parapente/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/parapente_error.log
    CustomLog ${APACHE_LOG_DIR}/parapente_access.log combined
</VirtualHost>
```

---

## 5. Configuration Queue Workers

### 5.1 Supervisor Configuration

Créer `/etc/supervisor/conf.d/parapente-worker.conf` :

```ini
[program:parapente-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/parapente/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/parapente/storage/logs/worker.log
stopwaitsecs=3600
```

Démarrer les workers :
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start parapente-worker:*
```

### 5.2 Vérification Queue

```bash
# Vérifier que les workers tournent
sudo supervisorctl status

# Voir les logs
tail -f /var/www/parapente/storage/logs/worker.log
```

---

## 6. Configuration Scheduler (Cron)

### 6.1 Configuration Crontab

Éditer le crontab :
```bash
sudo crontab -e -u www-data
```

Ajouter :
```bash
* * * * * cd /var/www/parapente && php artisan schedule:run >> /dev/null 2>&1
```

### 6.2 Vérification Scheduler

```bash
# Voir les tâches planifiées
php artisan schedule:list

# Tester l'exécution
php artisan schedule:run
```

---

## 7. Configuration SSL/TLS

### 7.1 Let's Encrypt (Certbot)

```bash
# Installer Certbot
sudo apt install certbot python3-certbot-nginx

# Obtenir le certificat
sudo certbot --nginx -d votre-domaine.com -d www.votre-domaine.com

# Auto-renouvellement
sudo certbot renew --dry-run
```

### 7.2 Auto-Renouvellement

Le certificat se renouvelle automatiquement via cron. Vérifier :
```bash
sudo systemctl status certbot.timer
```

---

## 8. Configuration Monitoring

### 8.1 Logs Laravel

Les logs sont dans `storage/logs/laravel.log` :

```bash
# Voir les logs en temps réel
tail -f storage/logs/laravel.log

# Rotation automatique (déjà configuré avec LOG_CHANNEL=daily)
```

### 8.2 Monitoring Queue

```bash
# Voir le nombre de jobs en attente
php artisan queue:monitor redis:default

# Voir les jobs échoués
php artisan queue:failed
```

### 8.3 Monitoring Système

```bash
# CPU et mémoire
htop

# Espace disque
df -h

# Logs système
journalctl -u nginx
journalctl -u php8.2-fpm
```

---

## 9. Backup et Récupération

### 9.1 Backup Base de Données

Créer un script `/usr/local/bin/backup-parapente.sh` :

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/parapente"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="parapente_prod"
DB_USER="parapente_user"

mkdir -p $BACKUP_DIR

# Backup PostgreSQL
PGPASSWORD="VOTRE_MOT_DE_PASSE" pg_dump -U $DB_USER -h localhost $DB_NAME | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Garder seulement les 30 derniers backups
find $BACKUP_DIR -name "db_*.sql.gz" -mtime +30 -delete

echo "Backup completed: $BACKUP_DIR/db_$DATE.sql.gz"
```

Rendre exécutable :
```bash
chmod +x /usr/local/bin/backup-parapente.sh
```

Ajouter au crontab (tous les jours à 2h) :
```bash
0 2 * * * /usr/local/bin/backup-parapente.sh
```

### 9.2 Backup Fichiers

```bash
# Backup storage/uploads
tar -czf /var/backups/parapente/storage_$(date +%Y%m%d).tar.gz /var/www/parapente/storage/app
```

### 9.3 Récupération

```bash
# Restaurer la base de données
gunzip < /var/backups/parapente/db_20240101_120000.sql.gz | psql -U parapente_user -d parapente_prod
```

---

## 10. Checklist Déploiement

### Avant Déploiement
- [ ] Variables d'environnement configurées
- [ ] Clés Stripe production configurées
- [ ] Webhook Stripe configuré
- [ ] Base de données créée et migrée
- [ ] SSL/TLS configuré
- [ ] Permissions fichiers correctes

### Après Déploiement
- [ ] Queue workers actifs
- [ ] Scheduler cron configuré
- [ ] Tests fonctionnels passés
- [ ] Monitoring configuré
- [ ] Backup automatique configuré
- [ ] Documentation équipe mise à jour

### Tests Post-Déploiement
- [ ] Créer une réservation test
- [ ] Vérifier paiement Stripe (test mode d'abord)
- [ ] Vérifier envoi emails
- [ ] Vérifier webhooks Stripe
- [ ] Vérifier scheduler (commande manuelle)
- [ ] Vérifier queue workers

---

## 11. Maintenance

### Mises à Jour

```bash
# Mettre à jour le code
git pull origin main

# Mettre à jour les dépendances
composer install --no-dev --optimize-autoloader
npm ci --production && npm run build

# Exécuter les migrations
php artisan migrate --force

# Recharger les caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Redémarrer les workers
sudo supervisorctl restart parapente-worker:*
```

### Nettoyage

```bash
# Nettoyer les anciens jobs échoués
php artisan queue:flush

# Nettoyer les anciens logs (rotation automatique)
# Vérifier storage/logs/laravel-*.log

# Nettoyer le cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

---

## 12. Sécurité

### Recommandations
1. **Ne JAMAIS** commiter le fichier `.env`
2. **Changer** tous les mots de passe par défaut
3. **Utiliser** des mots de passe forts (12+ caractères)
4. **Activer** le firewall (UFW recommandé)
5. **Mettre à jour** régulièrement le système
6. **Surveiller** les logs pour activité suspecte
7. **Limiter** les accès SSH (clés uniquement)
8. **Configurer** fail2ban pour protection brute force

### Firewall UFW

```bash
# Activer UFW
sudo ufw enable

# Autoriser SSH
sudo ufw allow 22/tcp

# Autoriser HTTP/HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Vérifier le statut
sudo ufw status
```

---

## 13. Support et Troubleshooting

### Problèmes Courants

#### Queue workers ne démarrent pas
```bash
sudo supervisorctl status
sudo supervisorctl restart parapente-worker:*
tail -f /var/www/parapente/storage/logs/worker.log
```

#### Scheduler ne s'exécute pas
```bash
# Vérifier le crontab
sudo crontab -l -u www-data

# Tester manuellement
php artisan schedule:run
```

#### Erreurs 500
```bash
# Vérifier les logs
tail -f storage/logs/laravel.log

# Vérifier les permissions
ls -la storage bootstrap/cache
```

#### Webhooks Stripe ne fonctionnent pas
```bash
# Vérifier la signature dans les logs
tail -f storage/logs/laravel.log | grep webhook

# Vérifier la configuration Stripe
php artisan tinker
config('services.stripe.webhook_secret')
```

---

## 14. Contacts et Ressources

- **Documentation Laravel** : https://laravel.com/docs
- **Documentation Stripe** : https://stripe.com/docs
- **Support** : support@votre-domaine.com

---

**Dernière mise à jour** : 2024  
**Version** : 1.0.0

